<?php

use Apretaste\Core;

class Service
{
	/**
	 * To list latest notes or post a new note
	 *
	 * @param Request  $request
	 * @param Response $response
	 *
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

		$pathToService = Utils::getPathToService($response->serviceName);
		$images = ["$pathToService/images/avatars.png"];
		$images[] = "$pathToService/images/img-prev.png";

		if (empty($notes)) {
			$content = [
				'header'     => 'Lo sentimos',
				'icon'       => 'sentiment_very_dissatisfied',
				'text'       => 'No encontramos notas que vayan con su búsqueda. Puede buscar por palabras, por @username o por #Tema.',
				'activeIcon' => 1,
				'myUser'     => $myUser
			];

			$response->setLayout('pizarra.ejs');
			$response->setTemplate('message.ejs', $content, $images);

			return;
		}

		// get most popular topics of last 7 days
		$popularTopics = q('
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
			'notes'               => $notes,
			'popularTopics'       => $topics,
			'title'               => $title,
			'num_notifications'   => $profile->notifications,
			'myUser'              => $myUser,
			'activeIcon'          => 1
		];

		// create the response
		$response->setLayout('pizarra.ejs');
		$response->SetTemplate('main.ejs', $content, $images);
	}

	/**
	 * The user likes a note
	 *
	 * @param Request  $request
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
			$noteId = q("SELECT MAX(id) AS id FROM $rowsTable WHERE id_person = '{$request->person->id}'")[0]->id;
		}
		// check if the user already liked this note
		$res = q("SELECT * FROM $actionsTable WHERE id_person={$request->person->id} AND $type='{$noteId}'");
		$note = q("SELECT id_person, `text` FROM $rowsTable WHERE id='{$noteId}'");

		if (empty($note)) {
			return;
		}

		if (!empty($res)) {
			if ($res[0]->action === 'unlike') {
				// delete previous vote and add new vote
				q("
				UPDATE $actionsTable SET `action`='like' WHERE id_person='{$request->person->id}' AND $type='{$noteId}';
				UPDATE $rowsTable SET likes=likes+1, unlikes=unlikes-1 WHERE id='{$noteId}'");
			}

			return;
		}

		// delete previos vote and add new vote
		Connection::query("
			INSERT INTO $actionsTable (id_person,$type,action) VALUES ('{$request->person->id}','{$noteId}','like');
			UPDATE $rowsTable SET likes=likes+1 WHERE id='{$noteId}'");

		$note = $note[0];
		$note->text = substr($note->text, 0, 30).'...';

		$this->addReputation($note->id_person, $request->person->id, $noteId, 0.3);

		// create notification for the creator
		if ($request->person->id != $note->id_person) {
			Utils::addNotification($note->id_person, "El usuario @{$request->person->username} le dio like a tu nota en la Pizarra: {$note->text}", "{'command':'PIZARRA NOTA', 'data':{'note':'{$noteId}'}}", 'thumb_up');
		}

		// complete the challenge
		Challenges::complete("like-pizarra-note", $request->person->id);
	}

	/**
	 * The user unlikes a note
	 *
	 * @param Request  $request
	 * @param Response $response
	 *
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
			$noteId = q("SELECT MAX(id) AS id FROM $rowsTable WHERE id_person = '{$request->person->id}'")[0]->id;
		}

		// check if the user already liked this note
		$res = q("SELECT * FROM $actionsTable WHERE id_person={$request->person->id} AND $type='{$noteId}'");
		$note = q("SELECT id_person, `text` FROM $rowsTable WHERE id='{$noteId}'");

		// do not continue if note do not exist
		if (empty($note)) {
			return;
		}

		// delete previos upvote and add new vote
		if (!empty($res)) {
			if ($res[0]->action === 'like') {
				q("
				UPDATE $actionsTable SET `action`='unlike' WHERE id_person='{$request->person->id}' AND $type='{$noteId}';
				UPDATE $rowsTable SET likes=likes-1, unlikes=unlikes+1 WHERE id='{$noteId}'");
			}
			return;
		}

		// delete previos vote and add new vote
		q("
			INSERT INTO $actionsTable (id_person,$type,action) VALUES ('{$request->person->id}','{$noteId}','unlike');
			UPDATE $rowsTable SET unlikes=unlikes+1 WHERE id='{$noteId}'");

		$note = $note[0];
		$this->addReputation($note->id_person, $request->person->id, $noteId, -0.3);

		// decrease the author's reputation
		Connection::query("UPDATE _pizarra_users SET reputation=reputation-1 WHERE id_person='{$note->id_person}'");

		// run powers for amulet VIDENTE
		if (Amulets::isActive(Amulets::VIDENTE, $note->id_person)) {
			$msg = "Los poderes del amuleto del Druida te avisan: A @{$request->person->username} le disgustó tu nota en Pizarra";
			Utils::addNotification($note->id_person, $msg, '{command:"PERFIL", data:{username:"@{$request->person->username}"}}', 'remove_red_eye');
		}
	}

	/**
	 * NOTA subservice
	 *
	 * @param Request  $request
	 * @param Response $response
	 *
	 * @author salvipascual
	 *
	 */
	public function _nota(Request $request, Response $response): void
	{
		$noteId = $request->input->data->note;
		if ($noteId === 'last') {
			$noteId = q("SELECT MAX(id) AS id FROM _pizarra_notes WHERE id_person = '{$request->person->id}'")[0]->id;
		}

		// get the records from the db
		$result = q("
			SELECT
				A.id, A.id_person, A.text, A.image, A.likes, A.unlikes, A.comments, A.inserted, A.ad, A.topic1, A.topic2, A.topic3,
				B.avatar, B.avatarColor, C.username, C.first_name, C.last_name, C.province, C.picture, C.gender, C.country, C.online,
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
				'origin'            => 'note',
				'num_notifications' => $request->person->notifications,
			]);

