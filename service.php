<?php

use Apretaste\Chats;
use Apretaste\Level;
use Apretaste\Person;
use Apretaste\Amulets;
use Apretaste\Request;
use Apretaste\Response;
use Apretaste\Tutorial;
use Apretaste\Challenges;
use Apretaste\Notifications;
use Framework\Core;
use Framework\Utils;
use Framework\Alert;
use Framework\Images;
use Framework\Database;
use Framework\GoogleAnalytics;
use Apretaste\Influencers;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;

class Service
{
	public $insertedNoteId = null;

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
		$page = $request->input->data->page ?? 1;
		$notes = $this->getNotesByFriends($request->person, $page);
		$pages = count($notes) >= 20 || $page > 1 ? $this->getPagesByFriends($request->person) : 1;

		$myUser = $this->preparePizarraUser($request->person);

		$images = [];

		if ($request->person->showImages) {
			for ($i = 0; $i < count($notes); $i++) {
				if ($notes[$i]['image']) {
					$pizarraImgDir = SHARED_PUBLIC_PATH . '/content/pizarra';
					$imgPath = "$pizarraImgDir/{$notes[$i]['image']}";

					$optimized = Images::optimize($imgPath); // This is cached so no worries
					if (filesize($optimized) > 50000) {
						array_splice($notes, $i, 1);
					} else {
						$images[] = $imgPath;
					}
				}
			}
		}

		$this->updateImpressions($notes);

		$popularTopics = $this->getPopularTopics();
		$popularTopics = array_splice($popularTopics, 0, 4);

