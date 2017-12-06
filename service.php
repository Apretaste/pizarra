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
		// get the user's profile
		$profile = $this->utils->getPerson($request->email);

		// if the user passed a query, post a note and return
		if ( ! empty($request->query)) return $this->post($profile, $request->query);

		// else get the last 50 records from the db
		$connection = new Connection();
		$listOfNotes = $connection->query("
			SELECT
				A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.country,
				DATEDIFF(inserted,CURRENT_DATE) as days,
				(SELECT COUNT(user1) FROM relations WHERE user1='{$request->email}' AND user2 = A.email AND type = 'follow') * 3 AS friend,
				(SELECT COUNT(email) FROM _pizarra_seen_notes WHERE _pizarra_seen_notes.email = '{$request->email}' AND _pizarra_seen_notes.note = A.id) * 3 as seen,
				(SELECT reputation FROM _pizarra_reputation WHERE _pizarra_reputation.user1 = '{$request->email}' AND _pizarra_reputation.user2 = A.email) as reputation,
				(SELECT COUNT(note) FROM _pizarra_actions WHERE _pizarra_actions.note = A.id AND _pizarra_actions.email = '{$request->email}' AND action = 'like') > 0 AS isliked
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.email = B.email
			WHERE A.email NOT IN (SELECT relations.user2 FROM relations WHERE relations.user1 = '{$request->email}' AND relations.type = 'blocked')
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
				(SELECT COUNT(note) FROM _pizarra_actions WHERE _pizarra_actions.note = A.id AND _pizarra_actions.email = '{$request->email}' AND action = 'like') > 0 AS isliked
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.email = B.email
			WHERE A.ad = 1");
		$listOfNotes = array_merge($ads, $listOfNotes);

		// format the array of notes
		$emails = array();
		$notes = array();
		foreach ($listOfNotes as $note)
		{
			// only accept the first note of person
			if(isset($emails[$note->email])) continue;
			$emails[$note->email] = true;

			// get the name
			$name = trim("{$note->first_name} {$note->last_name}");
			if (empty($name)) $name = $note->email;

			// get the location
			if (empty($note->province)) $location = "Cuba";
			else $location = ucwords(strtolower(str_replace("_", " ", $note->province)));

			// highlight usernames and link it to NOTA
			$note->text = $this->hightlightUsernames($note->text, $profile->username);

			// add the text to the array
			$notes[] = array(
				"id" => $note->id,
				"username" => $note->username,
				"location" => $location,
				"gender" => $note->gender,
				"picture" => empty($note->picture) ? "/images/user.jpg" : "/profile/{$note->picture}.jpg",
				"text" => utf8_encode($note->text),
				"inserted" => date("Y-m-d H:i:s", strtotime($note->inserted)),
				"likes" => $note->likes,
				"unlikes" => $note->unlikes,
				"isliked" => $note->isliked,
				"comments" => $note->comments,
				'email' => $note->email,
				"friend" => $note->friend > 0,
				"country" => empty(trim($note->country)) ? "CU": $note->country,
				"ad" => $note->ad
			);

			// check the note as seen by the user
			$connection->query("INSERT IGNORE INTO _pizarra_seen_notes (note, email) VALUES ('{$note->id}', '{$request->email}');");

			// only parse the first 50 notes
			if(count($notes) > 50) break;
		}

		// mark all notes as viewed
		$viewed = array();
		foreach ($notes as $n) $viewed[] = $n['id'];
		$viewed = implode(",", $viewed);
		if (trim($viewed) !== '')
			$connection->query("UPDATE _pizarra_notes SET views=views+1 WHERE id IN ($viewed)");

		// highlight hash tags
		for ($i = 0; $i < count($notes); $i ++)
		{
			$notes[$i]['text'] = $this->highlightHashTags($notes[$i]['text']);
		}

		// get the likes, follows and blocks
		$likes = $connection->query("SELECT SUM(likes) as likes FROM _pizarra_notes WHERE email='{$request->email}'")[0]->likes;
		$follows = $connection->query("SELECT COUNT(id) as follows FROM relations WHERE user2='{$request->email}'")[0]->follows;
		$blocks = $connection->query("SELECT COUNT(id) as blocks FROM relations WHERE user2='{$request->email}'")[0]->blocks;

		// check if the user is connecting via the app or email
		$di = \Phalcon\DI\FactoryDefault::getDefault();
		$notFromApp = $di->get('environment') != "app";

		// create variables for the template
		$responseContent = array(
			"likes" => $likes,
			"follows" => $follows,
			"blocks" => $blocks,
			"isProfileIncomplete" => $profile->completion < 70,
			"notFromApp" => $notFromApp,
			"notes" => $notes,
			"username" => $profile->username,
			"profile" => $this->utils->getPerson($request->email)
		);

		// create the response
		$response = new Response();
		$response->setEmailLayout('pizarra.tpl');
		$response->setResponseSubject("Ultimas 50 notas");
		$response->createFromTemplate("pizarra.tpl", $responseContent);
		return $response;
	}

	/**
	 * Get the last 50 messages sent by a user
	 *
	 * @author salvipascual
	 * @param Request
	 * @return Response
	 *
	 */
	public function _buscar (Request $request)
	{
		// do not work if the search comes in blank
		$query = trim($request->query);
		if (empty($query))
		{
			$response = new Response();
			$response->setEmailLayout('pizarra.tpl');
			$response->createFromText("Por favor escriba un @username, un #hashtag o un texto a buscar.");
			return $response;
		}

		$connection = new Connection();

		// get the username from the email
		$usern = $connection->query("SELECT username FROM person WHERE email='{$request->email}'");
		$usern = $usern[0]->username;

		// check if the query is a date
		if (substr(strtolower($query),0,5)=='fecha')
		{
			$query = trim(substr($query,5));

			// by default
			$where = " TRUE ";
			$subject = "Ultimas 50 notas en Pizarra";

			// getting the date
			if ($query != '')
			{
				$valid_formats = array('YmdHis', 'Y-m-d H:i:s', 'Ymd', 'Y-m-d', 'd/m/Y H:i:s', 'd/m/Y', 'd-m-Y H:i:s', 'd-m-Y');
				foreach($valid_formats as $vf) {
					$date = date_create_from_format($vf, $query);
					if ($date !== false){
						$where = " A.inserted >= '".$date->format('Y-m-d H:i:s')."' ";
						$subject = "Ultimas notas a partir de ".$date->format('d/m/Y'). " a las ".$date->format('H:i:s'). 'hrs';
						break;
					}
				}
			}
		}
		else
		{
			// prepare to search for a text
			// @TODO make it work with levestein type algorithm
			$where = "A.text like '%$query%'";
			$subject = 'Notas con el texto "'.$query.'"';

			// get the number of words passed
			$numberOfWords = count(explode(" ", $query));

			// check if the query is a username
			if ($numberOfWords == 1 && strlen($query) > 2 && $query[0] == "@")
			{
				$username = str_replace("@", "", $query);
				if (strcasecmp(trim($username), trim($usern)) === 0) $subject = 'Mis notas en pizarra';
				else $subject = "Notas de $query";
				$where = "B.username = '$username'";
			}

			// check if the query is a hashtag
			if ($numberOfWords == 1 && strlen($query) > 2 && ($query[0] == "*" || $query[0] == "#"))
			{
				$hashtag = str_replace("*", "#", $query);
				$where = "A.text like '% $hashtag%'";
				$subject = "Veces que $hashtag es mencionado";
			}
		}


		// get the last 50 records from the db
		$connection = new Connection();
		$listOfNotes = $connection->query("
			SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.gender, B.country,
			(SELECT COUNT(note) FROM _pizarra_actions WHERE _pizarra_actions.note = A.id AND _pizarra_actions.email = '{$request->email}' AND action = 'like') > 0 AS isliked,
			(SELECT count(id) FROM _pizarra_comments WHERE _pizarra_comments.note = A.id) as comments
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.email = B.email
			WHERE $where
			ORDER BY inserted DESC
			LIMIT 50");

		// display message if the response is blank
		if (empty($listOfNotes))
		{
			$response = new Response();
			$response->setEmailLayout('pizarra.tpl');
			$response->createFromText("No se encontraron notas para el @username, #hashtag o texto que usted busc&oacute;.");
			return $response;
		}

		// format the array of notes
		$notes = array();
		foreach ($listOfNotes as $note)
		{
			// get the name
			$name = trim("{$note->first_name} {$note->last_name}");
			if (empty($name)) $name = $note->email;

			// get the location
			if (empty($note->province)) $location = "Cuba";
			else $location = ucwords(strtolower(str_replace("_", " ", $note->province)));

			// highlight usernames and link it to NOTA
			$note->text = $this->hightlightUsernames($note->text, $usern);

			// add the text to the array
			$notes[] = array(
				"id" => $note->id,
				"username" => $note->username,
				"location" => $location,
				"gender" => $note->gender,
				"picture" => empty($note->picture) ? "" : "{$note->picture}.jpg",
				"text" => utf8_encode($note->text),
				"inserted" => date("Y-m-d H:i:s", strtotime($note->inserted)),
				"likes" => $note->likes,
				"isliked" => $note->isliked,
				"gender" => $note->gender,
				"unlikes" => $note->unlikes,
				"comments" => $note->comments,
				"country" => empty(trim($note->country)) ? "CU": $note->country
			);
		}

		// highlight hash tags
		for ($i = 0; $i < count($notes); $i ++)
		{
			$notes[$i]['text'] = $this->highlightHashTags($notes[$i]['text']);
		}

		// create the response
		$response = new Response();
		$response->setEmailLayout('pizarra.tpl');
		$response->setResponseSubject($subject);
		$response->createFromTemplate("notas.tpl", array("header" => $subject, "notes" => $notes));
		return $response;
	}

	/**
	 * A user blocks all posts from another user
	 *
	 * @author salvipascual
	 * @param Request
	 * @return Response
	 */
	public function _bloquear (Request $request)
	{
		$connection = new Connection();

		// get the email from the username
		$username = trim(strtolower(str_replace("@", "", $request->query)));
		$email = $connection->query("SELECT email FROM person WHERE username = '$username'");

		// add one to the likes for that post
		if(count($email) > 0)
		{
			$person = $request->email;
			$friend = $email[0]->email;
			$connection->query("INSERT IGNORE INTO relations (user1,user2,type, confirmed) VALUES ('$person','$friend','blocked',1);");
		}

		// do not send any response
		return new Response();
	}

	/**
	 * A user blocks all posts from another user
	 *
	 * @author salvipascual
	 * @param Request
	 * @return Response
	 */
	public function _desbloquear (Request $request)
	{
		$connection = new Connection();

		// get the email from the username
		$username = trim(strtolower(str_replace("@", "", $request->query)));
		$email = $connection->query("SELECT email FROM person WHERE username = '$username'");

		if(count($email) > 0)
		{
			$person = $request->email;
			$friend = $email[0]->email;
			$connection->query("DELETE FROM relations WHERE user1 = '$person' AND user2 = '$friend' AND type = 'blocked' AND confirmed = 1;");
		}

		// do not send any response
		return new Response();
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
		// add one to the likes for that post
		$connection = new Connection();

		// search for duplicated like
		$result = $connection->query("SELECT * FROM _pizarra_actions WHERE email = '{$request->email}' AND note = '{$request->query}';");

		$downgrade = false;
		if (isset($result[0]->action))
			if ($result[0]->action == 'like')
				return new Response();
			else
				$downgrade = true;
		// pull the note liked
		$note = $connection->query("SELECT email, `text` FROM _pizarra_notes WHERE id='{$request->query}'");

		if ($note)
		{
			if ($note[0]->email != $request->email)
			{

				$connection->query("DELETE FROM _pizarra_actions WHERE email = '{$request->email}' AND note = '{$request->query}';");
				$connection->query("INSERT INTO _pizarra_actions (email, note, action) VALUES ('{$request->email}','{$request->query}','like');");
				$connection->query("UPDATE _pizarra_notes SET likes = likes + 1 ".($downgrade ? ", unlikes = unlikes - 1" : "")." WHERE id='{$request->query}'");

				// generate a notification
				$yourUsername = $this->utils->getUsernameFromEmail($request->email);
				$creatorEmail = $note[0]->email;
				$text = $note[0]->text;

				// send web notification for web users
				$pushNotification = new PushNotification();
				$appid = $pushNotification->getAppId($creatorEmail, "pizarra");
				if($appid) $pushNotification->pizarraHeartNote($appid, $yourUsername, $text);
				// OR generate a notification via email
				else $this->utils->addNotification($creatorEmail, 'pizarra like', "A @$yourUsername le gusto tu nota <b>$text</b> en Pizarra.", "PERFIL @$yourUsername");

				// increase author reputation
				$connection->query("INSERT IGNORE INTO _pizarra_reputation (user1, user2) VALUES ('{$request->email}', '{$note[0]->email}');");
				$connection->query("UPDATE _pizarra_reputation SET reputation = reputation + 1 WHERE user1 = '{$request->email}' AND user2 = '{$note[0]->email}';");
			}
		}

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
		// search for duplicated unlike
		$connection = new Connection();
		$result = $connection->query("SELECT * FROM _pizarra_actions WHERE email = '{$request->email}' AND note = '{$request->query}';");

		$downgrade = false;
		if (isset($result[0]->action)) {
			if ($result[0]->action == 'unlike') return new Response();
			else $downgrade = true;
		}

		// pull the note unliked
		$note = $connection->query("SELECT email, `text` FROM _pizarra_notes WHERE id='{$request->query}'");
		if ($note)
		{
			if ($note[0]->email != $request->email)
			{
				// add one to the unlikes for that post
				$connection->query("DELETE FROM _pizarra_actions WHERE email = '{$request->email}' AND note = '{$request->query}';");
				$connection->query("INSERT INTO _pizarra_actions (email, note, action) VALUES ('{$request->email}','{$request->query}','unlike');");
				$connection->query("UPDATE _pizarra_notes SET unlikes = unlikes + 1 ".($downgrade ? ", likes = likes - 1" : "")." WHERE id='{$request->query}'");

				// decrease author reputation
				$connection->query("INSERT IGNORE INTO _pizarra_reputation (user1, user2) VALUES ('{$request->email}', '{$note[0]->email}');");
				$connection->query("UPDATE _pizarra_reputation SET reputation = reputation - 1 WHERE user1 = '{$request->email}' AND user2 = '{$note[0]->email}';");
			}
		}

		// do not send any response
		return new Response();
	}

	/**
	 * The user follows or unfollows another user
	 *
	 * @author salvipascual
	 * @param Request
	 * @return Response
	 */
	public function _seguir (Request $request)
	{
		$connection = new Connection();

		// get the email from the username
		$username = trim(strtolower(str_replace("@", "", $request->query)));
		$email = $connection->query("SELECT email FROM person WHERE username = '$username'");

		// add one to the likes for that post
		if(count($email) > 0)
		{
			// check if the person is already following
			$person = $request->email;
			$friend = $email[0]->email;
			$res = $connection->query("SELECT * FROM relations WHERE user1='$person' AND user2='$friend'");

			// delete if exists
			if(count($res) > 0)
			{
				$sql = "DELETE FROM relations WHERE user1='$person' AND user2='$friend'";
			}
			else // insert if does not exist
			{
				$sql = "INSERT IGNORE INTO relations (user1,user2,type,confirmed) VALUES ('$person','$friend','follow',1);";
				$un = $this->utils->getPerson($person)->username;
				$this->utils->addNotification($friend, 'pizarra seguir', 'Ahora @'. $un. ' te sigue en Pizarra', 'PERFIL @'.$un);
			}

			// commit the query
			$connection->query($sql);
		}

		// do not send any response
		return new Response();
	}

	public function _responder(Request $request)
	{
		// get the user's profile
		$profile = $this->utils->getPerson($request->email);

		// only post notes with real content
		if(strlen($text) < 16) return new Response();

		$text = $this->prepareText($text, $profile);

		// save note to the database
		$connection = new Connection();
		$connection->query("INSERT INTO _pizarra_notes (email, `text`) VALUES ('{$profile->email}', '$text')");

		// save a notificaction
		$this->utils->addNotification($profile->email, 'pizarra', 'Su respuesta ha sido publicada en Pizarra', 'PIZARRA');

		// do not return any response when posting
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

		$id = intval($request->query);
		$connection = new Connection();
		$sql = "SELECT
				A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender, B.country,
				DATEDIFF(inserted,CURRENT_DATE) as days,
				(SELECT COUNT(user1) FROM relations WHERE user1='{$request->email}' AND user2 = A.email AND type = 'follow') * 3 AS friend,
				(SELECT COUNT(email) FROM _pizarra_seen_notes WHERE _pizarra_seen_notes.email = '{$request->email}' AND _pizarra_seen_notes.note = A.id) * 3 as seen,
				(SELECT reputation FROM _pizarra_reputation WHERE _pizarra_reputation.user1 = '{$request->email}' AND _pizarra_reputation.user2 = A.email) as reputation,
				(SELECT COUNT(id) FROM _pizarra_comments WHERE _pizarra_comments.note = A.id) as comments
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.email = B.email
			WHERE A.id = $id;";

		$result = $connection->query($sql);
		if (isset($result[0]->id)) $note = $result[0];

		// get the location
		if (empty($note->province)) $location = "Cuba";
		else $location = ucwords(strtolower(str_replace("_", " ", $note->province)));

		// highlight usernames and link it to NOTA
		$note->text = $this->hightlightUsernames($note->text, $profile->username);

		// add the text to the array
		$note = array(
			"id" => $note->id,
			"username" => $note->username,
			"location" => $location,
			"gender" => $note->gender,
			"picture" => empty($note->picture) ? "" : "{$note->picture}.jpg",
			"text" => utf8_encode($note->text),
			"inserted" => date("Y-m-d H:i:s", strtotime($note->inserted)),
			"likes" => $note->likes,
			"unlikes" => $note->unlikes,
			'email' => $note->email,
			"friend" => $note->friend > 0,
			"country" => empty(trim($note->country)) ? "CU": $note->country,
			'ad' => $note->ad
		);

		$comments = $connection->query("SELECT *, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender
			FROM _pizarra_comments A
			LEFT JOIN person B
			ON A.email = B.email
			WHERE note = $id;");

		if ( ! isset($comments[0])) $comments = [];
		$note['comments'] = $comments;
		$note['total_comments'] = count($comments);
		$response = new Response();
		$responseContent = ['note' => $note];
		//$responseContent['profile'] = $this->utils->getPerson($responseContent['email']);
		//$responseContent['username'] = $responseContent['profile']->username;
		$response->setEmailLayout('pizarra.tpl');
		$response->createFromTemplate("note.tpl", $responseContent);

		return $response;
	}

	/**
	 * Highlight words with a #hashtag
	 *
	 * @author salvipascual
	 * @param String $text
	 * @return String
	 */
	private function highlightHashTags ($text)
	{
		return preg_replace_callback('/#\w*/', function($matches){
			return "<b>{$matches[0]}</b>";
		}, $text);
	}

	/**
	 * Find all mentions on a text
	 *
	 * @author salvipascual
	 * @param String $text
	 * @return Array, [[username,email],[username,email]...]
	 */
	private function findUsersMentionedOnText($text)
	{
		// find all users mentioned
		$return = array();
		preg_match_all('/@\w*/', $text, $matches);
		if ( ! empty($matches[0]))
		{
			// get string of possible matches
			$usernames = "'" . implode("','", $matches[0]) . "'";
			$usernames = str_replace("@", "", $usernames);
			$usernames = str_replace(",'',", ",", $usernames);
			$usernames = str_replace(",''", "", $usernames);
			$usernames = str_replace("'',", "", $usernames);

			// check real matches against the database
			$connection = new Connection();
			$users = $connection->query("SELECT email,username FROM person WHERE username in ($usernames)");

			// format the return
			foreach ($users as $user) {
				$return[] = array("user"=>$user->username, "email"=>$user->email);
			}
		}

		return $return;
	}

	/**
	 * Search usernames mentioned on text and replace it with link to NOTA
	 *
	 * @param string $text
	 * @return mixed
	 */
	function hightlightUsernames($text, $current_user)
	{
		// highlight usernames and link it to NOTA
		$mentions = $this->findUsersMentionedOnText($text);

		if (is_array($mentions))
		{
			// include the function to create links
			$di = \Phalcon\DI\FactoryDefault::getDefault();
			$wwwroot = $di->get('path')['root'];
			require_once "$wwwroot/app/plugins/function.link.php";

			foreach($mentions as $mention)
			{
				if ($mention['user'] == $current_user) continue; // do not allow self-mentioning

				// if the user is connecting via the app
				$params['caption'] = "text";
				$params['href'] = "WEB diariodecuna.com";
				$generatedLink = smarty_function_link($params);
				$text = str_replace('@'.$mention['user'], $generatedLink, $text);
			}
		}

		return $text;
	}

	/**
	 * Post a new note to the public feed
	 *
	 * @param string $text
	 * @return mixed
	 */
	private function post($profile, $text)
	{
		$connection = new Connection();

		// check if it is a comment
		$words = explode(" ", trim($text));
		if (isset($words[0]))
		{
			$id = $words[0];
			$l = strlen($id);
			if (substr($id,$l-1,1) == "*")
			{
				$id = substr($id, 0, $l - 1);
				if ($id == "".intval($id))
				{
					$id = intval($id);

					$r = $connection->query("SELECT COUNT(*) AS t FROM _pizarra_notes WHERE id = $id;");
					if (isset($r[0]->t))
					{
						if (intval($r[0]->t) == 1)
						{
							$text = trim(substr($text,strpos($text, '*')+1));
							if(strlen($text) < 16) return new Response();
							$text = $this->prepareText($text, $profile);
							$connection->query("
								INSERT INTO _pizarra_comments (email, note, text) VALUES ('{$profile->email}', $id, '$text');
								UPDATE _pizarra_notes SET comments=comments+1 WHERE id=$id");

							// save a notificaction
							$this->utils->addNotification($profile->email, 'pizarra', 'Su comentario ha sido publicado en Pizarra', 'PIZARRA');

							// do not return any response when posting
							return new Response();
						}
					}
				}
			}
		}

		// only post notes with real content
		if(strlen($text) < 16) return new Response();

		$text = $this->prepareText($text, $profile);

		// save note to the database
		$text = $connection->escape($text);
		$connection->query("INSERT INTO _pizarra_notes (email, `text`) VALUES ('{$profile->email}', '$text')");

		// save a notificaction
		$this->utils->addNotification($profile->email, 'pizarra', 'Su nota ha sido publicada en Pizarra', 'PIZARRA');

		// do not return any response when posting
		return new Response();
	}

	private function prepareText($text, $profile)
	{
		// replace accents by unicode chars
		$text = $this->utils->removeTildes($text);

		// shorten and clean the text
		$text = substr($text, 0, 130);
		$connection = new Connection();
		$text = $connection->escape($text);

		// search for mentions and alert the user mentioned
		$mentions = $this->findUsersMentionedOnText($text);
		foreach ($mentions as $mention)
		{
			// do not allow self-mentioning
			if ($mention['user'] == $profile->username) continue;

			// send web notification for web users
			$pushNotification = new PushNotification();
			$appid = $pushNotification->getAppId($mention['email'], "pizarra");
			if($appid) $pushNotification->pizarraUserMentioned($appid, $profile->username);

			// create a notification
			$this->utils->addNotification($mention['email'], 'pizarra', "<b>@{$profile->username}</b> le ha mencionado en Pizarra", "PIZARRA BUSCAR @{$profile->username}", 'IMPORTANT');
		}

		return $text;
	}
}