			return;
		}

		//check if the user is blocked by the owner of the note
		$blocks = Social::isBlocked($request->person->id, $note['id_person']);
		if ($blocks->blocked || $blocks->blockedByMe) {
			$content = [
				'username'          => $note['username'],
				'origin'            => 'note',
				'num_notifications' => $request->person->notifications,
				'blocks'            => $blocks,
			];
			$response->setTemplate('blocked.ejs', $content);

			return;
		}

		// get note comments
		$cmts = q("
			SELECT A.*, B.username, B.province, B.picture, B.gender, B.country, B.online, C.avatar, C.avatarColor,
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

		q("UPDATE _pizarra_notes SET views=views+1 WHERE id={$note['id']}");
		$this->addReputation($note['id_person'], $request->person->id, $noteId, 0.1);

		$myUser = $this->preparePizarraUser($request->person);

		$pathToService = Utils::getPathToService($response->serviceName);
		$images = ["$pathToService/images/avatars.png"];
		if ($note['image']) {
			$images[] = $note['image'];
		}

		$content = [
			'note'       => $note,
			'myUser'     => $myUser,
			'activeIcon' => 1
		];

		$response->setLayout('pizarra.ejs');
		$response->SetTemplate('note.ejs', $content, $images);
	}

	/**
	 * Post a new note to the public feed
	 *
	 * @author salvipascual
	 * @param Request  $request
	 * @param Response $response
	 */
	public function _escribir(Request $request, Response $response): void
	{
		$text = $request->input->data->text; // strip_tags
		$image = isset($request->input->data->image) ? $request->input->data->image : false;
		$fileName = '';
		$ad = 0;

		// get the image name and path
		if ($image) {
			$pizarraImgDir = Core::getRoot() . "/shared/img/content/pizarra";
			$fileName = Utils::generateRandomHash();
			$filePath = "$pizarraImgDir/$fileName.jpg";

			// save the optimized image on the user folder
			file_put_contents($filePath, base64_decode($image));
			Utils::optimizeImage($filePath);
		}

		// only post notes with real content
		if (strlen($text) < 20) {
			return;
		}

		// get all the topics from the post
		preg_match_all('/#\w*/', $text, $topics);
		$topics = empty($topics[0]) ? [q("SELECT default_topic FROM _pizarra_users WHERE id_person='{$request->person->id}'")[0]->default_topic] : $topics[0];
		$topic1 = isset($topics[0]) ? str_replace('#', '', $topics[0]) : '';
		$topic2 = isset($topics[1]) ? str_replace('#', '', $topics[1]) : '';
		$topic3 = isset($topics[2]) ? str_replace('#', '', $topics[2]) : '';

		// run powers for amulet PRIORIDAD
		if (Amulets::isActive(Amulets::PRIORIDAD, $request->person->id)) {
			// make the note into an ad
			$ad = 1;

			// alert the user
			$msg = "Los poderes del amuleto del druida harán que la nota que publicaste sea vista por muchas más personas";
			Utils::addNotification($request->person->id, $msg, '{command:"PIROPAZO PERFIL"}}', 'local_florist');
		}

		// save note to the database
		$cleanText = Connection::escape($text, 300, 'utf8mb4');
		$sql = "INSERT INTO _pizarra_notes (id_person, `text`, image, ad, topic1, topic2, topic3) VALUES ('{$request->person->id}', '$cleanText', '$fileName', $ad, '$topic1', '$topic2', '$topic3')";
		$noteID = Connection::query($sql, true, 'utf8mb4');

		// error if the note could not be inserted
		if (!is_numeric($noteID)) {
			throw new RuntimeException("PIZARRA: NoteID is null after INSERT. QUERY = $sql");
		}

		// complete the challenge
		Challenges::complete("write-pizarra-note", $request->person->id);

		// add the experience
		Level::setExperience('PIZARRA_POST_FIRST_DAILY', $request->person->id);

		// save the topics to the topics table
		foreach ($topics as $topic) {
			$topic = str_replace('#', '', $topic);
			$topic = Connection::escape($topic, 20, 'utf8mb4');
			Connection::query("INSERT INTO _pizarra_topics (topic, note, id_person) VALUES ('$topic', '$noteID', '{$request->person->id}')", true, 'utf8mb4');
		}

		// notify users mentioned
		$mentions = $this->findUsersMentionedOnText($text);
		$color = $request->person->gender === 'M' ? 'pizarra-color-text' : ($request->person->gender === 'F' ? 'pink-text' : 'black-text');
		foreach ($mentions as $m) {
			$blocks = Social::isBlocked($request->person->id, $m->id);
			if ($blocks->blocked > 0) {
				continue;
			}

			Utils::addNotification($m->id, "<span class=\"$color\">@{$request->person->username}</span> le ha mencionado", "{'command':'PIZARRA NOTA', 'data':{'note':'$noteID'}}", 'comment');
			$this->addReputation($m->id, $request->person->id, $noteID, 1);
		}
	}

	/**
	 * Avatar
	 *
	 * @param \Request  $request
	 * @param \Response $response
	 */
	public function _avatar(Request $request, Response $response): void
	{
		$pathToService = Utils::getPathToService($response->serviceName);
		$images = ["$pathToService/images/avatars.png"];

		$response->setLayout('pizarra.ejs');
		$response->setTemplate('avatar_select.ejs', [
			'myUser'     => $this->preparePizarraUser($request->person),
			'activeIcon' => 1
		], $images);
	}

	/**
	 * Post a new note to the public feed
	 *
	 * @param Request  $request
	 * @param Response $response
	 *
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
		$note = q("SELECT `text`,id_person FROM _pizarra_notes WHERE id='$noteId' AND active=1");
		if ($note) {
			$note = $note[0];
		} else {
			return;
		}

		$blocks = Social::isBlocked($request->person->id, $note->id_person);
		if ($blocks->blocked) {
			return;
		}

		// save the comment
		$comment = Connection::escape($comment, 200, 'utf8mb4');
		Connection::query(" INSERT INTO _pizarra_comments (id_person, note, text) VALUES ('{$request->person->id}', '$noteId', '$comment');
			UPDATE _pizarra_notes SET comments = comments+1 WHERE id='$noteId';", true, 'utf8mb4');

		// add the experience
		Level::setExperience('PIZARRA_COMMENT_FIRST_DAILY', $request->person->id);

		// complete the challenge
		Challenges::complete("comment-pizarra-note", $request->person->id);

		// notify users mentioned
		$mentions = $this->findUsersMentionedOnText($comment);
		foreach ($mentions as $mention) {
			$blocks = Social::isBlocked($request->person->id, $mention->id);
			if ($blocks->blocked || $blocks->blockedByMe) {
				continue;
			}
			Utils::addNotification($mention->id, "El usuario @{$request->person->username} le ha mencionado en la pizarra", "{'command':'PIZARRA NOTA', 'data':{'note':'$noteId'}}", 'comment');
			$this->addReputation($mention->id, $request->person->id, $noteId, 1);
		}

		// send a notification to the owner of the note
		$color = $request->person->gender === 'M' ? 'pizarra-color-text' : ($request->person->gender === 'F' ? 'pink-text' : 'black-text');
		if ($request->person->id !== $note->id_person) {
			Utils::addNotification($note->id_person, "<span class=\"$color\">@{$request->person->username}</span> ha comentado tu publicación", "{'command':'PIZARRA NOTA', 'data':{'note':'$noteId'}}", 'comment');
			$this->addReputation($note->id_person, $request->person->id, $noteId, 0.6);
		}
	}

	/**
	 * Show extensive list of topics as a web cloud
	 *
	 * @param Request  $request
	 * @param Response $response
	 *
	 * @throws Exception
	 * @author salvipascual
	 *
	 */
	public function _populares(Request $request, Response $response): void
	{
		$cacheFile = Utils::getTempDir()."/pizarra_populars.tmp";
		if (file_exists($cacheFile) && time() < filemtime($cacheFile) + 15*60) {
			$cache = json_decode(file_get_contents($cacheFile));
			$topics = $cache->topics;
			$populars = $cache->populars;
		} else {
			// get list of topics
			$ts = q("
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
			$populars =
				q('SELECT A.id_person, A.avatar, A.avatarColor, B.username, B.first_name, B.country, B.province, B.about_me,  B.gender, B.year_of_birth, B.highest_school_level, B.online, (SELECT SUM(amount) FROM _pizarra_reputation WHERE id_person = A.id_person) AS reputation FROM _pizarra_users A JOIN person B ON A.id_person = B.id ORDER BY reputation DESC LIMIT 10');
			foreach ($populars as $popular) {
				$popular->avatar = $request->person->avatar;
				//$popular->avatar = empty($popular->avatar) ? ($popular->gender === 'M' ? 'Hombre' : ($popular->gender === 'F' ? 'Señorita' : 'Hombre')) : $popular->avatar;
				$popular->reputation = floor(($popular->reputation ?? 0) + $this->profileCompletion($popular));
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

		$pathToService = Utils::getPathToService($response->serviceName);
		$images = ["$pathToService/images/avatars.png"];

		$response->setLayout('pizarra.ejs');
		$response->SetTemplate('populars.ejs', [
			'topics'     => $topics,
			'populars'   => $populars,
			'myUser'     => $myUser,
			'activeIcon' => 2
		], $images);
	}

	/**
	 * Show a list of notifications
	 *
	 * @param Request
	 * @param Response
	 *
	 * @author salvipascual
	 */
	public function _notificaciones(Request $request, Response $response): ?\Response
	{
		// get all unread notifications
		$notifications = q("
			SELECT id,icon,`text`,link,inserted
			FROM notification
			WHERE `to` = {$request->person->id} 
			AND service = 'pizarra'
			AND `hidden` = 0
			ORDER BY inserted DESC");

		$pathToService = Utils::getPathToService($response->serviceName);
		$images = ["$pathToService/images/avatars.png"];

		// if no notifications, let the user know
		if (empty($notifications)) {
			$content = [
				'header'     => 'Nada por leer',
				'icon'       => 'notifications_off',
				'text'       => 'Por ahora usted no tiene ninguna notificación por leer.',
				'activeIcon' => 3,
				'myUser'     => $this->preparePizarraUser($request->person)
			];

			$response->setLayout('pizarra.ejs');

			return $response->setTemplate('message.ejs', $content, $images);
		}

		foreach ($notifications as $noti) {
			$noti->inserted = strtoupper(date('d/m/Y h:ia', strtotime(($noti->inserted))));
		}

		// prepare content for the view
		$content = [
			'notifications' => $notifications,
			'title'         => 'Notificaciones',
			'activeIcon'    => 3,
			'myUser'        => $this->preparePizarraUser($request->person)
		];

		// build the response
		$response->setLayout('pizarra.ejs');
		$response->setTemplate('notifications.ejs', $content, $images);
	}

	/**
	 * Show the user profile
	 *
	 * @param Request  $request
	 * @param Response $response
	 *
	 * @throws Exception
	 * @author salvipascual
	 *
	 */
	public function _perfil(Request $request, Response $response)
	{
		$myUser = $this->preparePizarraUser($request->person);
		$pathToService = Utils::getPathToService($response->serviceName);
		$images = ["$pathToService/images/avatars.png"];

		if (isset($request->input->data->username) && $request->input->data->username != $request->person->username) {
			$username = $request->input->data->username;
			// get the user's profile
			$person = Utils::getPerson($username);

			// if user do not exist, message the requestor
			if (empty($person)) {
				$response->setLayout('pizarra.ejs');
				$response->setTemplate('notFound.ejs', [
					'origin'     => 'profile',
					'myUser'     => $myUser,
					'activeIcon' => 1
				], $images);

				return;
			}

			//check if the user is blocked
			$blocks = Social::isBlocked($request->person->id, $person->id);

			if ($blocks->blocked || $blocks->blockedByMe) {
				$content = [
					'username'          => $person->username,
					'origin'            => 'profile',
					'num_notifications' => $request->person->notifications,
					'blocks'            => $blocks,
					'myUser'            => $myUser,
					'activeIcon'        => 1
				];
				$response->SetTemplate('blocked.ejs', $content, $images);

				return;
			}

			$person = Social::prepareUserProfile($person);

			// run powers for amulet DETECTIVE
			if (Amulets::isActive(Amulets::DETECTIVE, $person->id)) {
				$msg = "Los poderes del amuleto del Druida te avisan: @{$request->person->username} está revisando tu perfil";
				Utils::addNotification($person->id, $msg, '{command:"PERFIL", data:{username:"@{$request->person->username}"}}', 'pageview');
			}

			// run powers for amulet SHADOWMODE
			if (Amulets::isActive(Amulets::SHADOWMODE, $person->id)) {
				return $response->setTemplate("message.ejs", [
					"header" => "Shadow-Mode",
					"icon" => "visibility_off",
					"text" => "La magia oscura de un amuleto rodea este perfil y te impide verlo. Por mucho que intentes romperlo, el hechizo del druida es poderoso."
				]);
			}
		} else {
			if (isset($request->input->data->avatar)) {
				Connection::query("UPDATE _pizarra_users SET avatar = '{$request->input->data->avatar}', avatarColor='{$request->input->data->color}' WHERE id_person={$request->person->id}");
				Connection::query("UPDATE person SET avatar = '{$request->input->data->avatar}', avatarColor='{$request->input->data->color}' WHERE id={$request->person->id}");
				$myUser->avatar = $request->input->data->avatar;
				$myUser->avatarColor = $request->input->data->color;
			}
			$person = $request->person;
		}

		$user = $this->preparePizarraUser($person);
		//$person->avatar = $user->avatar;
		//$person->avatarColor = $user->avatarColor;
		$person->reputation = $user->reputation;

		// create data for the view
		$content = [
			'profile'    => $person,
			'myUser'     => $myUser,
			'activeIcon' => 1
		];

		if ($person->id == $request->person->id) {
			$response->setLayout('pizarra.ejs');
			$response->SetTemplate('ownProfile.ejs', $content, $images);
		} else {
			$this->getTags($person);

			$response->setLayout('pizarra.ejs');
			$response->SetTemplate('profile.ejs', $content, $images);
		}
	}

	/**
	 * Chats lists with matches filter
	 *
	 * @param Request
	 * @param Response
	 *
	 * @author ricardo
	 */

	public function _chat(Request $request, Response $response): void
	{
		// get the list of people chating with you
		$chats = Social::chatsOpen($request->person->id);

		$myUser = $this->preparePizarraUser($request->person);
		$pathToService = Utils::getPathToService($response->serviceName);
		$images = ["$pathToService/images/avatars.png"];

		// if no matches, let the user know
		if (empty($chats)) {
			$content = [
				'header'     => 'No tiene conversaciones',
				'icon'       => 'sentiment_very_dissatisfied',
				'text'       => 'Aún no ha hablado con nadie.',
				'title'      => 'chats',
				'myUser'     => $myUser,
				'activeIcon' => 1
			];

			$response->setLayout('pizarra.ejs');
			$response->setTemplate('message.ejs', $content, $images);

			return;
		}

		foreach ($chats as $chat) {
			$user = $this->preparePizarraUser($chat, false);
			$chat->last_sent = explode(' ', $chat->last_sent)[0];
			//$chat->avatar = $user->avatar;
			//$chat->avatarColor = $user->avatarColor;
			unset($chat->picture);
			unset($chat->first_name);
		}

		$content = [
			'chats'      => $chats,
			'myUser'     => $myUser,
			'activeIcon' => 1
		];

		$response->setLayout('pizarra.ejs');
		$response->setTemplate('chats.ejs', $content, $images);
	}

	public function _conversacion(Request $request, Response $response): void
	{
		// get the username of the note
		$user = Utils::getPerson($request->input->data->userId);

		// check if the username is valid
		if (!$user) {
			$myUser = $this->preparePizarraUser($request->person);
			$pathToService = Utils::getPathToService($response->serviceName);
			$images = ["$pathToService/images/{$user->avatar}.png"];

			$response->setTemplate('notFound.ejs', ['myUser' => $myUser], $images);

			return;
		}

		$messages = Social::chatConversation($request->person->id, $user->id);
		$chats = [];

		foreach ($messages as $message) {
			$chat = new stdClass();
			$chat->id = $message->note_id;
			$chat->username = $message->username;
			$chat->text = $message->text;
			$chat->sent = date_format((new DateTime($message->sent)), 'd/m/Y h:ia');
			$chat->read = date('d/m/Y h:ia', strtotime($message->read));
			$chat->readed = $message->readed;
			$chats[] = $chat;
		}

		$pathToService = Utils::getPathToService($response->serviceName);
		$images = ["$pathToService/images/avatars.png"];

		$content = [
			'messages'   => $chats,
			'username'   => $user->username,
			'myusername' => $request->person->username,
			'id'         => $user->id,
			'online'     => $user->online,
			'last'       => date('d/m/Y h:ia', strtotime($user->last_access)),
			'title'      => $user->first_name,
			'myUser'     => $this->preparePizarraUser($user)
		];

		$response->setlayout('pizarra.ejs');
		$response->setTemplate('conversation.ejs', $content, $images);
	}

	/**
	 *
	 * @param Request
	 * @param Response
	 *
	 * @author salvipascual
	 */
	public function _mensaje(Request $request, Response $response): void
	{
		if (!isset($request->input->data->id)) {
			return;
		}
		$userTo = Utils::getPerson($request->input->data->id);
		if (!$userTo) {
			return;
		}
		$message = $request->input->data->message;

		$blocks = Social::isBlocked($request->person->id, $userTo->id);
		if ($blocks->blocked > 0 || $blocks->blockedByMe > 0) {
			Utils::addNotification(
				$request->person->id,
				"Su mensaje para @{$userTo->username} no pudo ser entregado, es posible que usted haya sido bloqueado por esa persona.",
				'{}',
				'error'
			);

			return;
		}

		// store the note in the database
		$message = Connection::escape($message, 499, 'utf8mb4');
		q("INSERT INTO _note (from_user, to_user, `text`) VALUES ({$request->person->id},{$userTo->id},'$message')", true, 'utf8mb4');

		$color = $request->person->gender === 'M' ? 'pizarra-color-text' : ($request->person->gender === 'F' ? 'pink-text' : 'black-text');

		// send notification for the app
		Utils::addNotification(
			$userTo->id,
			"<span class=\"$color\">@{$request->person->username}</span> le ha enviado un mensaje",
			"{'command':'PIZARRA CONVERSACION', 'data':{'userId':'{$request->person->id}'}}",
			'message'
		);
	}

	/**
	 * Assign a topic to a note
	 *
	 * @param Request  $request
	 * @param Response $response
	 *
	 * @author salvipascual
	 *
	 */
	public function _temificar(Request $request, Response $response): void
	{
		// get note ID and topic
		$noteId = $request->input->data->note;
		$topic = $request->input->data->theme;

		// get the note to update
		$note = q("SELECT topic1,topic2,topic3 FROM _pizarra_notes WHERE id='$noteId' AND id_person='{$request->person->id}' AND active=1");

		if ($note && $topic) {
			// save topic in the database
			$topic = Connection::escape($topic, 20);
			if (empty($note[0]->topic1)) {
				$topicToSave = "topic1='$topic'";
			} elseif (empty($note[0]->topic2)) {
				$topicToSave = "topic2='$topic'";
			} else {
				$topicToSave = "topic3='$topic'";
			}
			q("
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
		q(
			"UPDATE _pizarra_notes SET active=0 
			WHERE id='$noteId' AND id_person='{$request->person->id}'"
		);
	}

	/**
	 * Display the help document
	 *
	 * @param Request  $request
	 * @param Response $response
	 *
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
		if ($toId != $fromId) {
			q("INSERT INTO _pizarra_reputation(id_person, id_from, id_note, amount) VALUES ($toId, $fromId, $noteId, $amount)");
		}
	}

	/**
	 * Search what type of search the user is doing
	 *
	 * @param String $search
	 *
	 * @return Array ["type", "value"]
	 * @author salvipascual
	 *
	 */
	private function getSearchType($keyword): ?array
	{
		// return topic selected by the user if blank
		if (empty($keyword)) {
			return ['topic', 'general'];
			/*$topic = q("SELECT default_topic FROM _pizarra_users WHERE id_person='$id'");
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
		$topicExists = q("SELECT id FROM _pizarra_topics WHERE topic='$topicNoHashSymbol'");
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
	 * @param Profile $profile
	 * @param String  $topic
	 *
	 * @return Array of notes
	 * @author salvipascual
	 *
	 */
	private function getNotesByTopic($profile, $topic): array
	{
		$where = $topic !== 'general' ? "WHERE (topic1='$topic' OR topic2='$topic' OR topic3='$topic') AND active=1" : 'WHERE active=1';
		// set the topic as default for the user

		q("UPDATE _pizarra_users SET default_topic='$topic' WHERE id_person='{$profile->id}'");

		// get the records from the db
		$listOfNotes = q("
			SELECT
				A.id, A.id_person, A.text, A.image, A.likes, A.unlikes, A.comments, A.inserted, A.ad, A.topic1, A.topic2, A.topic3,
				B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.country, B.online,
				C.reputation, C.avatar, C.avatarColor, 
				TIMESTAMPDIFF(HOUR,A.inserted,CURRENT_DATE) as hours,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE note=A.id AND A.id_person='{$profile->id}' AND action='like') > 0 AS isliked,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE note=A.id AND A.id_person='{$profile->id}' AND action='unlike') > 0 AS isunliked
			FROM (
				SELECT * FROM _pizarra_notes subq2 INNER JOIN (
					SELECT max(id) as idx FROM _pizarra_notes
					$where
					GROUP BY id_person
					) subq
				ON subq.idx = subq2.id
				ORDER BY inserted DESC
				LIMIT 500
			) A
			LEFT JOIN person B ON A.id_person = B.id 
			JOIN _pizarra_users C ON A.id_person = C.id_person
			WHERE 
			NOT EXISTS (SELECT * FROM relations 
						WHERE `type` = 'blocked' AND confirmed=1 AND 
						 ((relations.user1 = A.id_person AND relations.user2 = '{$profile->id}') 
						  OR (relations.user2 = A.id_person AND relations.user1 = '{$profile->id}'))
						LIMIT 0,1);");

		// sort results by weight. Too complex and slow in MySQL
		usort($listOfNotes, function ($a, $b) {
			$a->score = 100 - $a->hours + $a->comments * 0.2 + ($a->likes - $a->unlikes * 2) + $a->ad * 1000;
			$b->score = 100 - $b->hours + $b->comments * 0.2 + ($b->likes - $b->unlikes * 2) + $b->ad * 1000;

			return ($b->score - $a->score) ? ($b->score - $a->score) / abs($b->score - $a->score) : 0;
		});

		// format the array of notes
		$notes = [];
		foreach ($listOfNotes as $note) {
			$notes[] = $this->formatNote($note, $profile->id); // format the array of notes
			if (count($notes) > 50) {
				break;
			} // only parse the first 50 notes
		}

		// return array of notes
		return $notes;
	}

	/**
	 * Search and return all notes made by a person
	 *
	 * @param Profile $profile
	 * @param String  $username
	 *
	 * @return Array of notes
	 * @author salvipascual
	 *
	 */
	private function getNotesByUsername($profile, $username): array
	{
		$user = Utils::getPerson($username);
		if (!$user) {
			return [];
		}

		// check if the person is blocked
		$blocks = Social::isBlocked($profile->id, $user->id);
		if ($blocks->blocked || $blocks->blockedByMe) {
			return [];
		}

		// get the last 50 records from the db
		$listOfNotes = q("
			SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.gender, B.country, C.avatar, C.avatarColor,
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
	 * @param Profile $profile
	 * @param String  $keyword
	 *
	 * @return Array of notes
	 * @author salvipascual
	 *
	 */
	private function getNotesByKeyword($profile, $keyword): array
	{
		// get the last 50 records from the db
		$listOfNotes = q("
			SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.gender, B.country, B.online, C.avatar, C.avatarColor,
			(SELECT COUNT(note) FROM _pizarra_actions WHERE _pizarra_actions.note = A.id AND _pizarra_actions.id_person= '{$profile->id}' AND `action` = 'like') > 0 AS isliked,
			(SELECT count(id) FROM _pizarra_comments WHERE _pizarra_comments.note = A.id) as comments
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.id_person= B.id
			LEFT JOIN _pizarra_users C
			ON C.id_person = B.id
			WHERE A.active=1 AND A.text like '%$keyword%' AND 
			NOT EXISTS (SELECT * FROM relations 
						WHERE `type` = 'blocked' AND confirmed=1 AND 
						 ((relations.user1 = A.id_person AND relations.user2 = '{$profile->id}') 
						  OR (relations.user2 = A.id_person AND relations.user1 = '{$profile->id}'))
						LIMIT 0,1)
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

	private function preparePizarraUser($profile, $reputationRequired = true)
	{
		$myUser = q("SELECT (SELECT SUM(amount) AS reputation FROM _pizarra_reputation WHERE id_person='{$profile->id}') AS reputation, avatar, avatarColor, default_topic AS topic FROM _pizarra_users WHERE id_person='{$profile->id}'");
		if (empty($myUser)) {
			// create the user in the table if do not exist
			q("INSERT IGNORE INTO _pizarra_users (id_person) VALUES ('{$profile->id}')");
			$myUser = q("SELECT reputation, avatar, avatarColor FROM _pizarra_users WHERE id_person='{$profile->id}'")[0];
		} else {
			$myUser = $myUser[0];
		}

		$myUser->id = $profile->id;
		$myUser->username = $profile->username;
		$myUser->gender = $profile->gender;
		if ($reputationRequired) {
			$myUser->reputation = floor(($myUser->reputation ?? 0) + $this->profileCompletion($profile));
		}

		$myUser->location = empty($profile->province) ? 'Cuba' : ucwords(strtolower(str_replace('_', ' ', $profile->province)));
		$myUser->avatar = $profile->avatar;
		$myUser->avatarColor = $profile->avatarColor;
		//$myUser->avatar = empty($myUser->avatar) ? ($myUser->gender === 'M' ? 'Hombre' : ($myUser->gender === 'F' ? 'Señorita' : 'Hombre')) : $myUser->avatar;

		return $myUser;
	}

	private function profileCompletion($profile): int
	{
		$total = 0;
		$total += $profile->first_name ? 15 : 0;
		$total += $profile->year_of_birth ? 15 : 0;
		$total += $profile->highest_school_level ? 10 : 0;
		$total += $profile->country ? 15 : 0;
		$total += $profile->province ? 15 : 0;
		$total += $profile->gender ? 10 : 0;
		$total += !$profile->about_me || (isset($profile->noDescription) && $profile->noDescription) ? 0 : 20;

		return $total;
	}

	/**
	 * Format note to be send to the view
	 *
	 * @param Object $note
	 *
	 * @return Array
	 * @throws Exception
	 * @author salvipascual
	 *
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
			$pizarraImgDir = Core::getRoot() . "/shared/img/content/pizarra";
			$note->image = "$pizarraImgDir/{$note->image}.jpg";
		} else {
			$note->image = false;
		}

		$avatar = empty($note->avatar) ? ($note->gender === 'M' ? 'Hombre' : ($note->gender === 'F' ? 'Señorita' : 'Hombre')) : $note->avatar;

		// get the country and flag
		$country = empty(trim($note->country)) ? 'cu' : strtolower($note->country);

		// remove \" and \' from the note
		$note->text = str_replace('\"', '"', $note->text);
		$note->text = str_replace("\'", "'", $note->text);

		$note->text = htmlentities($note->text);
		$note->text = str_replace("\n", '<br>', $note->text);

		while (json_encode($note->text) == '') {
			$note->text = substr($note->text, 0, strlen($note->text) - 2);
		}

		// add the text to the array
		$newNote = [
			'id'          => $note->id,
			'id_person'   => $note->id_person,
			'username'    => $note->username,
			'location'    => $location,
			'gender'      => $note->gender,
			'text'        => $note->text,
			'image'       => $note->image,
			'inserted'    => date_format((new DateTime($note->inserted)), 'j/n/y · g:ia'),
			'likes'       => isset($note->likes) ? $note->likes : 0,
			'unlikes'     => isset($note->unlikes) ? $note->unlikes : 0,
			'comments'    => isset($note->comments) ? $note->comments : 0,
			'liked'       => isset($note->isliked) && $note->isliked,
			'unliked'     => isset($note->isunliked) && $note->isunliked,
			'ad'          => isset($note->ad) ? $note->ad : false,
			'online'      => isset($note->online) ? $note->online : false,
			'country'     => $country,
			'avatar'      => $avatar,
			'avatarColor' => $note->avatarColor,
			'topics'      => $topics,
			'canmodify'   => $note->id_person == $id,
		];

		return $newNote;
	}

	/**
	 * Find all mentions on a text
	 *
	 * @param String $text
	 *
	 * @return Array, [username,email]
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
			$usernames = "'".implode("','", $matches[0])."'";
			$usernames = str_replace('@', '', $usernames);
			$usernames = str_replace(",'',", ',', $usernames);
			$usernames = str_replace(",''", '', $usernames);
			$usernames = str_replace("'',", '', $usernames);

			// check real matches against the database
			$users = q("SELECT id, email, username FROM person WHERE username in ($usernames)");

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

	private function getTags(&$profile): void
	{
		$profileTags = [];
		$professionTags = [];

		$genderLetter = $profile->gender === 'M' ? 'o' : 'a';

		$profileTags[] = $profile->gender === 'M' ? 'Hombre' : 'Mujer';
		$profileTags[] = $profile->age.' años';
		if ($profile->religion && $profile->religion !== 'OTRA') {
			$profileTags[] = substr(strtolower($profile->religion), 0, -1).$genderLetter;
		}

		$countries = [
			'cu'   => 'Cuba',
			'us'   => 'Estados Unidos',
			'es'   => 'Espana',
			'it'   => 'Italia',
			'mx'   => 'Mexico',
			'br'   => 'Brasil',
			'ec'   => 'Ecuador',
			'ca'   => 'Canada',
			'vz'   => 'Venezuela',
			'al'   => 'Alemania',
			'co'   => 'Colombia',
			'OTRO' => 'Otro'
		];

		$profile->country = $countries[$profile->country];

		if ($profile->highest_school_level !== 'OTRO') {
			$professionTags[] = ucfirst(strtolower($profile->highest_school_level));
		}
		$professionTags[] = $profile->occupation;

		$profile->profile_tags = implode(', ', $profileTags);
		$profile->profession_tags = implode(', ', $professionTags);
	}
}