		$myPopularTopics = Database::query("
			SELECT topic AS name, COUNT(id) AS cnt FROM _pizarra_topics
			WHERE created > DATE_ADD(NOW(), INTERVAL -30 DAY)
			AND topic <> 'general' AND id_person='{$request->person->id}'
			GROUP BY topic ORDER BY cnt DESC LIMIT 4");

		// create variables for the template
		$content = [
			'notes' => $notes,
			'myUser' => $myUser,
			'title' => 'Muro',
			'showImages' => $request->person->showImages,
			'popularTopics' => $popularTopics,
			'myPopularTopics' => $myPopularTopics,
			'page' => $page,
			'pages' => $pages ?? 1,
		];

		// create the response
		$response->setCache(60);
		$response->setLayout('pizarra.ejs');
		$response->SetTemplate('main.ejs', $content, $images);
	}

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
	public function _global(Request $request, Response $response): void
	{
		$page = $request->input->data->page ?? 1;

		// get the type of search
		$keyword = $request->input->data->search ?? null;

		// prepare my user
		$myUser = $this->preparePizarraUser($request->person);

		if ($keyword != null && gettype($keyword) != 'string') {
			$keyword = null;
		}

		$search = $this->getSearchType($keyword);
		[$searchType, $searchValue] = $search;

		// get the user's profile
		$profile = $request->person;

		// get notes if searched by topic
		if ($searchType === 'topic') {
			$notes = $this->getNotesByTopic($profile, $searchValue, $keyword != null, $page);
			$pages = count($notes) >= 20 || $page > 1 ? $this->getPagesByTopic($profile, $searchValue) : 1;
		}

		// get notes if searched by username
		if ($searchType === 'username') {
			$notes = $this->getNotesByUsername($profile, $searchValue, $page);
			$pages = count($notes) >= 20 || $page > 1 ? $this->getPagesByUsername($searchValue) : 1;
		}

		// get notes if searched by keyword
		if ($searchType === 'keyword') {
			$notes = $this->getNotesByKeyword($profile, $searchValue, $page);
			$pages = count($notes) >= 20 || $page > 1 ? $this->getPagesByKeyword($profile, $searchValue) : 1;
		}

		$images = [];

		if ($request->person->showImages) {
			foreach ($notes as $note) {
				if ($note['image']) {
					$pizarraImgDir = SHARED_PUBLIC_PATH . '/content/pizarra';
					$images[] = "$pizarraImgDir/{$note['image']}";
				}
			}
		}

		$this->updateImpressions($notes);

		$popularTopics = $this->getPopularTopics();
		array_splice($popularTopics, 0, 4);

		$myPopularTopics = Database::query("
			SELECT topic AS name, COUNT(id) AS cnt FROM _pizarra_topics
			WHERE created > DATE_ADD(NOW(), INTERVAL -30 DAY)
			AND topic <> 'general' AND id_person='{$request->person->id}'
			GROUP BY topic ORDER BY cnt DESC LIMIT 4");

		// create variables for the template
		$content = [
			'notes' => $notes,
			'myUser' => $myUser,
			'title' => 'Global',
			'search' => $keyword,
			'showImages' => $request->person->showImages,
			'popularTopics' => $popularTopics,
			'myPopularTopics' => $myPopularTopics,
			'page' => $page,
			'pages' => $pages ?? 1,
		];

		// create the response
		if (!$search) {
			$response->setCache(60);
		} else {
			$response->setCache(30);
		}
		$response->setLayout('pizarra.ejs');
		$response->SetTemplate('main.ejs', $content, $images);
	}

	/**
	 * The user likes a note
	 *
	 * @param Request $request
	 * @param Response $response
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
		$note = Database::query("SELECT id_person, `text`, likes FROM $rowsTable WHERE id='{$noteId}'");

		if (!empty($note)) {
			$note = $note[0];
			$liked = false;

			$ownlike = 0;
			if (intval($note->id_person) == intval($request->person->id)) {
				$ownlike = 1;
			}

			if (!empty($res)) {
				if ($res[0]->action === 'unlike') {
					// delete previous vote and add new vote
					Database::query("
						UPDATE $actionsTable SET `action`='like' WHERE id_person='{$request->person->id}' AND $type='{$noteId}';
						UPDATE $rowsTable SET likes=likes+1, unlikes=unlikes-1,ownlike=$ownlike WHERE id='{$noteId}'");

					// update influencers stats
					Influencers::incStat($note->id_person, 'likes');
					Influencers::decStat($note->id_person, 'unlikes');

					// create notification for the creator
					if ($request->person->id != $note->id_person) {
						Notifications::alert($note->id_person, "El usuario @{$request->person->username} le dio like a tu nota en la Pizarra: {$note->text}", 'thumb_up', "{'command':'PIZARRA NOTA', 'data':{'note':'{$noteId}'}}");
					}

					$liked = true;
				}
			}

			if (!$liked) {
				// add new vote
				$id = Database::query("
					INSERT INTO $actionsTable (id_person,$type,action) VALUES ('{$request->person->id}','{$noteId}','like');    
					UPDATE $rowsTable SET likes=likes+1, ownlike = $ownlike WHERE id='{$noteId}'");

				$note->text = substr($note->text, 0, 30) . '...';

				// update influencers stats
				Influencers::incStat($note->id_person, 'likes');

				$this->addReputation($note->id_person, $request->person->id, $noteId, 0.3);

				// create notification for the creator
				if ($request->person->id != $note->id_person) {
					Notifications::alert($note->id_person, "El usuario @{$request->person->username} le dio like a tu nota en la Pizarra: {$note->text}", 'thumb_up', "{'command':'PIZARRA NOTA', 'data':{'note':'{$noteId}'}}");
				}

				// submit to Google Analytics 
				if ($type === 'note') {
					GoogleAnalytics::event('note_like', $noteId);
				}

				// complete the challenge
				Challenges::complete('like-pizarra-note', $request->person->id);

			}
		}
	}

	/**
	 * The user unlikes a note
	 *
	 * @param Request $request
	 * @param Response $response
	 * @author salvipascual
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

		$note = $note[0];

		// delete previos upvote and add new vote
		if (!empty($res)) {
			if ($res[0]->action === 'like') {
				Database::query("
				UPDATE $actionsTable SET `action`='unlike' WHERE id_person='{$request->person->id}' AND $type='{$noteId}';
				UPDATE $rowsTable SET likes=likes-1, unlikes=unlikes+1 WHERE id='{$noteId}'");

				// update influencers stats
				Influencers::incStat($note->id_person, 'unlikes');
				Influencers::decStat($note->id_person, 'likes');
			}
			return;
		}

		// delete previos vote and add new vote
		Database::query("
			INSERT INTO $actionsTable (id_person,$type,action) VALUES ('{$request->person->id}','{$noteId}','unlike');
			UPDATE $rowsTable SET unlikes=unlikes+1 WHERE id='{$noteId}'");

		$this->addReputation($note->id_person, $request->person->id, $noteId, -0.3);

		// update influencers stats
		Influencers::incStat($note->id_person, 'unlikes');

		// submit to Google Analytics 
		if ($type === 'note') {
			GoogleAnalytics::event('note_dislike', $noteId);
		}

		// decrease the author's reputation
		Database::query("UPDATE _pizarra_users SET reputation=reputation-1 WHERE id_person='{$note->id_person}'");

		// run powers for amulet VIDENTE
		if (Amulets::isActive(Amulets::VIDENTE, $note->id_person) && $type === 'note') {
			$msg = "Los poderes del amuleto del Druida te avisan: A @{$request->person->username} le disgustó tu nota en Pizarra";
			Notifications::alert($note->id_person, $msg, 'remove_red_eye', "{'command':'PIZARRA NOTA', 'data':{'note':'{$request->input->data->note}'}}");
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
				A.id, A.id_person, A.text, A.image, A.likes, A.unlikes, A.comments, A.inserted, A.ad, A.topic1, A.topic2, A.topic3, A.article,  
				A.accept_comments, A.link_text, A.link_icon, A.link_command, B.reputation, C.avatar, C.avatarColor, 
				C.username, C.first_name, C.last_name, C.province, C.picture, C.gender, C.country, C.online, C.is_influencer,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE note=A.id AND id_person='{$request->person->id}' AND action='like') > 0 AS isliked,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE note=A.id AND id_person='{$request->person->id}' AND action='unlike') > 0 AS isunliked
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
			SELECT A.*, B.username, B.province, B.picture, B.gender, B.country, B.online, B.avatar, B.avatarColor, B.is_influencer, 
			(SELECT COUNT(comment) FROM _pizarra_comments_actions WHERE comment=A.id AND id_person='{$request->person->id}' AND action='like') > 0 AS isliked,
			(SELECT COUNT(comment) FROM _pizarra_comments_actions WHERE comment=A.id AND id_person='{$request->person->id}' AND action='unlike') > 0 AS isunliked
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

		// update influencers stats
		Influencers::incStat($note['id_person'], 'views');

		$response->setCache(60);
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
		$text = strip_tags($request->input->data->text); // strip_tags
		$image = $request->input->data->image ?? false;
		$imageName = $request->input->data->imageName ?? false;
		$fileName = '';

		// get the image name and path
		if ($image || $imageName) {
			$pizarraImgDir = SHARED_PUBLIC_PATH . '/content/pizarra';
			$fileName = Utils::randomHash();
			$filePath = "$pizarraImgDir/$fileName.jpg";

			if ($image) {
				$filePath = Images::saveBase64Image($image, $filePath);
			} else {
				$tempImagePath = $request->input->files[$imageName];
				$extension = explode('/', mime_content_type($tempImagePath))[1];
				$filePath = str_replace('.jpg', ".$extension", $filePath);
				rename($tempImagePath, $filePath);
			}

			$fileName = basename($filePath);
		}

		// only post notes with real content
		$minLength = isset($request->input->data->link->command) ? 0 : 3;

		if (strlen($text) < $minLength) {
			return;
		}

		// get all the topics from the post
		preg_match_all('/#\w*/', $text, $topics);
		$topics = $topics[0];// get all the topics from the post
		preg_match_all('/#\w*/', $text, $topics);
		$topics = $topics[0];

		// cut and escape values
		foreach ($topics as $i => $iValue) {
			$topics[$i] = Database::escape($iValue, 20);
		}

		$topic1 = isset($topics[0]) ? str_replace('#', '', $topics[0]) : '';
		$topic2 = isset($topics[1]) ? str_replace('#', '', $topics[1]) : '';
		$topic3 = isset($topics[2]) ? str_replace('#', '', $topics[2]) : '';

		// save note to the database
		$cleanText = Database::escape($text, 600);
		$link_command = Database::escape($request->input->data->link->command ?? '', 4000);
		$link_icon = Database::escape($request->input->data->link->icon ?? '', 100);
		$link_text = Database::escape($request->input->data->link->text ?? '', 600);
		$article = Database::escape($this->truncate(strip_tags($request->input->data->article ?? '','b strong u i h1 h2 h3 h4 p br hr ul ol li span'),5000,'',true, true), 5000);

		$sql = "INSERT INTO _pizarra_notes (id_person, `text`, image, topic1, topic2, topic3, link_command, link_icon, link_text, weight, article) 
			VALUES ('{$request->person->id}', '$cleanText', '$fileName', '$topic1', '$topic2', '$topic3', 
			NULLIF('$link_command', ''), NULLIF('$link_icon', ''), NULLIF('$link_text', ''), 100, '$article')";

		$this->insertedNoteId = Database::query($sql);

		// error if the note could not be inserted
		if (!is_numeric($this->insertedNoteId)) {
			throw new RuntimeException("PIZARRA: NoteID is null after INSERT. QUERY = $sql");
		}

		// fill muro
		Database::query("
			INSERT IGNORE INTO _pizarra_muro (id, person_id, note, author, created, inserted) 
			VALUES (uuid(), {$request->person->id}, {$this->insertedNoteId}, {$request->person->id}, current_timestamp, current_timestamp);");

		$friends = $request->person->getFriends();
		foreach ($friends as $friend) {
			Database::query("
				INSERT IGNORE INTO _pizarra_muro (id, person_id, note, author, created, inserted) 
				VALUES (uuid(), {$friend}, {$this->insertedNoteId}, {$request->person->id}, current_timestamp, current_timestamp);");
		}

		// complete the challenge
		Challenges::complete('write-pizarra-note', $request->person->id);

		// add the experience
		Level::setExperience('PIZARRA_POST_FIRST_DAILY', $request->person->id);

		// complete tutorial
		Tutorial::complete($request->person->id, 'post_pizarra');

		// submit to Google Analytics
		GoogleAnalytics::event('note_new', $this->insertedNoteId);

		// save the topics to the topics table
		foreach ($topics as $topic) {
			$topic = str_replace('#', '', $topic);
			$topic = Database::escape($topic, 20);
			Database::query("INSERT INTO _pizarra_topics (topic, note, id_person) VALUES ('$topic', '{$this->insertedNoteId}', '{$request->person->id}')", true);
		}

		// notify users mentioned
		$mentions = $this->findUsersMentionedOnText($text);
		foreach ($mentions as $m) {
			$blocks = Chats::isBlocked($request->person->id, $m->id);
			if ($blocks->blocked > 0) {
				continue;
			}

			Notifications::alert($m->id, "@{$request->person->username} le ha mencionado", 'comment', "{'command':'PIZARRA NOTA', 'data':{'note':'{$this->insertedNoteId}'}}");
			$this->addReputation($m->id, $request->person->id, $this->insertedNoteId, 1);
		}
	}

	/**
	 * Post a new note to the public feed
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 * @throws \Apretaste\Alert
	 * @throws FirebaseException
	 * @throws MessagingException
	 * @author salvipascual
	 */
	public function _comentar(Request $request, Response $response)
	{
		$comment = $request->input->data->comment;
		$noteId = $request->input->data->note;

		if (strlen($comment) < 2) {
			return;
		}

		// check the note ID is valid
		$note = Database::query("SELECT id, `text`,id_person, accept_comments,comments FROM _pizarra_notes WHERE id='$noteId' AND active=1");
		if ($note) {
			$note = $note[0];
		} else {
			return;
		}

		// si la nota no acepta comentario de otros
		if ((int)$note->accept_comments == 0 && (int)$note->id_person <> (int)$request->person->id) {
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

		// update influencers stats
		Influencers::incStat($note->id_person, 'comments');

		// add the experience
		Level::setExperience('PIZARRA_COMMENT_FIRST_DAILY', $request->person->id);

		// complete the challenge
		Challenges::complete('comment-pizarra-note', $request->person->id);

		// submit to Google Analytics
		GoogleAnalytics::event('note_comment', $note->id);

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
		$color = $request->person->gender === 'M' ? 'green-text' : ($request->person->gender === 'F' ? 'pink-text' : 'black-text');
		if ($request->person->id != $note->id_person) {
			Notifications::alert($note->id_person, "@{$request->person->username} ha comentado tu publicación", 'comment', "{'command':'PIZARRA NOTA', 'data':{'note':'$noteId'}}");
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
		// get list of topics
		$popularTopics = $this->getPopularTopics();

		$topics = [];

		if (isset($popularTopics[0])) {
			// get params for the algorithm
			$maxLetterSize = 10;
			$minLetterSize = 1;
			$maxTopicMentions = $popularTopics[0]->cnt;
			$minTopicMentions = $popularTopics[count($popularTopics) - 1]->cnt;
			$rate = ($maxTopicMentions - $minTopicMentions) / ($maxLetterSize - $minLetterSize);
			if ($rate === 0) {
				$rate = 1;
			} // avoid divisions by zero

			// get topics letter size and color
			foreach ($popularTopics as $t) {
				$topic = new stdClass();
				$topic->name = $t->name;
				$topic->size = ceil(($t->cnt - $minTopicMentions) / $rate);
				$topics[] = $topic;
			}

			// set topics in random order
			shuffle($topics);
		}

		// get the list of most popular users
		$populars = $this->getPopulars();

		$myUser = $this->preparePizarraUser($request->person);

		$response->setCache(360);
		$response->setLayout('pizarra.ejs');
		$response->SetTemplate('populars.ejs', [
			'topics' => $topics,
			'populars' => $populars,
			'myUser' => $myUser,
			'title' => 'Tendencia'
		]);
	}

	/**
	 * Show the content creatorsd
	 *
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws Exception
	 * @author ricardo
	 *
	 */

	public function _influencers(Request $request, Response $response)
	{
		$creators = Database::query(
			"SELECT A.id, A.username, A.avatar, A.avatarColor, A.online, A.gender,
       			 B.first_category, B.second_category 
				 FROM person A LEFT JOIN influencers B 
				 ON A.id = B.person_id WHERE A.is_influencer=1"
		);

		foreach ($creators as $creator) {
			$creator->isFriend = $request->person->isFriendOf($creator->id);

			$creator->firstCategoryCaption = Core::$influencerCategories[$creator->first_category];

			if ($creator->second_category != null) {
				$creator->secondCategoryCaption = Core::$influencerCategories[$creator->second_category];
			} else {
				$creator->secondCategoryCaption = null;
			}
		}

		$content = [
			'creators' => $creators,
			'myUser' => $this->preparePizarraUser($request->person),
			'title' => 'Influencers'
		];

		$response->setLayout('pizarra.ejs');
		$response->setTemplate('influencers.ejs', $content);
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
	 * Delete a note
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws \Apretaste\Alert
	 * @author ricardo@apretaste.org
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
	 * Add reputation
	 *
	 * @author ricardo@apretaste.org
	 */
	private function addReputation($toId, $fromId, $noteId, $amount): void
	{
		$amount = str_replace(',', '.', $amount);
		if ($toId != $fromId) {
			Database::query("INSERT INTO _pizarra_reputation(id_person, id_from, id_note, amount) VALUES ($toId, $fromId, $noteId, $amount)");
		}
	}

	/**
	 * Flag a note to be check by our team
	 *
	 * @param Request $request
	 * @param Response $response
	 * @author ricardo@apretaste.org
	 */
	public function _reportar(Request $request, Response &$response)
	{
		// get the text and note id
		$message = $request->input->data->message ?? false;
		$noteId = $request->input->data->id ?? false;

		// do not allow empty values
		if (empty($message) || empty($noteId)) {
			return false;
		}

		// escape the text
		$message = Database::escape($message, 250);

		// insert the query
		Database::query("
			INSERT INTO flags(service, person_id, reported_id, explanation) 
			VALUES('pizarra', {$request->person->id}, '$noteId', '$message')");

		// submit to Google Analytics
		GoogleAnalytics::event('note_flag', $noteId);
	}

	/**
	 * Search what type of search the user is doing
	 *
	 * @param $keyword
	 * @return array|null ["type", "value"]
	 * @throws \Apretaste\Alert
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
	 * @param bool $search
	 * @param int $page
	 * @return array of notes
	 * @throws Alert
	 * @author salvipascual
	 */
	private function getNotesByTopic($profile, $topic, $search = false, $page = 1): array
	{
		$offset = ($page - 1) * 20;
		$silencedQuery = 'SELECT topic FROM _pizarra_topics_silenced';

		$where = $topic !== 'general'
			? "WHERE (_pizarra_notes.topic1='$topic' OR _pizarra_notes.topic2='$topic' OR _pizarra_notes.topic3='$topic') AND active=1"
			: "WHERE _pizarra_notes.active=1 AND (_pizarra_notes.topic1 NOT IN($silencedQuery) AND _pizarra_notes.topic2 NOT IN($silencedQuery) AND _pizarra_notes.topic3 NOT IN($silencedQuery))";
		// set the topic as default for the user

		Database::query("UPDATE _pizarra_users SET default_topic='$topic' WHERE id_person='{$profile->id}'");

		$temporaryTableName = 'temprelation_' . uniqid('', false);
		Database::query("CREATE TEMPORARY TABLE $temporaryTableName 
    			SELECT relations.user1, relations.user2 
				FROM relations 
				WHERE (user1 = {$profile->id} OR user2 = {$profile->id}) AND type = 'blocked' AND confirmed = 1;");

		// get the records from the db
		$listOfNotes = Database::query("
			SELECT A.*,
			(select count(distinct id_person) from _pizarra_comments WHERE _pizarra_comments.note = A.id) as commentsUnique, 
				B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, 
			    B.country, B.online, B.avatar, B.avatarColor, B.is_influencer, C.reputation,
			    TIMESTAMPDIFF(HOUR,A.inserted,CURRENT_DATE) as hours,
				TIMESTAMPDIFF(DAY,A.inserted,CURRENT_DATE) as days,
				(SELECT COUNT(_pizarra_actions.note) FROM _pizarra_actions 
					WHERE _pizarra_actions.note = A.id AND id_person = {$profile->id} 
					  AND _pizarra_actions.action = 'like') > 0 AS isliked,
				(SELECT COUNT(_pizarra_actions.note) FROM _pizarra_actions 
					WHERE _pizarra_actions.note = A.id AND id_person = {$profile->id} 
					  AND _pizarra_actions.action = 'unlike') > 0 AS isunliked
			FROM (SELECT subq3.* 
					FROM (SELECT DISTINCT id, id_person 
						  FROM _pizarra_notes $where AND ad = 0 AND silenced = 0
						  ORDER BY id DESC LIMIT 500) subq2 
					INNER JOIN _pizarra_notes subq3 
					ON subq2.id = subq3.id
			      ) A
			LEFT JOIN (
			    SELECT P.id, P.username, P.first_name, P.last_name, P.province, P.picture, 
			           P.gender, P.country, P.online, 
			           P.avatar, P.avatarColor, P.is_influencer
			    FROM person P LEFT JOIN $temporaryTableName ON $temporaryTableName.user1 = P.id OR $temporaryTableName.user2 = P.id
			    WHERE $temporaryTableName.user1 IS NULL AND $temporaryTableName.user2 IS NULL  			    
			) B ON A.id_person = B.id 
			JOIN _pizarra_users C ON A.id_person = C.id_person ORDER BY weight DESC LIMIT 20 OFFSET $offset");

		$adNotes = !$search ? Database::query("
			SELECT A.*,
			(select count(distinct id_person) from _pizarra_comments WHERE _pizarra_comments.note = A.id AND _pizarra_comments.id_person <> A.id_person) as commentsUnique, 
				B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, 
			    B.country, B.online, B.avatar, B.avatarColor, B.is_influencer, C.reputation,
				TIMESTAMPDIFF(HOUR,A.inserted,CURRENT_DATE) as hours,
			    TIMESTAMPDIFF(DAY,A.inserted,CURRENT_DATE) as days,
				1 AS isliked,
				0 as isunliked
			FROM (SELECT subq3.* 
					FROM (SELECT id, id_person
						  FROM _pizarra_notes WHERE ad=1 and active=1
						  ORDER BY id DESC LIMIT 500) subq2 
					INNER JOIN _pizarra_notes subq3 
					ON subq2.id = subq3.id
			      ) A
			LEFT JOIN (
			    SELECT P.id, P.username, P.first_name, P.last_name, P.province, P.picture, 
			           P.gender, P.country, P.online, 
			           P.avatar, P.avatarColor, P.is_influencer
			    FROM person P 		    
			) B ON A.id_person = B.id 
			JOIN _pizarra_users C ON A.id_person = C.id_person ORDER BY RAND() LIMIT 1") : [];

		// sort results by weight. Too complex and slow in MySQL
		/*		usort($listOfNotes, function ($a, $b) {
					$a->score = (pow($a->hours, 0.5) * -1) * 0.4 + max($a->commentsUnique, 20) * 0.2 + ((($a->likes - intval($a->ownlike)) - $a->unlikes * 2) * 0.4) + $a->ad * 1000;
					$b->score = (pow($b->hours, 0.5) * -1) * 0.4 + max($b->commentsUnique, 20) * 0.2 + ((($b->likes - intval($a->ownlike)) - $b->unlikes * 2) * 0.4) + $b->ad * 1000;
					return ($b->score - $a->score) ? ($b->score - $a->score) / abs($b->score - $a->score) : 0;
				});
		*/
		// format the array of notes
		$notes = [];
		if (is_array($listOfNotes)) {
			foreach (array_merge($adNotes, $listOfNotes) as $note) {
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
	 * Search and return pages count by topic
	 *
	 * @param Person $profile
	 * @param String $topic
	 * @return int
	 * @throws Alert
	 * @throws \Apretaste\Alert
	 * @author ricardo
	 */
	private function getPagesByTopic($profile, $topic): int
	{
		$silencedQuery = 'SELECT topic FROM _pizarra_topics_silenced';

		$where = $topic !== 'general'
			? "WHERE (_pizarra_notes.topic1='$topic' OR _pizarra_notes.topic2='$topic' OR _pizarra_notes.topic3='$topic') AND active=1"
			: "WHERE _pizarra_notes.active=1 AND (_pizarra_notes.topic1 NOT IN($silencedQuery) AND _pizarra_notes.topic2 NOT IN($silencedQuery) AND _pizarra_notes.topic3 NOT IN($silencedQuery))";
		// set the topic as default for the user

		Database::query("UPDATE _pizarra_users SET default_topic='$topic' WHERE id_person='{$profile->id}'");

		$temporaryTableName = 'temprelation_' . uniqid('', false);
		Database::query("CREATE TEMPORARY TABLE $temporaryTableName 
    			SELECT relations.user1, relations.user2 
				FROM relations 
				WHERE (user1 = {$profile->id} OR user2 = {$profile->id}) AND type = 'blocked' AND confirmed = 1;");

		// get the records from the db
		$count = Database::queryFirst("
			SELECT COUNT(A.id) as total
			FROM (SELECT subq3.* 
					FROM (SELECT DISTINCT id, id_person 
						  FROM _pizarra_notes $where AND ad = 0 AND silenced = 0
						  ORDER BY id DESC LIMIT 500) subq2 
					INNER JOIN _pizarra_notes subq3 
					ON subq2.id = subq3.id
			      ) A
			LEFT JOIN (
			    SELECT P.id
			    FROM person P LEFT JOIN $temporaryTableName ON $temporaryTableName.user1 = P.id OR $temporaryTableName.user2 = P.id
			    WHERE $temporaryTableName.user1 IS NULL AND $temporaryTableName.user2 IS NULL  			    
			) B ON A.id_person = B.id 
			JOIN _pizarra_users C ON A.id_person = C.id_person");

		return (int)ceil($count->total / 20);
	}

	/**
	 * Search and return all notes made by a person
	 *
	 * @param Person $profile
	 * @param String $username
	 * @param int $page
	 * @return array of notes
	 * @throws Alert|\Apretaste\Alert
	 * @author salvipascual
	 */
	private function getNotesByUsername($profile, string $username, $page = 1): array
	{
		$offset = ($page - 1) * 20;
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
			SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.gender, B.country, B.avatar, B.avatarColor, B.is_influencer,
			(SELECT COUNT(note) FROM _pizarra_actions WHERE _pizarra_actions.note = A.id AND _pizarra_actions.id_person = '{$profile->id}' AND `action` = 'like') > 0 AS isliked,
			(SELECT COUNT(id) FROM _pizarra_comments WHERE _pizarra_comments.note = A.id) AS comments
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.id_person = B.id
			LEFT JOIN _pizarra_users C
			ON C.id_person = B.id
			WHERE A.active=1 AND B.id = '$user->id'
			ORDER BY inserted DESC
			LIMIT 20 OFFSET $offset");

		// format the array of notes
		$notes = [];
		foreach ($listOfNotes as $note) {
			$notes[] = $this->formatNote($note, $profile->id);
		}

		// return array of notes
		return $notes;
	}


	/**
	 * Search and return pages count by username
	 *
	 * @param String $username
	 * @return int
	 * @throws Alert
	 * @author ricardo
	 */
	private function getPagesByUsername(string $username): int
	{
		// get the last 50 records from the db
		$count = Database::queryCache("
			SELECT COUNT(A.id) as total FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.id_person = B.id
			LEFT JOIN _pizarra_users C
			ON C.id_person = B.id
			WHERE A.active=1 AND B.username = '$username'");

		// return total pages for this search
		return (int)ceil($count[0]->total / 20);
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
	private function getNotesByKeyword($profile, $keyword, $page = 1): array
	{
		$offset = ($page - 1) * 20;

		$temporaryTableName = 'temprelation_' . uniqid('', false);
		Database::query("CREATE TEMPORARY TABLE $temporaryTableName 
    			SELECT relations.user1, relations.user2 
				FROM relations 
				WHERE (user1 = {$profile->id} OR user2 = {$profile->id}) AND type = 'blocked' AND confirmed = 1;");

		// get the last 50 records from the db
		$listOfNotes = Database::query("
			SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.gender, B.country, B.online, B.avatar, B.avatarColor, B.is_influencer,
			(SELECT COUNT(note) FROM _pizarra_actions WHERE _pizarra_actions.note = A.id AND _pizarra_actions.id_person= '{$profile->id}' AND `action` = 'like') > 0 AS isliked,
			(SELECT count(id) FROM _pizarra_comments WHERE _pizarra_comments.note = A.id) as comments
			FROM _pizarra_notes A
			LEFT JOIN (
			    SELECT P.id, P.username, P.first_name, P.last_name, P.province, P.picture, 
			           P.gender, P.country, P.online, 
			           P.avatar, P.avatarColor, P.is_influencer
			    FROM person P LEFT JOIN $temporaryTableName ON $temporaryTableName.user1 = P.id OR $temporaryTableName.user2 = P.id
			    WHERE $temporaryTableName.user1 IS NULL AND $temporaryTableName.user2 IS NULL  			    
			) B
			ON A.id_person= B.id
			LEFT JOIN _pizarra_users C
			ON C.id_person = B.id
			WHERE A.active=1 AND A.text like '%$keyword%' 
			ORDER BY weight DESC
			LIMIT 20 OFFSET $offset");

		// format the array of notes
		$notes = [];
		foreach ($listOfNotes as $note) {
			$notes[] = $this->formatNote($note, $profile->email);
		}

		// return array of notes
		return $notes;
	}

	/**
	 * Search and return pages count by keyword
	 *
	 * @param Person $profile
	 * @param String $keyword
	 *
	 * @return int of notes
	 * @throws \Apretaste\Alert
	 * @author salvipascual
	 */
	private function getPagesByKeyword($profile, $keyword): int
	{
		$temporaryTableName = 'temprelation_' . uniqid('', false);
		Database::query("CREATE TEMPORARY TABLE $temporaryTableName 
    			SELECT relations.user1, relations.user2 
				FROM relations 
				WHERE (user1 = {$profile->id} OR user2 = {$profile->id}) AND type = 'blocked' AND confirmed = 1;");

		// get the last 50 records from the db
		$count = Database::queryFirst("
			SELECT COUNT(A.id) AS total FROM _pizarra_notes A
			LEFT JOIN (
			    SELECT P.id
			    FROM person P LEFT JOIN $temporaryTableName ON $temporaryTableName.user1 = P.id OR $temporaryTableName.user2 = P.id
			    WHERE $temporaryTableName.user1 IS NULL AND $temporaryTableName.user2 IS NULL  			    
			) B
			ON A.id_person= B.id
			LEFT JOIN _pizarra_users C
			ON C.id_person = B.id
			WHERE A.active=1 AND A.text like '%$keyword%'");

		// return total pages
		return (int)ceil($count->total / 20);
	}

	/**
	 * Get notes by user friends
	 *
	 * @param Person $person
	 *
	 * @param int $page
	 * @return array|null
	 * @throws Alert
	 * @author salvipascual
	 */
	private function getNotesByFriends(Person $person, $page = 1)
	{
		$offset = ($page - 1) * 20;

		$listOfNotes = Database::query("
			SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.gender, B.country, B.avatar, B.avatarColor, B.is_influencer, B.online,
				EXISTS(SELECT id FROM _pizarra_actions WHERE _pizarra_actions.note = A.id AND _pizarra_actions.id_person = '{$person->id}' AND `action` = 'like') AS isliked
			FROM _pizarra_muro muro INNER JOIN _pizarra_notes A ON muro.note = A.id  
			    INNER JOIN person B ON A.id_person = B.id
			WHERE muro.person_id = {$person->id}
			ORDER BY muro.created DESC
			LIMIT 20 OFFSET $offset");

		// format the array of notes
		$notes = [];
		foreach ($listOfNotes as $note) {
			$notes[] = $this->formatNote($note, $person->id);
		}

		// return array of notes
		return $notes;
	}

	/**
	 * Get notes pages count by user friends
	 *
	 * @param Person $person
	 *
	 * @return int
	 * @throws \Apretaste\Alert
	 * @author salvipascual
	 */
	private function getPagesByFriends(Person $person): int
	{
		$count = Database::queryCache("
			SELECT COUNT(A.id) as total FROM _pizarra_muro muro INNER JOIN _pizarra_notes A ON muro.note = A.id  
			    INNER JOIN person B ON A.id_person = B.id
			WHERE muro.person_id = {$person->id}");

		// return total pages
		return (int)ceil($count[0]->total / 20);
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
		$myUser->isInfluencer = $profile->isInfluencer ?? false;

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

		$note->image ??= false;

		// If the img doesn't have extension
		if ($note->image && !str_contains($note->image, '.')) {
			$note->image .= '.jpg';
		}

		$avatar = empty($note->avatar) ? ($note->gender === 'M' ? 'hombre' : ($note->gender === 'F' ? 'sennorita' : 'hombre')) : $note->avatar;

		// get the country and flag
		$country = empty(trim($note->country)) ? 'cu' : strtolower($note->country);
		$province = !empty($note->province) ? \Framework\Core::$provincesShort[$note->province] : null;

		// remove \" and \' from the note
		$note->text = strip_tags($note->text);
		$note->text = str_replace('\"', '"', $note->text);
		$note->text = str_replace("\'", "'", $note->text);
		$note->text = nl2br($note->text);

		while (json_encode($note->text, JSON_THROW_ON_ERROR, 512) === '') {
			$note->text = substr($note->text, 0, -2);
		}

		$note->text = html_entity_decode($note->text);
		$article = $note->article ?? '';
		if (empty($article)) $article = false;

		// add the text to the array
		return [
			'id' => $note->id,
			'id_person' => $note->id_person,
			'username' => $note->username,
			'location' => $location,
			'gender' => $note->gender,
			'isInfluencer' => (bool)$note->is_influencer ?? false,
			'text' => $note->text,
			'image' => $note->image,
			'inserted' => $note->inserted,
			'likes' => $note->likes ?? 0,
			'unlikes' => $note->unlikes ?? 0,
			'comments' => $note->comments ?? 0,
			'liked' => isset($note->isliked) && $note->isliked,
			'unliked' => isset($note->isunliked) && $note->isunliked,
			'reputation' => $note->reputation ?? 0,
			'ad' => $note->ad ?? false,
			'silenced' => $note->silenced ?? false,
			'online' => $note->online ?? false,
			'country' => $country,
			'province' => $province,
			'avatar' => $avatar,
			'avatarColor' => $note->avatarColor,
			'topics' => $topics,
			'canmodify' => $note->id_person === $id,
			'accept_comments' => (int)($note->accept_comments ?? 1) == 1,
			'linkCommand' => $note->link_command ?? false,
			'linkIcon' => $note->link_icon ?? false,
			'linkText' => $note->link_text ?? false,
			'article' => $article
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
		if (!empty($matches[0])) {
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

	/**
	 * PUBLICAR
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 */
	public function _publicar(Request $request, Response $response)
	{
		$this->_escribir($request, $response);
		if (isset($request->input->data->link->command) && !empty($request->input->data->link->command)) {
			$shareCommand = json_decode(base64_decode($request->input->data->link->command));
			switch ($shareCommand->command ?? '') {
				case 'CHISTE VER':
					Challenges::complete('share-joke', $request->person->id);
					break;
				case 'NOTICIAS HISTORIA':
					Challenges::complete('share-news', $request->person->id);
					break;
				case 'DONDEHAY VER':
					Challenges::complete('share-dondehay', $request->person->id);
					break;
			}
		}
	}


	// Ranking
	private function getLastSeed()
	{
		$seed = Database::queryFirst('select max(seed) as seed from ranking');
		return $seed->seed ?? null;
	}

	private function getPopulars()
	{
		$seed = $this->getLastSeed();
		$concept = 'POPULARITY';
		$sql = "select id_person, person.username, person.avatar, person.avatarColor, person.online, person.is_influencer, ranking.experience, from_date, to_date, position, person.gender 
                from ranking inner join person on person.id =ranking.id_person 
                where seed = '$seed' and concept = '$concept'
                order by position LIMIT 9;";

		$ranking = Database::queryCache($sql);

		foreach ($ranking as &$popular) {
			$popular->avatar = $popular->avatar ?? ($popular->gender === 'F' ? 'chica' : 'hombre');
		}

		if (!isset($ranking[0])) {
			$ranking = [];
		}

		return [
			'concept' => 'POPULARITY',
			'ranking' => $ranking,
			'from_date' => $ranking[0]->from_date ?? '',
			'to_date' => $ranking[0]->to_date ?? ''
		];
	}

	private function getPopularTopics()
	{
		return Database::queryCache("
			SELECT topic AS name, COUNT(id) AS cnt FROM _pizarra_topics
			WHERE created > DATE_ADD(NOW(), INTERVAL -30 DAY)
			AND topic <> 'general'
			GROUP BY topic ORDER BY cnt DESC LIMIT 50");
	}

	private function updateImpressions(array $notes)
	{
		for ($i = 0; $i < count($notes); $i++) {
			$note = $notes[$i];
			if ($notes[$i]['isInfluencer']) {
				// update influencers stats
				Influencers::incStat($note['id_person'], 'impressions');
				Database::query("UPDATE _pizarra_notes SET impressions=impressions+1 WHERE id='{$note['id']}'");
			}
		}
	}

	/**
	 * Truncates text.
	 *
	 * Cuts a string to the length of $length and replaces the last characters
	 * with the ending if the text is longer than length.
	 *
	 * @param string $text String to truncate.
	 * @param integer $length Length of returned string, including ellipsis.
	 * @param string $ending Ending to be appended to the trimmed string.
	 * @param boolean $exact If false, $text will not be cut mid-word
	 * @param boolean $considerHtml If true, HTML tags would be handled correctly
	 * @return string Trimmed string.
	 */
	private function truncate($text, $length = 100, $ending = '...', $exact = true, $considerHtml = false) {
		if ($considerHtml) {
			// if the plain text is shorter than the maximum length, return the whole text
			if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
				return $text;
			}

			// splits all html-tags to scanable lines
			preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);

			$total_length = strlen($ending);
			$open_tags = array();
			$truncate = '';

			foreach ($lines as $line_matchings) {
				// if there is any html-tag in this line, handle it and add it (uncounted) to the output
				if (!empty($line_matchings[1])) {
					// if it’s an “empty element” with or without xhtml-conform closing slash (f.e.)
					if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
						// do nothing
						// if tag is a closing tag (f.e.)
					} else if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
						// delete tag from $open_tags list
						$pos = array_search($tag_matchings[1], $open_tags);
						if ($pos !== false) {
							unset($open_tags[$pos]);
						}
						// if tag is an opening tag (f.e. )
					} else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
						// add tag to the beginning of $open_tags list
						array_unshift($open_tags, strtolower($tag_matchings[1]));
					}
					// add html-tag to $truncate’d text
					$truncate .= $line_matchings[1];
				}

				// calculate the length of the plain text part of the line; handle entities as one character
				$content_length = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));
				if ($total_length+$content_length > $length) {
					// the number of characters which are left
					$left = $length - $total_length;
					$entities_length = 0;
					// search for html entities
					if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
						// calculate the real length of all entities in the legal range
						foreach ($entities[0] as $entity) {
							if ($entity[1]+1-$entities_length <= $left) {
								$left--;
								$entities_length += strlen($entity[0]);
							} else {
								// no more characters left
								break;
							}
						}
					}
					$truncate .= substr($line_matchings[2], 0, $left+$entities_length);
					// maximum lenght is reached, so get off the loop
					break;
				} else {
					$truncate .= $line_matchings[2];
					$total_length += $content_length;
				}

				// if the maximum length is reached, get off the loop
				if($total_length >= $length) {
					break;
				}
			}
		} else {
			if (strlen($text) <= $length) {
				return $text;
			} else {
				$truncate = substr($text, 0, $length - strlen($ending));
			}
		}

		// if the words shouldn't be cut in the middle...
		if (!$exact) {
			// ...search the last occurance of a space...
			$spacepos = strrpos($truncate, ' ');
			if (isset($spacepos)) {
				// ...and cut the text in this position
				$truncate = substr($truncate, 0, $spacepos);
			}
		}

		// add the defined ending to the text
		$truncate .= $ending;

		if($considerHtml) {
			// close all unclosed html-tags
			foreach ($open_tags as $tag) {
				$truncate .= '';
			}
		}

		return $truncate;

	}
}
