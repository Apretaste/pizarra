<?php

class Pizarra extends Service
{
	/**
	 * To list lastest notes or post a new note
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _main (Request $request)
	{
		// get the type of search
		$search = $this->getSearchType($request->query, $request->email);
		$searchType = $search[0];
		$searchValue = $search[1];

		// get the user's profile
		$profile = $this->utils->getPerson($request->email);

		// create the user in the table if do not exist
		Connection::query("INSERT IGNORE INTO _pizarra_users (email) VALUES ('{$request->email}')");

		// get notes if serached by topic
		if($searchType == "topic") {
			$notes = $this->getNotesByTopic($profile, $searchValue);
			$title = "Tema #$searchValue";
		}

		// get notes if serached by username
		if($searchType == "username") {
			$notes = $this->getNotesByUsername($profile, $searchValue);
			$title = "Notas de @$searchValue";
		}

		// get notes if serached by keyword
		if($searchType == "keyword") {
			$notes = $this->getNotesByKeyword($profile, $searchValue);
			$title = $searchValue;
		}

		// check if the user is connecting via the app or email
		$di = \Phalcon\DI\FactoryDefault::getDefault();
		$notFromApp = $di->get('environment') != "app";

		// get most popular topics of last 7 days
		$popularTopics = Connection::query("
			SELECT topic AS name, COUNT(id) AS cnt FROM _pizarra_topics
			WHERE created > DATE_ADD(NOW(), INTERVAL -7 DAY)
			AND topic <> '$searchValue'
			GROUP BY topic ORDER BY cnt DESC LIMIT 10");

		// create variables for the template
		$content = [
			"isProfileIncomplete" => $profile->completion < 70,
			"notFromApp" => $notFromApp,
			"notes" => $notes,
			"popularTopics" => $popularTopics,
			"title" => $title
		];

		// create the response
		$response = new Response();
		$response->setEmailLayout('pizarra.tpl');
		$response->setResponseSubject("Notas en la pizarra");
		$response->createFromTemplate("pizarra.tpl", $content);
		return $response;
	}

	/**
	 * The user likes a note
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _like (Request $request)
	{
		// chekc if the user already liked this note
		$res = Connection::query("SELECT id FROM _pizarra_actions WHERE email='{$request->email}' AND note='{$request->query}' AND `action`='like'");
		if($res) return new Response();

		// delete possible previos vote and add new vote
		Connection::query("
			DELETE FROM _pizarra_actions WHERE email='{$request->email}' AND note='{$request->query}';
			INSERT INTO _pizarra_actions (email,note,action) VALUES ('{$request->email}','{$request->query}','like');
			UPDATE _pizarra_notes SET likes=likes+1 WHERE id='{$request->query}'");

		// pull the note
		$note = Connection::query("SELECT email, `text` FROM _pizarra_notes WHERE id='{$request->query}'")[0];

		// create notification for the creator
		$this->utils->addNotification($note->email, 'pizarra', "El usurio @{$request->username} le dio like a tu nota en la Pizarra", "PIZARRA NOTA {$request->query}");

		// increase the author's reputation
		Connection::query("UPDATE _pizarra_users SET reputation=reputation+2 WHERE email='{$note->email}'");

		// do not send any response
		return new Response();
	}

	/**
	 * The user unlikes a note
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _unlike (Request $request)
	{
		// chekc if the user already liked this note
		$res = Connection::query("SELECT id FROM _pizarra_actions WHERE email='{$request->email}' AND note='{$request->query}' AND `action`='unlike'");
		if($res) return new Response();

		// delete possible previos vote and add new vote
		Connection::query("
			DELETE FROM _pizarra_actions WHERE email='{$request->email}' AND note='{$request->query}';
			INSERT INTO _pizarra_actions (email,note,action) VALUES ('{$request->email}','{$request->query}','unlike');
			UPDATE _pizarra_notes SET unlikes=unlikes+1 WHERE id='{$request->query}'");

		// pull the note
		$note = Connection::query("SELECT email, `text` FROM _pizarra_notes WHERE id='{$request->query}'")[0];

		// decrease the author's reputation
		Connection::query("UPDATE _pizarra_users SET reputation=reputation-1 WHERE email='{$note->email}'");

		// do not send any response
		return new Response();
	}

	/**
	 * NOTA subservice
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _nota(Request $request)
	{
		// get the records from the db
		$result = Connection::query("
			SELECT
				A.id, A.email, A.text, A.likes, A.unlikes, A.comments, A.inserted, A.ad, A.topic1, A.topic2, A.topic3,
				B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.country,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE note=A.id AND email='{$request->email}' AND action='like') > 0 AS isliked,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE note=A.id AND email='{$request->email}' AND action='unlike') > 0 AS isunliked
			FROM _pizarra_notes A LEFT JOIN person B ON A.email = B.email
			WHERE A.id = '$request->query'");

		// format note
		if ($result) $note = $this->formatNote($result[0]);
		else return new Response();

		// get note comments
		$cmts = Connection::query("
			SELECT A.*, B.username, B.province, B.picture, B.gender, B.country
			FROM _pizarra_comments A
			LEFT JOIN person B
			ON A.email = B.email
			WHERE note = '$request->query'");

		// format comments
		$comments = [];
		if($cmts) foreach ($cmts as $c) $comments[] = $this->formatNote($c);
		$note['comments'] = $comments;

		// crease response
		$response = new Response();
		$response->setEmailLayout('pizarra.tpl');
		$response->createFromTemplate("note.tpl", ["note"=>$note]);
		return $response;
	}

	/**
	 * Post a new note to the public feed
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _escribir(Request $request)
	{
		// only post notes with real content
		if(strlen($request->query) < 16) return new Response();

		// replace accents by unicode chars
		$text = $this->utils->removeTildes($request->query);

		// shorten and clean the text
		$text = Connection::escape(substr($text, 0, 300));

		// get the current topic
		$defaultTopic = Connection::query("SELECT default_topic FROM _pizarra_users WHERE email='{$request->email}'")[0]->default_topic;

		// get all the topics from the post
		preg_match_all('/#\w*/', $text, $topics);
		$topics = array_merge($topics[0], [$defaultTopic]);
		$topic1 = isset($topics[0]) ? str_replace("#", "", $topics[0]) : "";
		$topic2 = isset($topics[1]) ? str_replace("#", "", $topics[1]) : "";
		$topic3 = isset($topics[2]) ? str_replace("#", "", $topics[2]) : "";

