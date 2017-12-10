<?php

class Pizarra extends Service
{
	/**
	 * To list lastest notes or post a new note
	 *
	 * @param Request
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
		$connection = new Connection();
		$popularTopics = $connection->query("
			SELECT topic AS name, COUNT(id) AS cnt FROM _pizarra_topics
			WHERE created > DATE_ADD(NOW(), INTERVAL -7 DAY)
			AND topic <> 'general'
			GROUP BY topic ORDER BY cnt DESC LIMIT 10");

		// create the list of elements for the dropdown
		$dropdown[] = "#general";
		foreach ($popularTopics as $t) $dropdown[] = "#{$t->name}";
		$topTopics = implode(",", $dropdown);

		// create variables for the template
		$content = [
			"isProfileIncomplete" => $profile->completion < 70,
			"notFromApp" => $notFromApp,
			"notes" => $notes,
			"popularTopics" => $popularTopics,
			"title" => $title,
			"topTopics" => $topTopics
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
	 * @param Request
	 * @return Response
	 */
	public function _like (Request $request)
	{
		// chekc if the user already liked this note
		$connection = new Connection();
		$res = $connection->query("SELECT id FROM _pizarra_actions WHERE email='{$request->email}' AND note='{$request->query}' AND `action`='like'");
		if($res) return new Response();

		// delete possible previos vote and add new vote
		$connection->query("
			DELETE FROM _pizarra_actions WHERE email='{$request->email}' AND note='{$request->query}';
			INSERT INTO _pizarra_actions (email,note,action) VALUES ('{$request->email}','{$request->query}','like');
			UPDATE _pizarra_notes SET likes=likes+1 WHERE id='{$request->query}'");

		// pull the note
		$note = $connection->query("SELECT email, `text` FROM _pizarra_notes WHERE id='{$request->query}'")[0];

		// create notification for the creator
		$this->utils->addNotification($note->email, 'pizarra', "El usurio @{$request->username} le dio like a tu nota en la Pizarra", "PIZARRA NOTA {$request->query}");

		// increase the author's reputation
		$connection->query("
			INSERT IGNORE INTO _pizarra_reputation (user1, user2) VALUES ('{$request->email}', '{$note->email}');
			UPDATE _pizarra_reputation SET reputation=reputation+1 WHERE user1='{$request->email}' AND user2='{$note->email}';");

		// do not send any response
		return new Response();
	}

	/**
	 * The user unlikes a note
	 *
	 * @author kuma
	 * @param Request
	 * @return Response
	 */
	public function _unlike (Request $request)
	{
		// chekc if the user already liked this note
		$connection = new Connection();
		$res = $connection->query("SELECT id FROM _pizarra_actions WHERE email='{$request->email}' AND note='{$request->query}' AND `action`='unlike'");
		if($res) return new Response();

		// delete possible previos vote and add new vote
		$connection->query("
			DELETE FROM _pizarra_actions WHERE email='{$request->email}' AND note='{$request->query}';
			INSERT INTO _pizarra_actions (email,note,action) VALUES ('{$request->email}','{$request->query}','unlike');
			UPDATE _pizarra_notes SET unlikes=unlikes+1 WHERE id='{$request->query}'");

		// pull the note
		$note = $connection->query("SELECT email, `text` FROM _pizarra_notes WHERE id='{$request->query}'")[0];

		// decrease the author's reputation
		$connection->query("
			INSERT IGNORE INTO _pizarra_reputation (user1, user2) VALUES ('{$request->email}', '{$note->email}');
			UPDATE _pizarra_reputation SET reputation=reputation-1 WHERE user1='{$request->email}' AND user2='{$note->email}';");

		// do not send any response
		return new Response();
	}

	/**
	 * NOTA subservice
	 *
	 * @author kumahacker
	 * @param Request $request
	 */
	public function _nota(Request $request)
	{
		// get the user's profile
		$profile = $this->utils->getPerson($request->email);

		// get note information
		$connection = new Connection();
		$result = $connection->query("SELECT
				A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.country,
				DATEDIFF(inserted,CURRENT_DATE) as days,
				(SELECT COUNT(user1) FROM relations WHERE user1='{$request->email}' AND user2 = A.email AND type = 'follow') * 3 AS friend,
				(SELECT COUNT(email) FROM _pizarra_seen_notes WHERE _pizarra_seen_notes.email = '{$request->email}' AND _pizarra_seen_notes.note = A.id) * 3 as seen,
				(SELECT reputation FROM _pizarra_reputation WHERE _pizarra_reputation.user1 = '{$request->email}' AND _pizarra_reputation.user2 = A.email) as reputation,
				(SELECT COUNT(id) FROM _pizarra_comments WHERE _pizarra_comments.note = A.id) as comments
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.email = B.email
			WHERE A.id = '$request->query';");

		// format note
		if ($result) $note = $this->formatNote($result[0]);
		else return new Response();

		// get note comments
		$cmts = $connection->query("
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
	 * @param string $text
	 * @return mixed
	 */
	public function _escribir($response)
	{
		// only post notes with real content
		if(strlen($response->query) < 16) return new Response();

		// replace accents by unicode chars
		$text = $this->utils->removeTildes($response->query);

		// shorten and clean the text
		$connection = new Connection();
		$text = $connection->escape(substr($text, 0, 300));

		// get all the topics from the post
		preg_match_all('/#\w*/', $text, $topics);
		$topics = $topics[0];
		$topic1 = isset($topics[0]) ? str_replace("#", "", $topics[0]) : "";
		$topic2 = isset($topics[1]) ? str_replace("#", "", $topics[1]) : "";
		$topic3 = isset($topics[2]) ? str_replace("#", "", $topics[2]) : "";
		if(empty($topic1)) $topic1 = "general"; // default topic if no topic is passed

		// save note to the database
		$noteID = $connection->query("
			INSERT INTO _pizarra_notes (email, `text`, topic1, topic2, topic3)
			VALUES ('{$response->email}', '$text', '$topic1', '$topic2', '$topic3')");

		// save the topics to the topics table
		foreach ($topics as $topic) {
			$topic = str_replace("#", "", $topic);
			$connection->query("
				INSERT INTO _pizarra_topics(topic, note, person)
				VALUES ('$topic', '$noteID', '{$response->email}')");
		}

		// notify users mentioned
		$mentions = $this->findUsersMentionedOnText($text);
		foreach ($mentions as $m) {
			$this->utils->addNotification($m->email, "PIZARRA", "El usuario @{$response->username} le ha mencionado en la pizarra", "PIZARRA NOTA $noteID");
		}

		// send a notificaction
		$this->utils->addNotification($response->email, 'PIZARRA', 'Su nota ha sido publicada en la Pizarra', "PIZARRA NOTA $noteID");

		// do not return any response
		return new Response();
	}

	/**
	 * Post a new note to the public feed
	 *
	 * @param string $text
	 * @return mixed
	 */
	public function _comentar($response)
	{
		// get ID and comment
		$part = explode(" ", $response->query);
		$noteId = array_shift($part);
		$text = implode(" ", $part);

		// check the note ID is valid
		$connection = new Connection();
		$note = $connection->query("SELECT email FROM _pizarra_notes WHERE id='$noteId'");
		if(empty($note)) return new Response(); else $note = $note[0];

		// save the comment
		$text = $connection->escape(substr($text, 0, 200));
		$connection->query("
			INSERT INTO _pizarra_comments (email, note, text) VALUES ('{$response->email}', '$noteId', '$text');
			UPDATE _pizarra_notes SET comments=comments+1 WHERE id=$noteId;");

		// notify users mentioned
		$mentions = $this->findUsersMentionedOnText($text);
		foreach ($mentions as $m) {
			$this->utils->addNotification($m->email, "PIZARRA", "El usuario @{$response->username} le ha mencionado en la pizarra", "PIZARRA NOTA $noteId");
		}

		// send a notificaction to the owner of the note
		$this->utils->addNotification($note->email, 'pizarra', 'Han comentado en su nota', "PIZARRA NOTA $noteId");

		// do not return any response when posting
		return new Response();
	}

	/**
	 * Show extense list of topics as a web cloud
	 *
	 * @param string $text
	 * @return mixed
	 */
	public function _temas($response)
	{
		// get list of topics
		$connection = new Connection();
		$ts = $connection->query("
			SELECT topic AS name, COUNT(id) AS cnt FROM _pizarra_topics
			WHERE created > DATE_ADD(NOW(), INTERVAL -30 DAY)
			AND topic <> 'general'
			GROUP BY topic ORDER BY cnt DESC LIMIT 100");

		// get params for the algorith
		$maxLetterSize = 30;
		$minLetterSize = 8;
		$maxTopicMentions = $ts[0]->cnt;
		$minTopicMentions = $ts[count($ts)-1]->cnt;
		$rate = ($maxTopicMentions-$minTopicMentions) / ($maxLetterSize-$minLetterSize);

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

		// set elements in random order
		shuffle ($topics);

		// get the list of most popular users
		$users = [];
		$popuplar = $connection->query("SELECT email FROM _pizarra_users ORDER BY reputation DESC LIMIT 9");
		foreach ($popuplar as $p) $users[] = $this->utils->getPerson($p->email);

		// create the response
		$response = new Response();
		$response->setEmailLayout('pizarra.tpl');
		$response->setResponseSubject("Lista de temas");
		$response->createFromTemplate("topics.tpl", ["topics"=>$topics, "users"=>$users]);
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
		$connection = new Connection();
		if(empty($keyword)) {
			$topic = $connection->query("SELECT default_topic FROM _pizarra_users WHERE email='$email'");
			return ["topic", $topic[0]->default_topic];
		}

		// get the number of words passed
		$oneWord = count(explode(" ", $keyword)) == 1;

		// check if searching for a username
		if($oneWord && strlen($keyword) > 2 && $keyword[0] == "@") return ["username", str_replace("@", "", $keyword)];

		// check if searching for a topic
		$topicNoHashSymbol = str_replace("#", "", $keyword);
		$topicExists = $connection->query("SELECT id FROM _pizarra_topics WHERE topic='$topicNoHashSymbol'");
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
		$connection = new Connection();
		$res = $connection->query("SELECT COUNT(id) as cnt FROM _pizarra_topics WHERE topic='$topic'");
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
		$connection = new Connection();
		$connection->query("
			INSERT IGNORE INTO _pizarra_users (email) VALUES ('{$profile->email}');
			UPDATE _pizarra_users SET default_topic='$topic' WHERE email='{$profile->email}';");

		// get the records from the db
		$listOfNotes = $connection->query("
			SELECT
				A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.country,
				DATEDIFF(inserted,CURRENT_DATE) as days,
				(SELECT COUNT(user1) FROM relations WHERE user1='{$profile->email}' AND user2 = A.email AND type = 'follow') * 3 AS friend,
				(SELECT COUNT(email) FROM _pizarra_seen_notes WHERE _pizarra_seen_notes.email = '{$profile->email}' AND _pizarra_seen_notes.note = A.id) * 3 as seen,
				(SELECT reputation FROM _pizarra_reputation WHERE _pizarra_reputation.user1 = '{$profile->email}' AND _pizarra_reputation.user2 = A.email) as reputation,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE _pizarra_actions.note = A.id AND _pizarra_actions.email = '{$profile->email}' AND `action` = 'like') > 0 AS isliked
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.email = B.email
			WHERE A.email NOT IN (SELECT relations.user2 FROM relations WHERE relations.user1 = '{$profile->email}' AND relations.type = 'blocked')
			AND (A.topic1 = '$topic' OR A.topic2 = '$topic' OR A.topic3 = '$topic')
			AND A.ad = 0
			ORDER BY inserted DESC
			LIMIT 300");

		// sort results by weight. Too complex and slow in MySQL
		usort($listOfNotes, function($a, $b) {
			$one = $a->days * 0.5 + $a->reputation * 0.3 + $a->comments * 0.2;
			$two = $b->days * 0.5 + $b->reputation * 0.3 + $b->comments * 0.2;
			if ($one == $two) return 0;
			return ($one > $two) ? -1 : 1;
		});

		// get one ad to show on top
		$ads = $connection->query("
			SELECT
				A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.country,
				0 as days, 0 as friend, 0 as seen, 0 as reputation,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE _pizarra_actions.note = A.id AND _pizarra_actions.email = '{$profile->email}' AND `action` = 'like') > 0 AS isliked
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.email = B.email
			WHERE A.ad = 1");
		$listOfNotes = array_merge($ads, $listOfNotes);

		// format the array of notes
		$notes = [];
		foreach ($listOfNotes as $note)
		{
			// format the array of notes
			$notes[] = $this->formatNote($note);

			// check the note as seen by the user
			$connection->query("INSERT IGNORE INTO _pizarra_seen_notes (note, email) VALUES ('{$note->id}', '{$profile->email}')");

			// only parse the first 50 notes
			if(count($notes) > 50) break;
		}

		// mark all notes as viewed
		$viewed = array();
		foreach ($notes as $n) $viewed[] = $n['id'];
		$viewed = implode(",", $viewed);
		if (trim($viewed) !== '') $connection->query("UPDATE _pizarra_notes SET views=views+1 WHERE id IN ($viewed)");

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
		$connection = new Connection();
		$listOfNotes = $connection->query("
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
		if (trim($viewed) !== '') $connection->query("UPDATE _pizarra_notes SET views=views+1 WHERE id IN ($viewed)");

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
		$connection = new Connection();
		$listOfNotes = $connection->query("
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
		if (trim($viewed) !== '') $connection->query("UPDATE _pizarra_notes SET views=views+1 WHERE id IN ($viewed)");

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
			$params['href'] = "PERFIL @{$m->username}";
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
			"isliked" => isset($note->isliked) ? $note->isliked : 0,
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
			$connection = new Connection();
			$users = $connection->query("SELECT email, username FROM person WHERE username in ($usernames)");

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
