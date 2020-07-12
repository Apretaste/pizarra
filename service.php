<?php

use Apretaste\Amulets;
use Apretaste\Challenges;
use Apretaste\Chats;
use Apretaste\Level;
use Apretaste\Notifications;
use Apretaste\Person;
use Apretaste\Request;
use Apretaste\Response;
use Framework\Alert;
use Framework\Database;
use Framework\Images;
use Framework\Utils;

class Service
{
	/**
	 * To list latest notes or post a new note
	 *
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws Alert
	 * @throws Exception
	 * @author salvipascual
	 *
	 */
	public function _main(Request $request, Response $response): void
	{
		// get the type of search
		$keyword = $request->input->data->search ?? '';
		$search = $this->getSearchType($keyword);
		[$searchType, $searchValue] = $search;
		$title = '';

		// get the user's profile
		$profile = $request->person;

		// get notes if searched by topic
		if ($searchType === 'topic') {
			$notes = $this->getNotesByTopic($profile, $searchValue);
			$title = "#$searchValue";
		}

		// get notes if searched by username
		if ($searchType === 'username') {
			$notes = $this->getNotesByUsername($profile, $searchValue);
			$title = "Notas de @$searchValue";
		}

		// get notes if searched by keyword
		if ($searchType === 'keyword') {
			$notes = $this->getNotesByKeyword($profile, $searchValue);
			$title = $searchValue;
		}

		$myUser = $this->preparePizarraUser($request->person);

		$pathToService = SERVICE_PATH . $response->service;
		$images[] = "$pathToService/images/img-prev.png";

		if (empty($notes)) {
			$content = [
				'header' => 'Lo sentimos',
				'icon' => 'sentiment_very_dissatisfied',
				'text' => 'No encontramos notas que vayan con su búsqueda. Puede buscar por palabras, por @username o por #Tema.',
				'activeIcon' => 1,
				'myUser' => $myUser
			];

			$response->setLayout('pizarra.ejs');
			$response->setTemplate('message.ejs', $content, $images);

			return;
		}

		// get most popular topics of last 7 days
		$popularTopics = Database::query('
			SELECT topic, count(id) as total FROM _pizarra_topics
			WHERE created > DATE_ADD(NOW(), INTERVAL -7 DAY)
			GROUP BY topic ORDER BY total DESC LIMIT 10');

		$topics = [];
		foreach ($popularTopics as $topic) {
			$topics[] = $topic->topic;
		}

		// create variables for the template
		$content = [
			'isProfileIncomplete' => $profile->completion < 70,
			'notes' => $notes,
			'popularTopics' => $topics,
			'title' => $title,
			'num_notifications' => $profile->notifications,
			'myUser' => $myUser,
			'activeIcon' => 1
		];

		// create the response
		$response->setLayout('pizarra.ejs');
		$response->SetTemplate('main.ejs', $content, $images);
	}

	/**
	 * The user likes a note
	 *
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws \Exception
	 * @author salvipascual
	 */
	public function _like(Request $request, Response $response)
	{
		$type = isset($request->input->data->note) ? 'note' : 'comment';
		$actionsTable = $type === 'note' ? '_pizarra_actions' : '_pizarra_comments_actions';
		$rowsTable = $type === 'note' ? '_pizarra_notes' : '_pizarra_comments';
		$noteId = $type === 'note' ? $request->input->data->note : $request->input->data->comment;

		if ($noteId === 'last') {
			$noteId = Database::query("SELECT MAX(id) AS id FROM $rowsTable WHERE id_person = '{$request->person->id}'")[0]->id;
		}

		// check if the user already liked this note
		$res = Database::query("SELECT * FROM $actionsTable WHERE id_person={$request->person->id} AND $type='{$noteId}'");
		$note = Database::query("SELECT id_person, `text` FROM $rowsTable WHERE id='{$noteId}'");

		if (!empty($note)) {
			$note = $note[0];
			$liked = false;

			if (!empty($res)) {
				if ($res[0]->action === 'unlike') {
					// delete previous vote and add new vote
					Database::query("
                        UPDATE $actionsTable SET `action`='like' WHERE id_person='{$request->person->id}' AND $type='{$noteId}';
                        UPDATE $rowsTable SET likes=likes+1, unlikes=unlikes-1 WHERE id='{$noteId}'");

					// create notification for the creator
					if ($request->person->id != $note->id_person) {
						Notifications::alert($note->id_person, "El usuario @{$request->person->username} le dio like a tu nota en la Pizarra: {$note->text}", 'thumb_up', "{'command':'PIZARRA NOTA', 'data':{'note':'{$noteId}'}}");
					}

					$liked = true;
				}
			}

			if (!$liked) {
				// add new vote
				Database::query("INSERT INTO $actionsTable (id_person,$type,action) VALUES ('{$request->person->id}','{$noteId}','like');    
                            UPDATE $rowsTable SET likes=likes+1 WHERE id='{$noteId}'");

				$note->text = substr($note->text, 0, 30) . '...';

				$this->addReputation($note->id_person, $request->person->id, $noteId, 0.3);

				// create notification for the creator
				if ($request->person->id !== $note->id_person) {
					Notifications::alert($note->id_person, "El usuario @{$request->person->username} le dio like a tu nota en la Pizarra: {$note->text}", 'thumb_up', "{'command':'PIZARRA NOTA', 'data':{'note':'{$noteId}'}}");
				}

				// complete the challenge
				Challenges::complete('like-pizarra-note', $request->person->id);
			}

			// track challenges
			Challenges::track($note->id_person, 'pizarra-likes-100', ['publish' => true, 'likes' => 0], static function ($track) {
				$track['publish'] = true;
				$track['likes']++;
				return $track;
			});
		}
	}

	/**
	 * The user unlikes a note
	 *
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws Alert
	 * @author salvipascual
	 *
	 */
	public function _unlike(Request $request, Response $response): void
	{
		$type = isset($request->input->data->note) ? 'note' : 'comment';
		$actionsTable = $type === 'note' ? '_pizarra_actions' : '_pizarra_comments_actions';
		$rowsTable = $type === 'note' ? '_pizarra_notes' : '_pizarra_comments';
		$noteId = $type === 'note' ? $request->input->data->note : $request->input->data->comment;

		if ($noteId === 'last') {
			$noteId = Database::query("SELECT MAX(id) AS id FROM $rowsTable WHERE id_person = '{$request->person->id}'")[0]->id;
		}

		// check if the user already liked this note
		$res = Database::query("SELECT * FROM $actionsTable WHERE id_person={$request->person->id} AND $type='{$noteId}'");
		$note = Database::query("SELECT id_person, `text` FROM $rowsTable WHERE id='{$noteId}'");

		// do not continue if note do not exist
		if (empty($note)) {
			return;
		}

		// delete previos upvote and add new vote
		if (!empty($res)) {
			if ($res[0]->action === 'like') {
				Database::query("
				UPDATE $actionsTable SET `action`='unlike' WHERE id_person='{$request->person->id}' AND $type='{$noteId}';
				UPDATE $rowsTable SET likes=likes-1, unlikes=unlikes+1 WHERE id='{$noteId}'");
			}
			return;
		}

		// delete previos vote and add new vote
		Database::query("
			INSERT INTO $actionsTable (id_person,$type,action) VALUES ('{$request->person->id}','{$noteId}','unlike');
			UPDATE $rowsTable SET unlikes=unlikes+1 WHERE id='{$noteId}'");

		$note = $note[0];
		$this->addReputation($note->id_person, $request->person->id, $noteId, -0.3);

		// decrease the author's reputation
		Database::query("UPDATE _pizarra_users SET reputation=reputation-1 WHERE id_person='{$note->id_person}'");

		// run powers for amulet VIDENTE
		if (Amulets::isActive(Amulets::VIDENTE, $note->id_person)) {
			$msg = "Los poderes del amuleto del Druida te avisan: A @{$request->person->username} le disgustó tu nota en Pizarra";
			Notifications::alert($note->id_person, $msg, 'remove_red_eye', "{command:'PERFIL', data:{username:'@{$request->person->username}'}}");
		}
	}

	/**
	 * NOTA subservice
	 *
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws Alert
	 * @author salvipascual
	 *
	 */
	public function _nota(Request $request, Response $response): void
	{
		$noteId = $request->input->data->note;
		if (empty($noteId)) {
			$response->setLayout('pizarra.ejs');
			$response->setTemplate('notFound.ejs', [
				'origin' => 'note',
				'num_notifications' => $request->person->notifications,
			]);

			return;
		}

		if ($noteId === 'last') {
			$noteId = Database::query("SELECT MAX(id) AS id FROM _pizarra_notes WHERE id_person = '{$request->person->id}'")[0]->id;
		}

		// get the records from the db
		$result = Database::query("
			SELECT
				A.id, A.id_person, A.text, A.image, A.likes, A.unlikes, A.comments, A.inserted, A.ad, A.topic1, A.topic2, A.topic3, A.accept_comments, A.staff,
				C.avatar, C.avatarColor, C.username, C.first_name, C.last_name, C.province, C.picture, C.gender, C.country, C.online,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE note=A.id AND A.id_person='{$request->person->id}' AND action='like') > 0 AS isliked,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE note=A.id AND A.id_person='{$request->person->id}' AND action='unlike') > 0 AS isunliked
			FROM _pizarra_notes A LEFT JOIN _pizarra_users B ON A.id_person = B.id_person LEFT JOIN person C ON C.id = B.id_person
			WHERE A.id = '$noteId' AND A.active=1");

		// format note
		if ($result) {
			$note = $this->formatNote($result[0], $request->person->id);
		} else {
			$response->setLayout('pizarra.ejs');
			$response->setTemplate('notFound.ejs', [
				'origin' => 'note',
				'num_notifications' => $request->person->notifications,
			]);

			return;
		}

		//check if the user is blocked by the owner of the note
		$blocks = Chats::isBlocked($request->person->id, $note['id_person']);
		if ($blocks->blocked || $blocks->blockedByMe) {
			$content = [
				'username' => $note['username'],
				'origin' => 'note',
				'num_notifications' => $request->person->notifications,
				'blocks' => $blocks,
			];
			$response->setTemplate('blocked.ejs', $content);

			return;
		}

		// get note comments
		$cmts = Database::query("
			SELECT A.*, B.username, B.province, B.picture, B.gender, B.country, B.online, B.avatar, B.avatarColor, 
			(SELECT COUNT(comment) FROM _pizarra_comments_actions WHERE comment=A.id AND A.id_person='{$request->person->id}' AND action='like') > 0 AS isliked,
			(SELECT COUNT(comment) FROM _pizarra_comments_actions WHERE comment=A.id AND A.id_person='{$request->person->id}' AND action='unlike') > 0 AS isunliked
			FROM _pizarra_comments A
			LEFT JOIN person B
			ON A.id_person = B.id
			LEFT JOIN _pizarra_users C
			ON C.id_person = B.id
			WHERE note = '$noteId'");

		// format comments
		$comments = [];
		if ($cmts) {
			foreach ($cmts as $c) {
				$comments[] = $this->formatNote($c, $request->person->id);
			}
		}
		$note['comments'] = $comments;

		Database::query("UPDATE _pizarra_notes SET views=views+1 WHERE id={$note['id']}");
		$this->addReputation($note['id_person'], $request->person->id, $noteId, 0.1);

		$myUser = $this->preparePizarraUser($request->person);

		$images = [];
		if ($note['image']) {
			$pizarraImgDir = SHARED_PUBLIC_PATH . '/content/pizarra';
			$images[] = "$pizarraImgDir/{$note['image']}";
		}

		$content = [
			'person_id' => $request->person->id,
			'note' => $note,
			'myUser' => $myUser,
			'activeIcon' => 1
		];

		$response->setLayout('pizarra.ejs');
		$response->SetTemplate('note.ejs', $content, $images);
	}

	/**
	 * Post a new note to the public feed
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _escribir(Request $request, Response $response): void
	{
		$text = $request->input->data->text; // strip_tags
		$image = $request->input->data->image ?? false;
		$fileName = '';
		$ad = 0;

		// get the image name and path
		if ($image) {
			$pizarraImgDir = SHARED_PUBLIC_PATH . '/content/pizarra';
			$fileName = Utils::randomHash();
			$filePath = "$pizarraImgDir/$fileName.jpg";

			// save the optimized image on the user folder
			file_put_contents($filePath, base64_decode($image));
			Images::optimize($filePath);
		}

		// only post notes with real content
		if (strlen($text) < 20) {
			return;
		}

		// get all the topics from the post
		preg_match_all('/#\w*/', $text, $topics);
		$topics = empty($topics[0]) ? [Database::query("SELECT default_topic FROM _pizarra_users WHERE id_person='{$request->person->id}'")[0]->default_topic] : $topics[0];

		// cut and escape values
		foreach ($topics as $i => $iValue) {
			$topics[$i] = Database::escape($iValue, 20);
		}

		$topic1 = isset($topics[0]) ? str_replace('#', '', $topics[0]) : '';
		$topic2 = isset($topics[1]) ? str_replace('#', '', $topics[1]) : '';
		$topic3 = isset($topics[2]) ? str_replace('#', '', $topics[2]) : '';

		// run powers for amulet PRIORIDAD
		if (Amulets::isActive(Amulets::PRIORIDAD, $request->person->id)) {
			// make the note into an ad
			$ad = 1;

			// alert the user
			$msg = 'Los poderes del amuleto del druida harán que la nota que publicaste sea vista por muchas más personas';
			Notifications::alert($request->person->id, $msg, 'local_florist', '{command:"PIROPAZO PERFIL"}}');
		}

		// save note to the database
		$cleanText = Database::escape($text, 300);
		$sql = "INSERT INTO _pizarra_notes (id_person, `text`, image, ad, topic1, topic2, topic3) VALUES ('{$request->person->id}', '$cleanText', '$fileName', $ad, '$topic1', '$topic2', '$topic3')";
		$noteID = Database::query($sql);

		// error if the note could not be inserted
		if (!is_numeric($noteID)) {
			throw new RuntimeException("PIZARRA: NoteID is null after INSERT. QUERY = $sql");
		}

		// complete the challenge
		Challenges::complete('write-pizarra-note', $request->person->id);

		// track challenges
		Challenges::track($request->person->id, 'pizarra-likes-100', ['publish' => false, 'likes' => 0], static function ($track) {
			$track['publish'] = true;
			return $track;
		});

		Challenges::track($request->person->id, 'pizarra-comments-20', ['publish' => false, 'comments' => 0], static function ($track) {
			$track['publish'] = true;
			return $track;
		});

		// add the experience
		Level::setExperience('PIZARRA_POST_FIRST_DAILY', $request->person->id);

		// save the topics to the topics table
		foreach ($topics as $topic) {
			$topic = str_replace('#', '', $topic);
			$topic = Database::escape($topic, 20);
			Database::query("INSERT INTO _pizarra_topics (topic, note, id_person) VALUES ('$topic', '$noteID', '{$request->person->id}')", true);
		}

		// notify users mentioned
		$mentions = $this->findUsersMentionedOnText($text);
		$color = $request->person->gender === 'M' ? 'pizarra-color-text' : ($request->person->gender === 'F' ? 'pink-text' : 'black-text');
		foreach ($mentions as $m) {
			$blocks = Chats::isBlocked($request->person->id, $m->id);
			if ($blocks->blocked > 0) {
				continue;
			}

			Notifications::alert($m->id, "<span class=\"$color\">@{$request->person->username}</span> le ha mencionado", 'comment', "{'command':'PIZARRA NOTA', 'data':{'note':'$noteID'}}");
			$this->addReputation($m->id, $request->person->id, $noteID, 1);
		}
	}

	/**
	 * Avatar
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 */
	public function _avatar(Request $request, Response $response): void
	{
		$response->setLayout('pizarra.ejs');
		$response->setTemplate('avatar_select.ejs', [
			'myUser' => $this->preparePizarraUser($request->person),
			'activeIcon' => 1
		]);
	}

	/**
	 * Post a new note to the public feed
	 *
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws Alert
	 * @author salvipascual
	 *
	 */
	public function _comentar(Request $request, Response $response)
	{
		$comment = $request->input->data->comment;
		$noteId = $request->input->data->note;

		if (strlen($comment) < 2) {
			return;
		}

		// check the note ID is valid
		$note = Database::query("SELECT `text`,id_person, accept_comments FROM _pizarra_notes WHERE id='$noteId' AND active=1");
		if ($note) {
			$note = $note[0];
		} else {
			return;
		}

		// si la nota no acepta comentario de otros
		if ((int) $note->accept_comments == 0 && (int) $note->id_person <> (int) $request->person->id) {
			return;
		}

		$blocks = Chats::isBlocked($request->person->id, $note->id_person);
		if ($blocks->blocked) {
			return;
		}

		// save the comment
		$comment = Database::escape($comment, 250);
		Database::query(" INSERT INTO _pizarra_comments (id_person, note, text) VALUES ('{$request->person->id}', '$noteId', '$comment');
			UPDATE _pizarra_notes SET comments = comments+1 WHERE id='$noteId';", true);

		// add the experience
		Level::setExperience('PIZARRA_COMMENT_FIRST_DAILY', $request->person->id);

		// complete the challenge
		Challenges::complete('comment-pizarra-note', $request->person->id);

		// notify users mentioned
		$mentions = $this->findUsersMentionedOnText($comment);
		foreach ($mentions as $mention) {
			$blocks = Chats::isBlocked($request->person->id, $mention->id);
			if ($blocks->blocked || $blocks->blockedByMe) {
				continue;
			}
			Notifications::alert($mention->id, "El usuario @{$request->person->username} le ha mencionado en la pizarra", 'comment', "{'command':'PIZARRA NOTA', 'data':{'note':'$noteId'}}");
			$this->addReputation($mention->id, $request->person->id, $noteId, 1);
		}

		// send a notification to the owner of the note
		$color = $request->person->gender === 'M' ? 'pizarra-color-text' : ($request->person->gender === 'F' ? 'pink-text' : 'black-text');
		if ($request->person->id !== $note->id_person) {
			Notifications::alert($note->id_person, "<span class=\"$color\">@{$request->person->username}</span> ha comentado tu publicación", 'comment', "{'command':'PIZARRA NOTA', 'data':{'note':'$noteId'}}");
			$this->addReputation($note->id_person, $request->person->id, $noteId, 0.6);
		}
	}

	/**
	 * Show extensive list of topics as a web cloud
	 *
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws Exception
	 * @author salvipascual
	 *
	 */
	public function _populares(Request $request, Response $response): void
	{
		$cacheFile = TEMP_PATH . '/pizarra_populars.tmp';
		if (file_exists($cacheFile) && time() < filemtime($cacheFile) + 15 * 60) {
			$cache = json_decode(file_get_contents($cacheFile));
			$topics = $cache->topics;
			$populars = $cache->populars;
		} else {
			// get list of topics
			$ts = Database::query("
			SELECT topic AS name, COUNT(id) AS cnt FROM _pizarra_topics
			WHERE created > DATE_ADD(NOW(), INTERVAL -30 DAY)
			AND topic <> 'general'
			GROUP BY topic ORDER BY cnt DESC LIMIT 50");

			// get params for the algorithm
			$maxLetterSize = 30;
			$minLetterSize = 10;
			$maxTopicMentions = $ts[0]->cnt;
			$minTopicMentions = $ts[count($ts) - 1]->cnt;
			$rate = ($maxTopicMentions - $minTopicMentions) / ($maxLetterSize - $minLetterSize);
			if ($rate === 0) {
				$rate = 1;
			} // avoid divisions by zero

			// get topics letter size and color
			$topics = [];
			foreach ($ts as $t) {
				$topic = new stdClass();
				$topic->name = $t->name;
				$topic->size = (($t->cnt - $minTopicMentions) / $rate) + $minLetterSize;
				$topics[] = $topic;
			}

			// set topics in random order
			shuffle($topics);

			// get the list of most popular users
			$populars = Database::query('SELECT A.id_person, B.avatar, B.avatarColor, B.username, B.first_name, B.country, B.province, B.about_me,  B.gender, B.year_of_birth, B.highest_school_level, B.online, (SELECT SUM(amount) FROM _pizarra_reputation WHERE id_person = A.id_person) AS reputation FROM _pizarra_users A JOIN person B ON A.id_person = B.id ORDER BY reputation DESC LIMIT 10');
			foreach ($populars as $popular) {
				$popular->completion = Person::find($popular->id_person)->completion;
				$popular->reputation = floor(($popular->reputation ?? 0) + $popular->completion);
			}

			usort($populars, function ($a, $b) {
				if ($a->reputation == $b->reputation) {
					return 0;
				} elseif ($a->reputation < $b->reputation) {
					return 1;
				} else {
					return -1;
				}
			});

			$cache = new stdClass();
			$cache->populars = $populars;
			$cache->topics = $topics;
			file_put_contents($cacheFile, json_encode($cache));
		}

		$myUser = $this->preparePizarraUser($request->person);


		$response->setLayout('pizarra.ejs');
		$response->SetTemplate('populars.ejs', [
			'topics' => $topics,
			'populars' => $populars,
			'myUser' => $myUser,
			'activeIcon' => 2
		]);
	}

	/**
	 * Show a list of notifications
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _notificaciones(Request $request, Response $response)
	{
		// get all unread notifications
		$notifications = Database::query("
			SELECT id,icon,`text`,link,inserted
			FROM notification
			WHERE `to` = {$request->person->id}
			AND service = 'pizarra'
			AND `hidden` = 0
			ORDER BY inserted DESC");

		$myUser = $this->preparePizarraUser($request->person);

		// if no notifications, let the user know
		if (empty($notifications)) {
			$content = [
				'header' => 'Nada por leer',
				'icon' => 'notifications_off',
				'text' => 'Por ahora usted no tiene ninguna notificación por leer.',
				'activeIcon' => 3,
				'myUser' => $myUser
			];

			$response->setLayout('pizarra.ejs');

			return $response->setTemplate('message.ejs', $content);
		}

		foreach ($notifications as $noti) {
			$noti->inserted = strtoupper(date('d/m/Y h:ia', strtotime(($noti->inserted))));
		}

		// prepare content for the view
		$content = [
			'notifications' => $notifications,
			'title' => 'Notificaciones',
			'activeIcon' => 3,
			'myUser' => $this->preparePizarraUser($request->person)
		];

		// build the response
		$response->setLayout('pizarra.ejs');
		$response->setTemplate('notifications.ejs', $content);
	}

	/**
	 * Show the user profile
	 *
	 * @param Request $request
	 * @param Response $response
	 *
	 * @return Response|void
	 * @throws Exception
	 * @author salvipascual
	 *
	 */
	public function _perfil(Request $request, Response $response)
	{
		$myUser = $this->preparePizarraUser($request->person);

		if (isset($request->input->data->username) && $request->input->data->username != $request->person->username) {
			$username = $request->input->data->username;
			// get the user's profile
			$person = Person::find($username);

			// if user do not exist, message the requestor
			if (empty($person)) {
				$response->setLayout('pizarra.ejs');
				$response->setTemplate('notFound.ejs', [
					'origin' => 'profile',
					'myUser' => $myUser,
					'activeIcon' => 1
				]);
				return;
			}

			//check if the user is blocked
			$blocks = Chats::isBlocked($request->person->id, $person->id);

			if ($blocks->blocked || $blocks->blockedByMe) {
				$content = [
					'username' => $person->username,
					'origin' => 'profile',
					'num_notifications' => $request->person->notifications,
					'blocks' => $blocks,
					'myUser' => $myUser,
					'activeIcon' => 1
				];
				$response->SetTemplate('blocked.ejs', $content);

				return;
			}

			// run powers for amulet DETECTIVE
			if (Amulets::isActive(Amulets::DETECTIVE, $person->id)) {
				$msg = "Los poderes del amuleto del Druida te avisan: @{$request->person->username} está revisando tu perfil";
				Notifications::alert($person->id, $msg, 'pageview', "{command:'PERFIL', data:{username:'@{$request->person->username}'}}");
			}

			// run powers for amulet SHADOWMODE
			if (Amulets::isActive(Amulets::SHADOWMODE, $person->id)) {
				return $response->setTemplate('message.ejs', [
					'header' => 'Shadow-Mode',
					'icon' => 'visibility_off',
					'text' => 'La magia oscura de un amuleto rodea este perfil y te impide verlo. Por mucho que intentes romperlo, el hechizo del druida es poderoso.'
				]);
			}
		} else {
			if (isset($request->input->data->avatar)) {
				Database::query("UPDATE _pizarra_users SET avatar = '{$request->input->data->avatar}', avatarColor='{$request->input->data->color}' WHERE id_person={$request->person->id}");
				Database::query("UPDATE person SET avatar = '{$request->input->data->avatar}', avatarColor='{$request->input->data->color}' WHERE id={$request->person->id}");
				$myUser->avatar = $request->input->data->avatar;
				$myUser->avatarColor = $request->input->data->color;
			}
			$person = $request->person;
		}

		$user = $this->preparePizarraUser($person);
		$person->avatar = $user->avatar;
		$person->avatarColor = $user->avatarColor;
		$person->reputation = $user->reputation;

		// create data for the view
		$content = [
			'profile' => $person,
			'myUser' => $myUser,
			'activeIcon' => 1
		];

		if ($person->id == $request->person->id) {
			$response->setLayout('pizarra.ejs');
			$response->SetTemplate('ownProfile.ejs', $content);
		} else {
			Person::setProfileTags($person);

			$response->setLayout('pizarra.ejs');
			$response->SetTemplate('profile.ejs', $content);
		}
	}

	/**
	 * Chats lists with matches filter
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 * @author ricardo
	 */

	public function _chat(Request $request, Response $response): void
	{
		// get the list of people chating with you
		$chats = Chats::open($request->person->id);

		$myUser = $this->preparePizarraUser($request->person);

		// if no matches, let the user know
		if (empty($chats)) {
			$content = [
				'header' => 'No tiene conversaciones',
				'icon' => 'sentiment_very_dissatisfied',
				'text' => 'Aún no ha hablado con nadie.',
				'title' => 'chats',
				'myUser' => $myUser,
				'activeIcon' => 1
			];

			$response->setLayout('pizarra.ejs');
			$response->setTemplate('message.ejs', $content);

			return;
		}

		foreach ($chats as $chat) {
			$user = $this->preparePizarraUser($chat, false);
			$chat->last_sent = explode(' ', $chat->last_sent)[0];
			$chat->avatar = $user->avatar;
			$chat->avatarColor = $user->avatarColor;
			unset($chat->picture);
			unset($chat->first_name);
		}

		$content = [
			'chats' => $chats,
			'myUser' => $myUser,
			'activeIcon' => 1
		];

		$response->setLayout('pizarra.ejs');
		$response->setTemplate('chats.ejs', $content);
	}

	/**
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 */
	public function _conversacion(Request $request, Response $response): void
	{
		// get the username of the note
		$user = Person::find($request->input->data->userId);

		// check if the username is valid
		if (!$user) {
			$myUser = $this->preparePizarraUser($request->person);
			$response->setTemplate('notFound.ejs', ['myUser' => $myUser]);
			return;
		}

		$messages = Chats::conversation($request->person->id, $user->id);
		$chats = [];

		foreach ($messages as $message) {
			$chat = new stdClass();
			$chat->id = $message->id;
			$chat->username = $message->username;
			$chat->text = $message->text;
			$chat->sent = $message->sent;
			$chat->read = $message->read;
			$chat->readed = $message->readed;
			$chats[] = $chat;
		}

		$chatUser = $this->preparePizarraUser($user);

		$content = [
			'messages' => $chats,
			'username' => $user->username,
			'myusername' => $request->person->username,
			'id' => $user->id,
			'online' => $user->isOnline,
			'last' => date('d/m/Y h:ia', strtotime($user->lastAccess)),
			'title' => $user->firstName,
			'myUser' => $chatUser
		];

		$response->setlayout('pizarra.ejs');
		$response->setTemplate('conversation.ejs', $content);
	}

	/**
	 *
	 * @param Request
	 * @param Response
	 *
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _mensaje(Request $request, Response $response): void
	{
		if (!isset($request->input->data->id)) {
			return;
		}
		$userTo = Person::find($request->input->data->id);
		if (!$userTo) {
			return;
		}
		$message = $request->input->data->message;

		$blocks = Chats::isBlocked($request->person->id, $userTo->id);
		if ($blocks->blocked > 0 || $blocks->blockedByMe > 0) {
			Notifications::alert(
				$request->person->id,
				"Su mensaje para @{$userTo->username} no pudo ser entregado, es posible que usted haya sido bloqueado por esa persona.",
				'error'
			);

			return;
		}

		// store the note in the database
		$message = Database::escape($message, 499);
		Database::query("INSERT INTO _note (from_user, to_user, `text`) VALUES ({$request->person->id},{$userTo->id},'$message')", true);

		$color = $request->person->gender === 'M' ? 'pizarra-color-text' : ($request->person->gender === 'F' ? 'pink-text' : 'black-text');

		// send notification for the app
		Notifications::alert(
			$userTo->id,
			"<span class=\"$color\">@{$request->person->username}</span> le ha enviado un mensaje",
			'message',
			"{'command':'PIZARRA CONVERSACION', 'data':{'userId':'{$request->person->id}'}}"
		);
	}

	/**
	 * Assign a topic to a note
	 *
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws Alert
	 * @author salvipascual
	 *
	 */
	public function _temificar(Request $request, Response $response): void
	{
		// get note ID and topic
		$noteId = $request->input->data->note;
		$topic = $request->input->data->theme;

		// get the note to update
		$note = Database::query("SELECT topic1,topic2,topic3 FROM _pizarra_notes WHERE id='$noteId' AND id_person='{$request->person->id}' AND active=1");

		if ($note && $topic) {
			// save topic in the database
			$topic = Database::escape($topic, 20);
			if (empty($note[0]->topic1)) {
				$topicToSave = "topic1='$topic'";
			} elseif (empty($note[0]->topic2)) {
				$topicToSave = "topic2='$topic'";
			} else {
				$topicToSave = "topic3='$topic'";
			}
			Database::query("
				UPDATE _pizarra_notes SET $topicToSave WHERE id='$noteId';
				INSERT INTO _pizarra_topics(topic,note,id_person) VALUES ('$topic','$noteId','{$request->person->id}');");
		}
	}

	/**
	 * @param Request
	 * @param Response
	 *
	 * @author ricardo@apretaste.org
	 *
	 */

	public function _eliminar(Request $request, Response $response): void
	{
		$noteId = $request->input->data->note;

		Database::query(
			"UPDATE _pizarra_notes SET active=0 
			WHERE id='$noteId' AND id_person='{$request->person->id}'"
		);
	}

	/**
	 * Display the help document
	 *
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws Alert
	 * @author salvipascual
	 *
	 */
	public function _ayuda(Request $request, Response $response)
	{
		$response->setLayout('pizarra.ejs');
		$response->SetTemplate('help.ejs', ['num_notifications' => $request->person->notifications]);
	}

	private function addReputation($toId, $fromId, $noteId, $amount): void
	{
		$amount = str_replace(',', '.', $amount);
		if ($toId != $fromId) {
			Database::query("INSERT INTO _pizarra_reputation(id_person, id_from, id_note, amount) VALUES ($toId, $fromId, $noteId, $amount)");
		}
	}

	/**
	 * Search what type of search the user is doing
	 *
	 * @param $keyword
	 * @return array ["type", "value"]
	 * @throws Alert
	 * @author salvipascual
	 */
	private function getSearchType($keyword): ?array
	{
		// return topic selected by the user if blank
		if (empty($keyword)) {
			return ['topic', 'general'];
			/*$topic = Database::query("SELECT default_topic FROM _pizarra_users WHERE id_person='$id'");
			if(empty($topic[0]->default_topic))
			{
				$defaultTopic = "general";
			}
			else
			{
				$defaultTopic = $topic[0]->default_topic;
			}

			return ["topic", $defaultTopic];
			*/
		}

		// get the number of words passed
		$oneWord = count(explode(' ', $keyword)) == 1;

		// check if searching for a username
		if ($oneWord && strlen($keyword) > 2 && $keyword[0] === '@') {
			return ['username', str_replace('@', '', $keyword)];
		}

		// check if searching for a topic
		$topicNoHashSymbol = str_replace('#', '', $keyword);
		$topicExists = Database::query("SELECT id FROM _pizarra_topics WHERE topic='$topicNoHashSymbol'");
		if ($topicExists) {
			return ['topic', $topicNoHashSymbol];
		} // else searching for words on a note
		else {
			return ['keyword', $keyword];
		}
	}

	/**
	 * Search and return all notes by a topic
	 *
	 * @param Person $profile
	 * @param String $topic
	 *
	 * @return array of notes
	 * @throws Exception
	 * @author salvipascual
	 *
	 */
	private function getNotesByTopic($profile, $topic): array
	{
		$where = $topic !== 'general' ? "WHERE (_pizarra_notes.topic1='$topic' OR _pizarra_notes.topic2='$topic' OR _pizarra_notes.topic3='$topic') AND active=1" : 'WHERE _pizarra_notes.active=1';
		// set the topic as default for the user

		Database::query("UPDATE _pizarra_users SET default_topic='$topic' WHERE id_person='{$profile->id}'");

		$temporaryTableName = 'temprelation_' . uniqid('', false);
		Database::query("CREATE TEMPORARY TABLE $temporaryTableName 
    			SELECT relations.user1, relations.user2 
				FROM relations 
				WHERE (user1 = {$profile->id} OR user2 = {$profile->id}) AND type = 'blocked' AND confirmed = 1;");

		// get the records from the db
		$listOfNotes = Database::query("
			SELECT
				A.id, A.id_person, A.text, A.image, A.likes, A.unlikes, 
			       A.comments,
			       (select count(distinct id_person) from _pizarra_comments WHERE _pizarra_comments.note = A.id) as commentsUnique, 
			       A.staff, 
			    A.inserted, A.ad, A.topic1, A.topic2, A.topic3,
				B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, 
			    B.country, B.online, B.avatar, B.avatarColor,
				C.reputation, 
				TIMESTAMPDIFF(HOUR,A.inserted,CURRENT_DATE) as hours,
				(SELECT COUNT(_pizarra_actions.note) FROM _pizarra_actions 
					WHERE _pizarra_actions.note = A.id AND A.id_person = {$profile->id} 
					  AND _pizarra_actions.action = 'like') > 0 AS isliked,
				(SELECT COUNT(_pizarra_actions.note) FROM _pizarra_actions 
					WHERE _pizarra_actions.note = A.id AND A.id_person = {$profile->id} 
					  AND _pizarra_actions.action = 'unlike') > 0 AS isunliked
			FROM (SELECT subq3.* 
					FROM (SELECT DISTINCT id, id_person 
						  FROM _pizarra_notes $where 
						  ORDER BY id DESC LIMIT 500) subq2 
					INNER JOIN _pizarra_notes subq3 
					ON subq2.id = subq3.id
			      ) A
			LEFT JOIN (
			    SELECT P.id, P.username, P.first_name, P.last_name, P.province, P.picture, 
			           P.gender, P.country, P.online, 
			           P.avatar, P.avatarColor
			    FROM person P LEFT JOIN $temporaryTableName ON $temporaryTableName.user1 = P.id OR $temporaryTableName.user2 = P.id
			    WHERE $temporaryTableName.user1 IS NULL AND $temporaryTableName.user2 IS NULL  			    
			) B ON A.id_person = B.id 
			JOIN _pizarra_users C ON A.id_person = C.id_person");

		$staffNotes = Database::query("
			SELECT
				A.id, A.id_person, A.text, A.image, A.likes, A.unlikes, 
			       A.comments,
			       (select count(distinct id_person) from _pizarra_comments WHERE _pizarra_comments.note = A.id) as commentsUnique, 
			       A.staff, 
			    A.inserted, A.ad, A.topic1, A.topic2, A.topic3,
				B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, 
			    B.country, B.online, B.avatar, B.avatarColor,
				C.reputation, 
				TIMESTAMPDIFF(HOUR,A.inserted,CURRENT_DATE) as hours,
				1 AS isliked,
				0 as isunliked
			FROM (SELECT subq3.* 
					FROM (SELECT id, id_person
						  FROM _pizarra_notes WHERE staff =1 and active = 1
						  ORDER BY id DESC LIMIT 500) subq2 
					INNER JOIN _pizarra_notes subq3 
					ON subq2.id = subq3.id
			      ) A
			LEFT JOIN (
			    SELECT P.id, P.username, P.first_name, P.last_name, P.province, P.picture, 
			           P.gender, P.country, P.online, 
			           P.avatar, P.avatarColor
			    FROM person P 		    
			) B ON A.id_person = B.id 
			JOIN _pizarra_users C ON A.id_person = C.id_person");

		// sort results by weight. Too complex and slow in MySQL
		usort($listOfNotes, function ($a, $b) {
			$a->score = (pow($a->hours, 0.5) * -1) * 0.4 + max($a->commentsUnique, 20) * 0.2 + (($a->likes - $a->unlikes * 2) * 0.4) + $a->ad * 1000;
			$b->score = (pow($b->hours, 0.5) * -1) * 0.4 + max($b->commentsUnique, 20) * 0.2 + (($b->likes - $b->unlikes * 2) * 0.4) + $b->ad * 1000;
			return ($b->score - $a->score) ? ($b->score - $a->score) / abs($b->score - $a->score) : 0;
		});

		// format the array of notes
		$notes = [];
		if (is_array($listOfNotes)) {
			foreach (array_merge($staffNotes, $listOfNotes)  as $note) {
				$notes[] = $this->formatNote($note, $profile->id); // format the array of notes
				if (count($notes) > 50) {
					break;
				} // only parse the first 50 notes
			}
		}

		// return array of notes
		return $notes;
	}

	/**
	 * Search and return all notes made by a person
	 *
	 * @param Person $profile
	 * @param String $username
	 *
	 * @return array of notes
	 * @throws Alert
	 * @author salvipascual
	 *
	 */
	private function getNotesByUsername($profile, $username): array
	{
		$user = Person::find($username);
		if (!$user) {
			return [];
		}

		// check if the person is blocked
		$blocks = Chats::isBlocked($profile->id, $user->id);
		if ($blocks->blocked || $blocks->blockedByMe) {
			return [];
		}

		// get the last 50 records from the db
		$listOfNotes = Database::query("
			SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.gender, B.country, B.avatar, B.avatarColor,
			(SELECT COUNT(note) FROM _pizarra_actions WHERE _pizarra_actions.note = A.id AND _pizarra_actions.id_person = '{$profile->id}' AND `action` = 'like') > 0 AS isliked,
			(SELECT COUNT(id) FROM _pizarra_comments WHERE _pizarra_comments.note = A.id) AS comments
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.id_person = B.id
			LEFT JOIN _pizarra_users C
			ON C.id_person = B.id
			WHERE A.active=1 AND B.id = '$user->id'
			ORDER BY inserted DESC
			LIMIT 20");

		// format the array of notes
		$notes = [];
		foreach ($listOfNotes as $note) {
			$notes[] = $this->formatNote($note, $profile->id);
		}

		// return array of notes
		return $notes;
	}

	/**
	 * Search notes by keyword
	 *
	 * @param Person $profile
	 * @param String $keyword
	 *
	 * @return array of notes
	 * @throws Exception
	 * @author salvipascual
	 *
	 */
	private function getNotesByKeyword($profile, $keyword): array
	{
		$temporaryTableName = 'temprelation_' . uniqid('', false);
		Database::query("CREATE TEMPORARY TABLE $temporaryTableName 
    			SELECT relations.user1, relations.user2 
				FROM relations 
				WHERE (user1 = {$profile->id} OR user2 = {$profile->id}) AND type = 'blocked' AND confirmed = 1;");

		// get the last 50 records from the db
		$listOfNotes = Database::query("
			SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.gender, B.country, B.online, B.avatar, B.avatarColor,
			(SELECT COUNT(note) FROM _pizarra_actions WHERE _pizarra_actions.note = A.id AND _pizarra_actions.id_person= '{$profile->id}' AND `action` = 'like') > 0 AS isliked,
			(SELECT count(id) FROM _pizarra_comments WHERE _pizarra_comments.note = A.id) as comments
			FROM _pizarra_notes A
			LEFT JOIN (
			    SELECT P.id, P.username, P.first_name, P.last_name, P.province, P.picture, 
			           P.gender, P.country, P.online, 
			           P.avatar, P.avatarColor
			    FROM person P LEFT JOIN $temporaryTableName ON $temporaryTableName.user1 = P.id OR $temporaryTableName.user2 = P.id
			    WHERE $temporaryTableName.user1 IS NULL AND $temporaryTableName.user2 IS NULL  			    
			) B
			ON A.id_person= B.id
			LEFT JOIN _pizarra_users C
			ON C.id_person = B.id
			WHERE A.active=1 AND A.text like '%$keyword%' 
			ORDER BY inserted DESC
			LIMIT 30");

		// format the array of notes
		$notes = [];
		foreach ($listOfNotes as $note) {
			$notes[] = $this->formatNote($note, $profile->email);
		}

		// return array of notes
		return $notes;
	}

	/**
	 * @param $profile
	 * @param bool $reputationRequired
	 * @return mixed
	 * @throws Alert
	 */
	private function preparePizarraUser($profile, $reputationRequired = true)
	{
		$profile = Person::find($profile->id);
		$myUser = Database::query("SELECT (SELECT SUM(amount) AS reputation FROM _pizarra_reputation WHERE id_person='{$profile->id}') AS reputation, avatar, avatarColor, default_topic AS topic FROM _pizarra_users WHERE id_person='{$profile->id}'");
		if (empty($myUser)) {
			// create the user in the table if do not exist
			Database::query("INSERT IGNORE INTO _pizarra_users (id_person) VALUES ('{$profile->id}')");
			$myUser = Database::query("SELECT reputation, avatar, avatarColor FROM _pizarra_users WHERE id_person='{$profile->id}'")[0];
		} else {
			$myUser = $myUser[0];
		}

		$myUser->id = $profile->id;
		$myUser->username = $profile->username;
		$myUser->gender = $profile->gender;
		if ($reputationRequired) {
			$myUser->reputation = floor(($myUser->reputation ?? 0) + $profile->completion);
		}

		$myUser->location = $profile->location;
		$myUser->avatar = $profile->avatar;
		$myUser->avatarColor = $profile->avatarColor;

		return $myUser;
	}

	/**
	 * Format note to be send to the view
	 *
	 * @param object $note
	 *
	 * @param $id
	 *
	 * @return array
	 * @throws \Exception
	 * @author salvipascual
	 */
	private function formatNote($note, $id): array
	{
		// get the location
		if (empty($note->province)) {
			$location = 'Cuba';
		} else {
			$location = ucwords(strtolower(str_replace('_', ' ', $note->province)));
		}

		// crate topics array
		$topics = [];
		if (isset($note->topic1) && $note->topic1) {
			$topics[] = $note->topic1;
		}
		if (isset($note->topic2) && $note->topic2) {
			$topics[] = $note->topic2;
		}
		if (isset($note->topic3) && $note->topic3) {
			$topics[] = $note->topic3;
		}

		if (isset($note->image) && $note->image) {
			$note->image .= '.jpg';
		} else {
			$note->image = false;
		}

		$avatar = empty($note->avatar) ? ($note->gender === 'M' ? 'hombre' : ($note->gender === 'F' ? 'sennorita' : 'hombre')) : $note->avatar;

		// get the country and flag
		$country = empty(trim($note->country)) ? 'cu' : strtolower($note->country);

		// remove \" and \' from the note
		$note->text = str_replace('\"', '"', $note->text);
		$note->text = str_replace("\'", "'", $note->text);

		$note->text = htmlentities($note->text);
		$note->text = str_replace("\n", '<br>', $note->text);

		while (json_encode($note->text, JSON_THROW_ON_ERROR, 512) === '') {
			$note->text = substr($note->text, 0, -2);
		}

		// add the text to the array
		return [
			'id' => $note->id,
			'id_person' => $note->id_person,
			'username' => $note->username,
			'location' => $location,
			'gender' => $note->gender,
			'text' => $note->text,
			'image' => $note->image,
			'inserted' => date_format((new DateTime($note->inserted)), 'j/n/y · g:ia'),
			'likes' => $note->likes ?? 0,
			'unlikes' => $note->unlikes ?? 0,
			'comments' => $note->comments ?? 0,
			'liked' => isset($note->isliked) && $note->isliked,
			'unliked' => isset($note->isunliked) && $note->isunliked,
			'ad' => $note->ad ?? false,
			'online' => $note->online ?? false,
			'country' => $country,
			'avatar' => $avatar,
			'avatarColor' => $note->avatarColor,
			'topics' => $topics,
			'canmodify' => $note->id_person === $id,
			'accept_comments' => (int) ($note->accept_comments ?? 1) == 1,
			'staff' => (int) ($note->staff ?? 0) == 1
		];
	}

	/**
	 * Find all mentions on a text
	 *
	 * @param String $text
	 *
	 * @return array, [username,email]
	 * @throws Alert
	 * @author salvipascual
	 *
	 */
	private function findUsersMentionedOnText($text): array
	{
		// find all users mentioned
		preg_match_all('/@\w*/', $text, $matches);

		// filter the ones that exist
		$return = [];
		if ($matches[0]) {
			// get string of possible matches
			$usernames = "'" . implode("','", $matches[0]) . "'";
			$usernames = str_replace('@', '', $usernames);
			$usernames = str_replace(",'',", ',', $usernames);
			$usernames = str_replace(",''", '', $usernames);
			$usernames = str_replace("'',", '', $usernames);

			// check real matches against the database
			$users = Database::query("SELECT id, email, username FROM person WHERE username in ($usernames)");

			// format the return
			foreach ($users as $user) {
				$object = new stdClass();
				$object->username = $user->username;
				$object->email = $user->email;
				$object->id = $user->id;
				$return[] = $object;
			}
		}

		return $return;
	}
}