		// save note to the database
		$cleanText = Connection::escape($text, 300);
		$noteID = Connection::query("
			INSERT INTO _pizarra_notes (email, `text`, topic1, topic2, topic3)
			VALUES ('{$request->email}', '$cleanText', '$topic1', '$topic2', '$topic3')");

		// increase the writer's reputation
		Connection::query("UPDATE _pizarra_users SET reputation=reputation+1 WHERE email='{$request->email}'");

		// save the topics to the topics table
		foreach ($topics as $topic) {
			$topic = str_replace("#", "", $topic);
			Connection::query("
				INSERT INTO _pizarra_topics(topic, note, person)
				VALUES ('$topic', '$noteID', '{$request->email}')");
		}

		// notify users mentioned
		$mentions = $this->findUsersMentionedOnText($text);
		foreach ($mentions as $m) {
			$this->utils->addNotification($m->email, "PIZARRA", "El usuario @{$request->username} le ha mencionado en la pizarra", "PIZARRA NOTA $noteID");
		}

		// send a notificaction
		$this->utils->addNotification($request->email, 'PIZARRA', 'Su nota ha sido publicada en la Pizarra', "PIZARRA NOTA $noteID");

		// do not return any response
		return new Response();
	}

	/**
	 * Post a new note to the public feed
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _comentar(Request $request)
	{
		// get ID and comment
		$part = explode(" ", $request->query);
		$noteId = array_shift($part);
		$text = implode(" ", $part);

		// check the note ID is valid
		$note = Connection::query("SELECT email FROM _pizarra_notes WHERE id='$noteId'");
		if(empty($note)) return new Response(); else $note = $note[0];

		// save the comment
		$text = Connection::escape(substr($text, 0, 200));
		Connection::query("
			INSERT INTO _pizarra_comments (email, note, text) VALUES ('{$request->email}', '$noteId', '$text');
			UPDATE _pizarra_notes SET comments=comments+1 WHERE id=$noteId;");

		// notify users mentioned
		$mentions = $this->findUsersMentionedOnText($text);
		foreach ($mentions as $m) {
			$this->utils->addNotification($m->email, "PIZARRA", "El usuario @{$request->username} le ha mencionado en la pizarra", "PIZARRA NOTA $noteId");
		}

		// send a notificaction to the owner of the note
		$this->utils->addNotification($note->email, 'pizarra', 'Han comentado en su nota', "PIZARRA NOTA $noteId");

		// do not return any response when posting
		return new Response();
	}

	/**
	 * Show extense list of topics as a web cloud
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _temas(Request $request)
	{
		// get list of topics
		$ts = Connection::query("
			SELECT topic AS name, COUNT(id) AS cnt FROM _pizarra_topics
			WHERE created > DATE_ADD(NOW(), INTERVAL -30 DAY)
			AND topic <> 'general'
			GROUP BY topic ORDER BY cnt DESC LIMIT 100");

		// get params for the algorith
		$maxLetterSize = 30;
		$minLetterSize = 10;
		$maxTopicMentions = $ts[0]->cnt;
		$minTopicMentions = $ts[count($ts)-1]->cnt;
		$rate = ($maxTopicMentions-$minTopicMentions) / ($maxLetterSize-$minLetterSize);
		if($rate === 0) $rate = 1; // avoid divisions by zero

		// get topics letter size and color
		$topics = [];
		foreach ($ts as $t) {
			$topic = new stdClass();
			$topic->name = $t->name;
			$topic->count = $t->cnt;
			$topic->fontSize = (($t->cnt - $minTopicMentions) / $rate) + $minLetterSize;
			$topic->color = "#000000";
			$topics[] = $topic;
		}

		// set topics in random order
		shuffle ($topics);

		// get the list of most popular users
		$users = [];
		$popuplar = Connection::query("SELECT email, reputation FROM _pizarra_users ORDER BY reputation DESC LIMIT 9");
		foreach ($popuplar as $p) {
			$user = $this->utils->getPerson($p->email);
			$user->reputation = $p->reputation;
			$users[] = $user;
		}

		// create the response
		$response = new Response();
		$response->setEmailLayout('pizarra.tpl');
		$response->setResponseSubject("Lista de temas");
		$response->createFromTemplate("topics.tpl", ["topics"=>$topics, "users"=>$users]);
		return $response;
	}

	/**
	 * Show the user profile
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _perfil(Request $request)
	{
		// get the user's profile
		if(empty($request->query)) $email = $request->email;
		else $email = Utils::getEmailFromUsername($request->query);
		$person = Utils::getPerson($email);

		// if user do not exist, message the requestor
		if (empty($person)) {
			$response = new Response();
			$response->setResponseSubject("No encontramos el perfil");
			$response->createFromText("No encontramos un perfil para este usuario, por favor intente con otro nombre de usuario o pruebe mas tarde.");
			return $response;
		}

		// get user's reputation and default topic
		$user = Connection::query("SELECT * FROM _pizarra_users WHERE email='$email'");
		$person->reputation = empty($user[0]) ? 0 : $user[0]->reputation;
		$person->myTopic = empty($user[0]) ? "general" : $user[0]->default_topic;

		// get user topics
		$person->topics = [];
		$topics = Connection::query("SELECT DISTINCT topic FROM _pizarra_topics WHERE person='$email'");
		if($topics) foreach($topics as $t) $person->topics[] = $t->topic;

		// create data for the view
		$content = [
			"profile" => $person,
			"isMyOwnProfile" => $person->email == $request->email
		];

		// return response
		$response = new Response();
		$response->setEmailLayout('pizarra.tpl');
		$response->setResponseSubject("Perfil de @{$person->username}");
		$response->createFromTemplate("profile.tpl", $content, [$person->picture_public]);
		return $response;
	}

	/**
	 * Catalog the posts by topic
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _catalogar(Request $request)
	{
		// if you are trying to submit a topic
		$message = false;
		if($request->query) {
			// get note ID and topic
			$part = explode(" ", $request->query);
			$noteId = isset($part[0]) ? $part[0] : "";
			$topic = isset($part[1]) ? str_replace("#", "", $part[1]) : "";

			// get the note to update
			$note = Connection::query("SELECT topic1,topic2,topic3 FROM _pizarra_notes WHERE id='$noteId'");

			if($note && $topic) {
				// save topic in the database (also replace general topic)
				if(empty($note[0]->topic1) || $note[0]->topic1=="general") $topicToSave = "topic1='$topic'";
				elseif(empty($note[0]->topic2) || $note[0]->topic2=="general") $topicToSave = "topic2='$topic'";
				else $topicToSave = "topic3='$topic'";
				Connection::query("
					UPDATE _pizarra_notes SET $topicToSave WHERE id='$noteId';
					UPDATE _pizarra_users SET reputation=reputation+5 WHERE email='{$request->email}';
					INSERT INTO _pizarra_topics(topic,note,person) VALUES ('$topic','$noteId','{$request->email}');");
				$message = true;
			}
		}

		// get a random note
		$note = Connection::query("
			SELECT id, email, text, topic1, topic2, topic3 FROM _pizarra_notes
			WHERE inserted > DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 150 DAY)
			AND (topic1 = '' OR topic2 = '' OR topic3 = '')
			ORDER BY RAND() LIMIT 1")[0];

		// get the user profile
		$person = Utils::getPerson($note->email);

		// get last used topics
		$topics = [];
		$res = Connection::query("
			SELECT COUNT(id) AS rows, topic
			FROM _pizarra_topics
			WHERE created > DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 31 DAY)
			AND topic <> '{$note->topic1}'
			AND topic <> '{$note->topic2}'
			AND topic <> '{$note->topic3}'
			AND topic <> 'general'
			GROUP BY topic ORDER BY rows DESC LIMIT 50");
		foreach($res as $t) $topics[] = $t->topic;

		// create data for the view
		$content = [
			"message" => $message,
			"person" => $person,
			"note" => $note,
			"topics" => $topics
		];

		// return response
		$response = new Response();
		$response->setEmailLayout('pizarra.tpl');
		$response->setResponseSubject("Catalogue esta nota");
		$response->createFromTemplate("catalog.tpl", $content, [$person->picture_public]);
		return $response;
	}

	/**
	 * Denounce users
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _denunciar(Request $request)
	{
		// get @username and text
		$parts = explode(" ", $request->query);
		$username = array_shift($parts);
		$text = implode(" ", $parts);

		// get the email
		$email = $this->utils->getEmailFromUsername($username);
		if(empty($email)) return new Response();

		// only allow to report a person once a week
		$reportedByYou = Connection::query("SELECT id FROM _pizarra_denounce WHERE email='$email' AND denouncer='{$request->email}' AND inserted > (NOW()-INTERVAL 7 DAY)");
		if($reportedByYou) return new Response();

		// get code from text
		$reason = "OTHER";
		if(php::exists($text, "info falsa")) $reason = "FAKE_PROFILE";
		if(php::exists($text, "imperso")) $reason = "PERSONIFICATION";
		if(php::exists($text, "ofensiv")) $reason = "OFFENSIVE";
		if(php::exists($text, "notas falsa")) $reason = "FAKE_NOTES";
		if(php::exists($text, "inenten")) $reason = "ILLEGIBLE_NOTES";
		if(php::exists($text, "ilegal")) $reason = "ILLEGAL";
		if(php::exists($text, "inmoral")) $reason = "IMMORAL";

		// save into the database
		Connection::query("INSERT INTO _pizarra_denounce (email,denouncer,reason,`text`) VALUES ('$email','{$request->email}','$reason','$text')");

		// substract 50 to reputation when a user receives 5+ denounces a week
		$weeklyTimesReported = Connection::query("SELECT COUNT(id) AS cnt FROM _pizarra_denounce WHERE email='$email' AND inserted > (NOW()-INTERVAL 7 DAY)")[0]->cnt;
		if($weeklyTimesReported > 4) Connection::query("UPDATE _pizarra_users SET reputation=reputation-50 WHERE email='$email'");

		// respond empty variable
		return new Response();
	}

	/**
	 * Display the help document
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _ayuda(Request $request)
	{
		$response = new Response();
		$response->setEmailLayout('pizarra.tpl');
		$response->setResponseSubject("Ayuda de Pizarra");
		$response->createFromTemplate("help.tpl", []);
		return $response;
	}

	/**
	 * Search what type of search the user is doing
	 *
	 * @author salvipascual
	 * @param String $search
	 * @return Array ["type", "value"]
	 */
	private function getSearchType($keyword, $email)
	{
		// return topic selected by the user if blank
		if(empty($keyword)) {
			$topic = Connection::query("SELECT default_topic FROM _pizarra_users WHERE email='$email'");
			if(empty($topic[0]->default_topic)) $defaultTopic = "general";
			else $defaultTopic = $topic[0]->default_topic;
			return ["topic", $defaultTopic];
		}

		// get the number of words passed
		$oneWord = count(explode(" ", $keyword)) == 1;

		// check if searching for a username
		if($oneWord && strlen($keyword) > 2 && $keyword[0] == "@") return ["username", str_replace("@", "", $keyword)];

		// check if searching for a topic
		$topicNoHashSymbol = str_replace("#", "", $keyword);
		$topicExists = Connection::query("SELECT id FROM _pizarra_topics WHERE topic='$topicNoHashSymbol'");
		if($topicExists) return ["topic", $topicNoHashSymbol];

		// else searching for words on a note
		else return ["keyword", $keyword];
	}

	/**
	 * Get the number of times a topic show
	 *
	 * @author salvipascual
	 * @param String $topic
	 * @return Integer
	 */
	private function getTimesTopicShow($topic)
	{
		// @TODO add cache to avoid asking the same thousands of times
		$res = Connection::query("SELECT COUNT(id) as cnt FROM _pizarra_topics WHERE topic='$topic'");
		return $res[0]->cnt;
	}

	/**
	 * Search and return all notes by a topic
	 *
	 * @author salvipascual
	 * @param Profile $profile
	 * @param String $topic
	 * @return Array of notes
	 */
	private function getNotesByTopic($profile, $topic)
	{
		// set the topic as default for the user
		Connection::query("UPDATE _pizarra_users SET default_topic='$topic' WHERE email='{$profile->email}'");

		// get the records from the db
		$listOfNotes = Connection::query("
			SELECT
				A.id, A.email, A.text, A.likes, A.unlikes, A.comments, A.inserted, A.ad, A.topic1, A.topic2, A.topic3,
				B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.country,
				C.reputation,
				DATEDIFF(A.inserted,CURRENT_DATE) as days,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE note=A.id AND email='{$profile->email}' AND action='like') > 0 AS isliked,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE note=A.id AND email='{$profile->email}' AND action='unlike') > 0 AS isunliked
			FROM _pizarra_notes A
			LEFT JOIN person B ON A.email = B.email
			JOIN _pizarra_users C ON A.email = C.email
			WHERE (A.topic1='$topic' OR A.topic2='$topic' OR A.topic3='$topic')
			ORDER BY A.inserted DESC
			LIMIT 500");

		// sort results by weight. Too complex and slow in MySQL
		usort($listOfNotes, function($a, $b) {
			$one = $a->days*0.5 + $a->reputation*0.9 + $a->comments*0.2 + ($a->likes - $a->unlikes*1.5) + $a->ad*1000;
			$two = $b->days*0.5 + $b->reputation*0.9 + $b->comments*0.2 + ($b->likes - $b->unlikes*1.5) + $b->ad*1000;
			return ($two-$one) ? ($two-$one)/abs($two-$one) : 0;
		});

		// format the array of notes
		$notes = [];
		foreach ($listOfNotes as $note) {
			$notes[] = $this->formatNote($note); // format the array of notes
			if(count($notes) > 50) break; // only parse the first 50 notes
		}

		// mark all notes as viewed
		$viewed = array();
		foreach ($notes as $n) $viewed[] = $n['id'];
		$viewed = implode(",", $viewed);
		if (trim($viewed) !== '') Connection::query("UPDATE _pizarra_notes SET views=views+1 WHERE id IN ($viewed)");

		// return array of notes
		return $notes;
	}

