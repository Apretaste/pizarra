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
				A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender,
				DATEDIFF(inserted,CURRENT_DATE) as days,
				(SELECT COUNT(user1) FROM relations WHERE user1='{$request->email}' AND user2 = A.email AND type = 'follow') * 3 AS friend,
				(SELECT count(email) FROM _pizarra_seen_notes WHERE _pizarra_seen_notes.email = '{$request->email}' AND _pizarra_seen_notes.note = A.id) * 3 as seen,
				(SELECT reputation FROM _pizarra_reputation WHERE _pizarra_reputation.user1 = '{$request->email}' AND _pizarra_reputation.user2 = A.email) as reputation,
				(SELECT count(id) FROM _pizarra_comments WHERE _pizarra_comments.note = A.id) as comments
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.email = B.email
			WHERE A.email NOT IN (SELECT relations.user2 FROM relations WHERE relations.user1 = '{$request->email}' AND relations.type = 'blocked')
			ORDER BY inserted DESC
			LIMIT 300");

		// sort results by weight. Too complex and slow in MySQL
		usort($listOfNotes, function($a, $b) {
			$one = $a->days * 0.5 + $a->reputation * 0.3 + $a->comments * 0.2;
			$two = $b->days * 0.5 + $b->reputation * 0.3 + $b->comments * 0.2;
			if ($one == $two) return 0;
			return ($one > $two) ? -1 : 1;
		});

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
				"picture" => empty($note->picture) ? "" : "{$note->picture}.jpg",
				"text" => utf8_encode($note->text),
				"inserted" => date("Y-m-d H:i:s", strtotime($note->inserted)),
				"likes" => $note->likes,
				'source' => $note->source,
				'email' => $note->email,
				"friend" => $note->friend > 0,
			);

			// check the note as seen by the user
			$connection->query("INSERT IGNORE INTO _pizarra_seen_notes (note, email) VALUES ('{$note->id}', '{$request->email}');");

			// only parse the first 50 notes
			if(count($notes) > 50) break;
		}

		// highlight hash tags
		for ($i = 0; $i < count($notes); $i ++)
		{
			$notes[$i]['text'] = $this->highlightHashTags($notes[$i]['text']);
		}

		// get the likes, follows and blocks
		$likes = $connection->query("SELECT SUM(likes) as likes FROM _pizarra_notes WHERE email='{$request->email}'")[0]->likes;
		$follows = $connection->query("SELECT COUNT(id) as follows FROM relations WHERE user2='{$request->email}'")[0]->follows;
		$blocks = $connection->query("SELECT COUNT(id) as blocks FROM relations WHERE user2='{$request->email}'")[0]->blocks;

		// create variables for the template
		$responseContent = array(
			"likes" => $likes,
			"follows" => $follows,
			"blocks" => $blocks,
			"isProfileIncomplete" => $profile->completion < 70,
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
			SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender
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
				"likes" => $note->likes
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

		// pull the note liked
		$note = $connection->query("SELECT email, `text` FROM _pizarra_notes WHERE id='{$request->query}'");

		if ($note)
		{
			if ($note[0]->email != $request->email)
			{
				$connection->deepQuery("UPDATE _pizarra_notes SET likes = likes + 1 WHERE id='{$request->query}'");

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
				$connection->deepQuery("INSERT IGNORE INTO _pizarra_reputation (user1, user2) VALUES ('{$request->email}', '{$note[0]->email}');");
				$connection->deepQuery("UPDATE _pizarra_reputation SET reputation = reputation + 1 WHERE user1 = '{$request->email}' AND user2 = '{$note[0]->email}';");
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
        $connection = new Connection();

		// pull the note unliked
		$note = $connection->deepQuery("SELECT email, `text` FROM _pizarra_notes WHERE id='{$request->query}'");
		if ($note)
		{
			if ($note[0]->email != $request->email)
			{
				// add one to the likes for that post
				$connection = new Connection();
				$connection->query("UPDATE _pizarra_notes SET likes = likes - 1 WHERE id='{$request->query}'");

				// decrease author reputation
				$connection->deepQuery("INSERT IGNORE INTO _pizarra_reputation (user1, user2) VALUES ('{$request->email}', '{$note[0]->email}');");
				$connection->deepQuery("UPDATE _pizarra_reputation SET reputation = reputation - 1 WHERE user1 = '{$request->email}' AND user2 = '{$note[0]->email}';");
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
				$return[] = array($user->username, $user->email);
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
			foreach($mentions as $mention)
			{
				if ($mention[0] == $current_user) continue; // do not allow self-mentioning
				$validEmailAddress = $this->utils->getValidEmailAddress();
				$generatedLink = '<a href="mailto:'.$validEmailAddress.'?subject=NOTA @' . $mention[0].' hola amigo, vi que te mencionaron en PIZARRA y te escribo esta nota&body=Envie+el+correo+tal+y+como+esta,+ya+esta+preparado+para+usted">@' . $mention[0] . '</a>';
				$text = str_replace('@'.$mention[0], $generatedLink, $text);
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

					$r = $connection->deepQuery("SELECT COUNT(*) AS t FROM _pizarra_notes WHERE id = $id;");
					if (isset($r[0]->t))
					{
						if (intval($r[0]->t) == 1)
						{
							$text = trim(substr($text,strpos($text, '*')+1));

							if(strlen($text) < 16) return new Response();

							$text = $this->prepareText($text, $profile);
							
							$connection->deepQuery("INSERT INTO _pizarra_comments (email, note, text) VALUES ('{$profile->email}', $id, '$text');");

							// save a notificaction
							$this->utils->addNotification($profile->email, 'pizarra', 'Su comentario ha sido publicado en Pizarra', 'PIZARRA');

							// do not return any response when posting
							return new Response();

						}
					}
				}
			}
		}

		// do not allow default text to be posted
		if ($text == "reemplace este texto por su nota") return new Response();

		// only post notes with real content
		if(strlen($text) < 16) return new Response();

		$text = $this->prepareText($text, $profile);

		// save note to the database
		$text = $connection->escape($text);
		$connection->deepQuery("INSERT INTO _pizarra_notes (email, `text`) VALUES ('{$profile->email}', '$text')");

		// save a notificaction
		$this->utils->addNotification($profile->email, 'pizarra', 'Su nota ha sido publicada en Pizarra', 'PIZARRA');

		// do not return any response when posting
		return new Response();
	}

	public function _responder(Request $request)
	{
		// get the user's profile
		$profile = $this->utils->getPerson($request->email);

		// do not allow default text to be posted
		if ($text == "reemplace este texto por su respuesta") return new Response();

		// only post notes with real content
		if(strlen($text) < 16) return new Response();

		$text = $this->prepareText($text, $profile);

		// save note to the database
		$connection = new Connection();
		$connection->deepQuery("INSERT INTO _pizarra_notes (email, `text`) VALUES ('{$profile->email}', '$text')");

		// save a notificaction
		$this->utils->addNotification($profile->email, 'pizarra', 'Su respuesta ha sido publicada en Pizarra', 'PIZARRA');

		// do not return any response when posting
		return new Response();
	}

	private function prepareText($text, $profile)
	{
		// replace accents by unicode chars
		$text = $this->utils->removeTildes($text);

		// replace known emails in text by their usernames
		$pattern = "/(?:[a-z0-9!#$%&'*+=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+=?^_`{|}~-]+)*|\"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*\")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])/";
		preg_match_all($pattern, $text, $matches);
		if( ! empty($matches[0]))
		{
			foreach ($matches[0] as $e) {
				$person = $this->utils->getPerson($e);
				if($person) $text = str_replace($e, "@{$person->username}", $text);
			}
		}

		// shorten all urls in the note
		$pattern = '#[-a-zA-Z0-9@:%_\+.~\#?&//=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~\#?&//=]*)?#si';
		preg_match_all($pattern, $text, $matches);
		if( ! empty($matches[0]))
		{
			foreach ($matches[0] as $e) {
				$shortUrl = $this->utils->shortenUrl($e);
				if($shortUrl) $text = str_replace($e, $shortUrl, $text);
			}
		}

		$text = substr($text, 0, 130);
		$connection = new Connection();
		$text = $connection->escape($text);
		
		// search for mentions and alert the user mentioned
		$mentions = $this->findUsersMentionedOnText($text);
		$usersMentioned = "";
		foreach ($mentions as $mention)
		{
			$mentionedUsername = $mention[0];
			$mentionedEmail = $mention[1];

			// do not allow self-mentioning
			if ($mentionedUsername == $profile->username) continue;

			// save the list of users mentioned
			$usersMentioned .= "@" . $mention[0] . ", ";

			// email the user mentioned
			$responseContent = array("message" => "El usuario <b>@{$profile->username}</b> le ha mencionado en una nota escrita en la pizarra. La nota se lee a continuaci&oacute;n:<br/><br/><br/>{$text}");
			$response = new Response();
			$response->setEmailLayout('pizarra.tpl');
			$response->setResponseEmail($mentionedEmail);
			$response->setResponseSubject("Han mencionado su nombre en la pizarra");
			$response->createFromTemplate("message.tpl", $responseContent);
			$responses[] = $response;

			// send web notification for web users
			$pushNotification = new PushNotification();
			$appid = $pushNotification->getAppId($mentionedEmail, "pizarra");
			if($appid) $pushNotification->pizarraUserMentioned($appid, $profile->username);
			// OR generate a notification via email
			else $this->utils->addNotification($mentionedEmail, 'pizarra', "<b>@{$profile->username}</b> le ha mencionado en Pizarra.<br/>&gt;{$text}", "PIZARRA BUSCAR @{$profile->username}", 'IMPORTANT');
		}

		return $text;
	}
}
