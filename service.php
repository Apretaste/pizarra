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
		$profile = Utils::getPerson($request->email);

		// create the user in the table if do not exist
		Connection::query("INSERT IGNORE INTO _pizarra_users (id_person) VALUES ('{$request->userId}')");

		// get notes if serached by topic
		if($searchType == "topic") {
			$notes = $this->getNotesByTopic($profile, $searchValue);
			$title = "#$searchValue";
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

		// get most popular topics of last 7 days
		$popularTopics = Connection::query("
			SELECT topic AS name, COUNT(id) AS cnt FROM _pizarra_topics
			WHERE created > DATE_ADD(NOW(), INTERVAL -7 DAY)
			AND topic <> '$searchValue'
			GROUP BY topic ORDER BY cnt DESC LIMIT 10");

		// create variables for the template
		$content = [
			"isProfileIncomplete" => $profile->completion < 70,
			"notes" => $notes,
			"popularTopics" => $popularTopics,
			"title" => $title
		];

		// get images for the web
		$images = [];
		if($request->environment == "web") {
			foreach ($notes as $note) {
				$images[] = $note['picture'];
				$images[] = $note['flag'];
			}
		}

		// create the response
		$response = new Response();
		$response->setEmailLayout('pizarra.tpl');
		$response->setResponseSubject("Notas en la pizarra");
		$response->createFromTemplate("pizarra.tpl", $content, $images);
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
		$res = Connection::query("SELECT * FROM _pizarra_actions WHERE id_person={$request->userId} AND note='{$request->query}'");
		$note = Connection::query("SELECT id_person, `text` FROM _pizarra_notes WHERE id='{$request->query}'");

		if(empty($note)) return new Response();

		if(!empty($res)){
			if($res[0]->action=='unlike'){
				// delete previos vote and add new vote
				Connection::query("
				UPDATE _pizarra_actions SET `action`='like' WHERE id_person='{$request->userId}' AND note='{$request->query}';
				UPDATE _pizarra_notes SET likes=likes+1, unlikes=unlikes-1 WHERE id='{$request->query}'");
				return new Response();
			}
			else return new Response();
		}

		// delete previos vote and add new vote
		Connection::query("
			INSERT INTO _pizarra_actions (id_person,note,action) VALUES ('{$request->userId}','{$request->query}','like');
			UPDATE _pizarra_notes SET likes=likes+1 WHERE id='{$request->query}'");

		$note = $note[0];
		$note->text = substr($note->text,0,30);

		// create notification for the creator
		Utils::addNotification($note->id_person, 'pizarra', "El usuario @{$request->username} le dio like a tu nota en la Pizarra: {$note->text}", "PIZARRA NOTA {$request->query}");

		// increase the author's reputation
		Connection::query("UPDATE _pizarra_users SET reputation=reputation+2 WHERE id_person='{$note->id_person}'");

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
		$res = Connection::query("SELECT * FROM _pizarra_actions WHERE id_person={$request->userId} AND note='{$request->query}'");
		$note = Connection::query("SELECT id_person, `text` FROM _pizarra_notes WHERE id='{$request->query}'");

		if(empty($note)) return new Response();
		
		if(!empty($res)){
			if($res[0]->action=='like'){
				// delete previos vote and add new vote
				Connection::query("
				UPDATE _pizarra_actions SET `action`='unlike' WHERE id_person='{$request->userId}' AND note='{$request->query}';
				UPDATE _pizarra_notes SET likes=likes-1, unlikes=unlikes+1 WHERE id='{$request->query}'");
				return new Response();
			}
			else return new Response();
		}
		
		// delete previos vote and add new vote
		Connection::query("
			INSERT INTO _pizarra_actions (id_person,note,action) VALUES ('{$request->userId}','{$request->query}','unlike');
			UPDATE _pizarra_notes SET unlikes=unlikes+1 WHERE id='{$request->query}'");
		
		$note = $note[0];

		// decrease the author's reputation
		Connection::query("UPDATE _pizarra_users SET reputation=reputation-1 WHERE id_person='{$note->id_person}'");

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
				A.id, A.id_person, A.text, A.likes, A.unlikes, A.comments, A.inserted, A.ad, A.topic1, A.topic2, A.topic3,
				B.email, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.country,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE note=A.id AND id_person='{$request->userId}' AND action='like') > 0 AS isliked,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE note=A.id AND id_person='{$request->userId}' AND action='unlike') > 0 AS isunliked
			FROM _pizarra_notes A LEFT JOIN person B ON A.id_person = B.id
			WHERE A.id = '$request->query' AND A.active=1");

		// format note
		if ($result) $note = $this->formatNote($result[0],$request->email);
		else return new Response();

		//check if the user is blocked by the owner of the note
		$blocks=$this->isBlocked($request->userId,$result[0]->id_person);
		if($blocks->blocked>0){
			$response=new Response();
			$response->subject="Lo sentimos, usted no puede ver esta nota";
			$response->createFromText("Lo sentimos, usted no tiene acceso a la nota solicitada");
			return $response;
		}

		// get note comments
		$cmts = Connection::query("
			SELECT A.*, B.username, B.province, B.picture, B.gender, B.country
			FROM _pizarra_comments A
			LEFT JOIN person B
			ON A.id_person = B.id
			WHERE note = '$request->query'");

		// format comments
		$comments = [];
		if($cmts) foreach ($cmts as $c) $comments[] = $this->formatNote($c,$request->email);
		$note['comments'] = $comments;

		// get images for the web
		$images = [];
		if($request->environment == "web") {
			$images[] = $note['picture'];
			$images[] = $note['flag'];
			foreach ($comments as $comment) {
				$images[] = $comment['picture'];
				$images[] = $comment['flag'];
			}
		}

		// crease response
		$response = new Response();
		$response->setEmailLayout('pizarra.tpl');
		$response->createFromTemplate("note.tpl", ["note"=>$note], $images);
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
		$text = $text = strip_tags($request->query);

		// only post notes with real content
		if(strlen($text) < 16) return new Response();

		//$text = Utils::removeTildes($request->query);
		// shorten and clean the text
		$text = Connection::escape(substr($text, 0, 300));

		// get the current topic
		$defaultTopic = Connection::query("SELECT default_topic FROM _pizarra_users WHERE id_person='{$request->userId}'")[0]->default_topic;

		// get all the topics from the post
		preg_match_all('/#\w*/', $text, $topics);
		$topics = array_merge($topics[0], [$defaultTopic]);
		$topic1 = isset($topics[0]) ? str_replace("#", "", $topics[0]) : "";
		$topic2 = isset($topics[1]) ? str_replace("#", "", $topics[1]) : "";
		$topic3 = isset($topics[2]) ? str_replace("#", "", $topics[2]) : "";

		// save note to the database
		$cleanText = Connection::escape($text, 300);
		$noteID = Connection::query("
			INSERT INTO _pizarra_notes (id_person, `text`, topic1, topic2, topic3)
			VALUES ('{$request->userId}', '$cleanText', '$topic1', '$topic2', '$topic3')");

		// save the topics to the topics table
		foreach ($topics as $topic) {
			$topic = str_replace("#", "", $topic);
			$topic = Connection::escape($topic, 20);
			Connection::query("
				INSERT INTO _pizarra_topics(topic, note, id_person)
				VALUES ('$topic', '$noteID', '{$request->userId}')");
		}

		// notify users mentioned
		$mentions = $this->findUsersMentionedOnText($text);
		foreach ($mentions as $m) {
			$blocks=$this->isBlocked($request->userId,$m->id);
			if($blocks->blocked>0) continue;
			Utils::addNotification($m->id, "PIZARRA", "El usuario @{$request->username} le ha mencionado en la pizarra", "PIZARRA NOTA $noteID");
		}

		// send a notificaction
		Utils::addNotification($request->userId, 'PIZARRA', 'Su nota ha sido publicada en la Pizarra', "PIZARRA NOTA $noteID");

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
		$text = strip_tags($text);
		$request->query = "";
		// check the note ID is valid
		$note = Connection::query("SELECT email,`text`,id_person FROM _pizarra_notes WHERE id='$noteId' AND active=1");
		if(empty($note)) return $this->_main($request); else $note = $note[0];

		$blocks=$this->isBlocked($request->userId,$note->id_person);
		if($blocks->blocked>0) return $this->main($request);

		// save the comment
		$text = Connection::escape(substr($text, 0, 200));
		Connection::query("
			INSERT INTO _pizarra_comments (id_person, note, text) VALUES ('{$request->userId}', '$noteId', '$text');
			UPDATE _pizarra_notes SET comments=comments+1 WHERE id=$noteId;");

		// notify users mentioned
		$mentions = $this->findUsersMentionedOnText($text);
		foreach ($mentions as $m) {
			$blocks=$this->isBlocked($request->userId,$m->id);
			if($blocks->blocked>0) continue;
			Utils::addNotification($m->id, "PIZARRA", "El usuario @{$request->username} le ha mencionado en la pizarra", "PIZARRA NOTA $noteId");
		}

		// send a notificaction to the owner of the note
		$note->text = substr($note->text,0,30);
		if($request->userId!=$note->id_person) Utils::addNotification($note->id_person, 'pizarra', "Han comentado en su nota: {$note->text}", "PIZARRA NOTA $noteId");
		$request->query = $noteId;
		// return the same note
		return $this->_nota($request);
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
		$images = [];
		$popuplar = Connection::query("SELECT id_person, reputation FROM _pizarra_users ORDER BY reputation DESC LIMIT 9");
		foreach ($popuplar as $p) {
			$user = Utils::getPerson($p->id_person);
			$user->reputation = $p->reputation;
			$users[] = $user;
			if($user->picture) $images[] = $user->picture_internal;
		}

		// create the response
		$response = new Response();
		$response->setEmailLayout('pizarra.tpl');
		$response->setResponseSubject("Lista de temas");
		$response->createFromTemplate("topics.tpl", ["topics"=>$topics, "users"=>$users], $images);
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
		$id = $person->id;

		// if user do not exist, message the requestor
		if (empty($person)) {
			$response = new Response();
			$response->setResponseSubject("No encontramos el perfil");
			$response->createFromText("No encontramos un perfil para este usuario, por favor intente con otro nombre de usuario o pruebe mas tarde.");
			return $response;
		}

		//check if the user is blocked
		$blocks=$this->isBlocked($request->userId,$person->id);
		$person->blocked=$blocks->blocked;
		$person->blockedByMe=$blocks->blockedByMe;

		if ($person->blocked) {
			$response = new Response();
			$response->setResponseSubject("Lo sentimos, usted no tiene acceso a este perfil");
			$response->createFromTemplate("blocked.tpl",['person'=>$person]);
			return $response;
		}

		// get user's reputation and default topic
		$user = Connection::query("SELECT * FROM _pizarra_users WHERE id_person='$id'");
		$person->reputation = empty($user[0]) ? 0 : $user[0]->reputation;
		$person->myTopic = empty($user[0]) ? "general" : $user[0]->default_topic;

		// get user topics
		$person->topics = [];
		$topics = Connection::query("SELECT * FROM (SELECT `topic` FROM _pizarra_topics WHERE id_person='$id'  
		ORDER BY `created` DESC LIMIT 5) A GROUP BY `topic`");
		if($topics) foreach($topics as $t) $person->topics[] = $t->topic;

		// create data for the view
		$content = [
			"profile" => $person,
			"isMyOwnProfile" => $person->email == $request->email
		];

		// get images for the web
		$images = [$person->picture_internal];
		if($request->environment == "web" && $person->country) {
			$di = \Phalcon\DI\FactoryDefault::getDefault();
			$wwwroot = $di->get('path')['root'];
			$images[] = "$wwwroot/public/images/flags/".strtolower($person->country).".png";
		}

		// return response
		$response = new Response();
		$response->setEmailLayout('pizarra.tpl');
		$response->setResponseSubject("Perfil de @{$person->username}");
		$response->createFromTemplate("profile.tpl", $content, $images);
		return $response;
	}

	/**
	 * Assign a topic to a note
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _temificar(Request $request)
	{
		// get note ID and topic
		$part = explode(" ", $request->query);
		$noteId = isset($part[0]) ? $part[0] : "";
		$topic = isset($part[1]) ? str_replace("#", "", $part[1]) : "";

		// get the note to update
		$note = Connection::query("SELECT topic1,topic2,topic3 FROM _pizarra_notes WHERE id='$noteId' AND id_person='$request->userId' AND active=1");

		if($note && $topic) {
			// save topic in the database
			$topic = Connection::escape($topic, 20);
			if(empty($note[0]->topic1)) $topicToSave = "topic1='$topic'";
			elseif(empty($note[0]->topic2)) $topicToSave = "topic2='$topic'";
			else $topicToSave = "topic3='$topic'";
			Connection::query("
				UPDATE _pizarra_notes SET $topicToSave WHERE id='$noteId';
				INSERT INTO _pizarra_topics(topic,note,id_person) VALUES ('$topic','$noteId','{$request->userId}');");
		}

		return new Response();
	}

	/**
	 * @author ricardo
	 * @param Request
	 * @return Response
	 */

	 public function _eliminar(Request $request){
		 $note=Connection::query("SELECT * FROM _pizarra_notes 
		 WHERE id='$request->query' AND id_person='$request->userId'");
		 
		 if(!empty($note)) Connection::query("UPDATE _pizarra_notes SET active=0 
		 WHERE id='$request->query'");

		 return new Response();
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
		$email = Utils::getEmailFromUsername($username);
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
		if(php::exists($text, "temas")) $reason = "WRONG_TOPIC";
		if(php::exists($text, "ilegal")) $reason = "ILLEGAL";
		if(php::exists($text, "inmoral")) $reason = "IMMORAL";

		// save into the database
		$text = Connection::escape($text, 500);
		Connection::query("INSERT INTO _pizarra_denounce (email,denouncer,reason,`text`) VALUES ('$email','{$request->email}','$reason','$text')");

		// substract 50 to reputation when a user receives 5+ denounces a week
		$weeklyTimesReported = Connection::query("SELECT COUNT(id) AS cnt FROM _pizarra_denounce WHERE email='$email' AND inserted > (NOW()-INTERVAL 7 DAY)")[0]->cnt;
		if($weeklyTimesReported > 4) Connection::query("UPDATE _pizarra_users SET reputation=reputation-50 WHERE email='$email'");

		// respond empty variable
		return new Response();
	}

	/**
	 * Check the list of opens chats or chat with somebody
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _chat(Request $request)
	{
		// get person to chat
		$social = new Social();
		$friendId = Utils::getIdFromUsername($request->query);

		// show notes of the conversation with a person
		if($friendId) {
			// get the list of people chating with you
			$chats = $social->chatConversation($request->userId, $friendId);

			// send information to the view
			$response = new Response();
			$response->setEmailLayout('pizarra.tpl');
			$response->setResponseSubject("Lista de chats");
			$response->createFromTemplate("conversation.tpl", ["username"=>str_replace("@", "", $request->query), "chats"=>$chats]);
			return $response;
		}

		// get open chats
		$chats = $social->chatsOpen($request->userId);

		// get the path to the root
		$di = \Phalcon\DI\FactoryDefault::getDefault();
		$wwwroot = $di->get('path')['root'];

		// get images for the web
		$images = [];
		if($request->environment == "web") {
			foreach ($chats as $chat) {
				$images[] = $chat->profile->picture_internal;
				$images[] = "$wwwroot/public/images/flags/".strtolower($chat->profile->country).".png";
			}
		}

		// send info to the view
		$response = new Response();
		$response->setEmailLayout('pizarra.tpl');
		$response->setResponseSubject("Lista de chats");
		$response->createFromTemplate("chats.tpl", ["chats"=>$chats], $images);
		return $response;
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
		$cache = Utils::getTempDir() . "pizarra_$topic" . date("YmdH") . ".cache";
		if(file_exists($cache)) $count = file_get_contents($cache);
		else {
			$count = Connection::query("SELECT COUNT(id) as cnt FROM _pizarra_topics WHERE topic='$topic'")[0]->cnt;
			file_put_contents($cache, $count);
		}
		return $count;
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
		Connection::query("UPDATE _pizarra_users SET default_topic='$topic' WHERE email='{$profile->id}'");

		// get the records from the db
		$listOfNotes = Connection::query("
			SELECT
				A.id, A.email, A.text, A.likes, A.unlikes, A.comments, A.inserted, A.ad, A.topic1, A.topic2, A.topic3,
				B.email, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.country, B.online,
				C.reputation,
				TIMESTAMPDIFF(HOUR,A.inserted,CURRENT_DATE) as hours,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE note=A.id AND id_person='{$profile->id}' AND action='like') > 0 AS isliked,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE note=A.id AND id_person='{$profile->id}' AND action='unlike') > 0 AS isunliked
			FROM (
				SELECT * FROM _pizarra_notes subq2 INNER JOIN (
					SELECT max(id) as idx FROM _pizarra_notes
					WHERE (topic1='$topic' OR topic2='$topic' OR topic3='$topic')
					AND active=1
					GROUP BY id_person
					) subq
				ON subq.idx = subq2.id
				ORDER BY inserted DESC
				LIMIT 500
			) A
			LEFT JOIN person B ON A.id_person = B.id 
			JOIN _pizarra_users C ON A.id_person = C.id_person
			WHERE A.id_person NOT IN(
				SELECT user1 AS id_person FROM relations 
				WHERE user2 = '$profile->id' 
				AND `type` = 'blocked' AND confirmed=1 UNION
				SELECT user2 AS id_person FROM relations 
				WHERE user1 = '$profile->id' 
				AND `type` = 'blocked' AND confirmed=1
			)");

		// sort results by weight. Too complex and slow in MySQL
		usort($listOfNotes, function($a, $b) {
			$a->score = 100-$a->hours + $a->comments*0.2 + ($a->likes-$a->unlikes*2) + $a->ad*1000;
			$b->score = 100-$b->hours + $b->comments*0.2 + ($b->likes-$b->unlikes*2) + $b->ad*1000;
			return ($b->score-$a->score) ? ($b->score-$a->score)/abs($b->score-$a->score) : 0;
		});

		// format the array of notes
		$notes = [];
		foreach ($listOfNotes as $note) {
			$notes[] = $this->formatNote($note,$profile->email); // format the array of notes
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
		$email = Utils::getEmailFromUsername($username);
		$id = Utils::personExist($email);

		// check if the person is blocked
		$blocks = $this->isBlocked($profile->id,$id);
		if($blocks->blocked > 0 || $blocks->blockedByMe > 0) return [];

		// get the last 50 records from the db
		$listOfNotes = Connection::query("
			SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.gender, B.country,
			(SELECT COUNT(note) FROM _pizarra_actions WHERE _pizarra_actions.note = A.id AND _pizarra_actions.id_person = '{$profile->id}' AND `action` = 'like') > 0 AS isliked,
			(SELECT COUNT(id) FROM _pizarra_comments WHERE _pizarra_comments.note = A.id) AS comments
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.id_person = B.id
			WHERE A.active=1 AND B.username = '$username'
			ORDER BY inserted DESC
			LIMIT 20");

		// format the array of notes
		$notes = array();
		foreach ($listOfNotes as $note) $notes[] = $this->formatNote($note,$profile->email);

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
			SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.gender, B.country, B.online,
			(SELECT COUNT(note) FROM _pizarra_actions WHERE _pizarra_actions.note = A.id AND _pizarra_actions.id_person= '{$profile->id}' AND `action` = 'like') > 0 AS isliked,
			(SELECT count(id) FROM _pizarra_comments WHERE _pizarra_comments.note = A.id) as comments
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.id_person= B.id
			WHERE A.active=1 AND A.text like '%$keyword%' AND 
			A.id_person NOT IN(
				SELECT user1 AS id_person FROM relations 
				WHERE user2 = '$profile->id' 
				AND `type` = 'blocked' AND confirmed=1 UNION
				SELECT user2 AS id_person FROM relations 
				WHERE user1 = '$profile->id' 
				AND `type` = 'blocked' AND confirmed=1
			)
			ORDER BY inserted DESC
			LIMIT 30");

		// format the array of notes
		$notes = array();
		foreach ($listOfNotes as $note) $notes[] = $this->formatNote($note,$profile->email);

		// mark all notes as viewed
		$viewed = array();
		foreach ($notes as $n) $viewed[] = $n['id'];
		$viewed = implode(",", $viewed);
		if (trim($viewed) !== '') Connection::query("UPDATE _pizarra_notes SET views=views+1 WHERE id IN ($viewed)");

		// return array of notes
		return $notes;
	}

	/**
	 * Get if the user is blocked or has been blocked by
	 * @author ricardo@apretaste.com
	 * @param String $user1
	 * @param String $user2
	 * @return Object
	 */
	private function isBlocked(String $user1, String $user2){
		$res=new stdClass();
		$res->blocked = false;
		$res->blockedByMe = false;

		$r = Connection::query("SELECT * 
		FROM ((SELECT COUNT(user1) AS blockedByMe FROM relations 
				WHERE user1 = '$user1' AND user2 = '$user2' 
				AND `type` = 'blocked' AND confirmed=1) AS A,
				(SELECT COUNT(user1) AS blocked FROM relations 
				WHERE user1 = '$user2' AND user2 = '$user1' 
				AND `type` = 'blocked' AND confirmed=1) AS B)");

		$res->blocked=($r[0]->blocked>0)?true:false;
		$res->blockedByMe=($r[0]->blockedByMe>0)?true:false;
		
		return $res;
	}

	/**
	 * Format note to be send to the view
	 *
	 * @author salvipascual
	 * @param Object $note
	 * @return Array
	 */
	private function formatNote($note,$email)
	{
		// get the location
		if (empty($note->province)) $location = "Cuba";
		else $location = ucwords(strtolower(str_replace("_", " ", $note->province)));

		// crate topics array
		$topics = [];
		if(isset($note->topic1) && $note->topic1) $topics[] = ["name"=>$note->topic1, "count"=>$this->getTimesTopicShow($note->topic1)];
		if(isset($note->topic2) && $note->topic2) $topics[] = ["name"=>$note->topic2, "count"=>$this->getTimesTopicShow($note->topic2)];
		if(isset($note->topic3) && $note->topic3) $topics[] = ["name"=>$note->topic3, "count"=>$this->getTimesTopicShow($note->topic3)];

		// get the path to the root
		$di = \Phalcon\DI\FactoryDefault::getDefault();
		$wwwroot = $di->get('path')['root'];

		// get the country and flag
		$country = empty(trim($note->country)) ? "cu": strtolower($note->country);
		$flag = "$wwwroot/public/images/flags/$country.png";

		// include the function to create links
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

		// remove \" and \' from the note
		$note->text = str_replace('\"', '"', $note->text);
		$note->text = str_replace("\'", "'", $note->text);
		$note->text = str_replace("\\n", "<br/>", $note->text);

		// add the text to the array
		$newNote = [
			"id" => $note->id,
			"username" => $note->username,
			"location" => $location,
			"gender" => $note->gender,
			"picture" => empty($note->picture) ? "$wwwroot/public/images/user.jpg" : "$wwwroot/public/profile/{$note->picture}.jpg",
			"text" => $note->text,
			"inserted" => date("Y-m-d H:i:s", strtotime($note->inserted)),
			"likes" => isset($note->likes) ? $note->likes : 0,
			"unlikes" => isset($note->unlikes) ? $note->unlikes : 0,
			"comments" => isset($note->comments) ? $note->comments : 0,
			"likecolor" => isset($note->isliked) && $note->isliked ? "#9E100A" : "black",
			"unlikecolor" => isset($note->isunliked) && $note->isunliked ? "#9E100A" : "black",
			"ad" => isset($note->ad) ? $note->ad : false,
			"online" => isset($note->online) ? $note->online : 0,
			"country" => $country,
			"flag" => $flag,
			'email' => $note->email,
			"topics" => $topics,
			'canmodify' => ($note->email==$email)?true:false
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
			$users = Connection::query("SELECT id, email, username FROM person WHERE username in ($usernames)");

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