	/**
	 * Search and return all notes made by a person
	 *
	 * @author salvipascual
	 * @param Profile $profile
	 * @param String $username
	 * @return Array of notes
	 */
	private function getNotesByUsername($profile, $username)
	{
		// get the last 50 records from the db
		$listOfNotes = Connection::query("
			SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.gender, B.country,
			(SELECT COUNT(note) FROM _pizarra_actions WHERE _pizarra_actions.note = A.id AND _pizarra_actions.email = '{$profile->email}' AND `action` = 'like') > 0 AS isliked,
			(SELECT count(id) FROM _pizarra_comments WHERE _pizarra_comments.note = A.id) as comments
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.email = B.email
			WHERE B.username = '$username'
			ORDER BY inserted DESC
			LIMIT 20");

		// format the array of notes
		$notes = array();
		foreach ($listOfNotes as $note) $notes[] = $this->formatNote($note);

		// mark all notes as viewed
		$viewed = array();
		foreach ($notes as $n) $viewed[] = $n['id'];
		$viewed = implode(",", $viewed);
		if (trim($viewed) !== '') Connection::query("UPDATE _pizarra_notes SET views=views+1 WHERE id IN ($viewed)");

		// return array of notes
		return $notes;
	}

	/**
	 * Search notes by keyword
	 *
	 * @author salvipascual
	 * @param Profile $profile
	 * @param String $keyword
	 * @return Array of notes
	 */
	private function getNotesByKeyword($profile, $keyword)
	{
		// get the last 50 records from the db
		$listOfNotes = Connection::query("
			SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.gender, B.country,
			(SELECT COUNT(note) FROM _pizarra_actions WHERE _pizarra_actions.note = A.id AND _pizarra_actions.email = '{$profile->email}' AND `action` = 'like') > 0 AS isliked,
			(SELECT count(id) FROM _pizarra_comments WHERE _pizarra_comments.note = A.id) as comments
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.email = B.email
			WHERE A.text like '%$keyword%'
			ORDER BY inserted DESC
			LIMIT 30");

		// format the array of notes
		$notes = array();
		foreach ($listOfNotes as $note) $notes[] = $this->formatNote($note);

		// mark all notes as viewed
		$viewed = array();
		foreach ($notes as $n) $viewed[] = $n['id'];
		$viewed = implode(",", $viewed);
		if (trim($viewed) !== '') Connection::query("UPDATE _pizarra_notes SET views=views+1 WHERE id IN ($viewed)");

		// return array of notes
		return $notes;
	}

	/**
	 * Format note to be send to the view
	 *
	 * @author salvipascual
	 * @param Object $note
	 * @return Array
	 */
	private function formatNote($note)
	{
		// get the location
		if (empty($note->province)) $location = "Cuba";
		else $location = ucwords(strtolower(str_replace("_", " ", $note->province)));

		// crate topics array
		$topics = [];
		if(isset($note->topic1) && $note->topic1) $topics[] = ["name"=>$note->topic1, "count"=>$this->getTimesTopicShow($note->topic1)];
		if(isset($note->topic2) && $note->topic2) $topics[] = ["name"=>$note->topic2, "count"=>$this->getTimesTopicShow($note->topic2)];
		if(isset($note->topic3) && $note->topic3) $topics[] = ["name"=>$note->topic3, "count"=>$this->getTimesTopicShow($note->topic3)];

		// get the country and flag
		$country = empty(trim($note->country)) ? "cu": strtolower($note->country);
		$flag = "/images/flags/$country.png";

		// include the function to create links
		$di = \Phalcon\DI\FactoryDefault::getDefault();
		$wwwroot = $di->get('path')['root'];
		require_once "$wwwroot/app/plugins/function.link.php";

		// create @usernames as links
		$mentions = $this->findUsersMentionedOnText($note->text);
		foreach ($mentions as $m) {
			$params['caption'] = "@{$m->username}";
			$params['href'] = "PIZARRA PERFIL @{$m->username}";
			$params['style'] = "color:#9E100A;";
			$link = smarty_function_link($params, null);
			$note->text = str_replace("@{$m->username}", $link, $note->text);
		}

		// add the text to the array
		$newNote = [
			"id" => $note->id,
			"username" => $note->username,
			"location" => $location,
			"gender" => $note->gender,
			"picture" => empty($note->picture) ? "/images/user.jpg" : "/profile/{$note->picture}.jpg",
			"text" => utf8_encode($note->text),
			"inserted" => date("Y-m-d H:i:s", strtotime($note->inserted)),
			"likes" => isset($note->likes) ? $note->likes : 0,
			"unlikes" => isset($note->unlikes) ? $note->unlikes : 0,
			"comments" => isset($note->comments) ? $note->comments : 0,
			"likecolor" => isset($note->isliked) && $note->isliked ? "#9E100A" : "black",
			"unlikecolor" => isset($note->isunliked) && $note->isunliked ? "#9E100A" : "black",
			"ad" => isset($note->ad) ? $note->ad : false,
			"country" => $country,
			"flag" => $flag,
			'email' => $note->email,
			"topics" => $topics
		];

		return $newNote;
	}

	/**
	 * Find all mentions on a text
	 *
	 * @author salvipascual
	 * @param String $text
	 * @return Array, [username,email]
	 */
	private function findUsersMentionedOnText($text)
	{
		// find all users mentioned
		preg_match_all('/@\w*/', $text, $matches);

		// filter the ones that exist
		$return = array();
		if ($matches[0]) {
			// get string of possible matches
			$usernames = "'" . implode("','", $matches[0]) . "'";
			$usernames = str_replace("@", "", $usernames);
			$usernames = str_replace(",'',", ",", $usernames);
			$usernames = str_replace(",''", "", $usernames);
			$usernames = str_replace("'',", "", $usernames);

			// check real matches against the database
			$users = Connection::query("SELECT email, username FROM person WHERE username in ($usernames)");

			// format the return
			foreach ($users as $user) {
				$object = new stdClass();
				$object->username = $user->username;
				$object->email = $user->email;
				$return[] = $object;
			}
		}

		return $return;
	}
}
