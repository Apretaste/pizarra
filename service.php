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
		$listOfNotes = $connection->deepQuery("
			SELECT
				A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender,
				A.likes*0.5 as loved,
				DATEDIFF(inserted,CURRENT_DATE)+7 as days,
				(SELECT COUNT(user1) FROM relations WHERE user1='{$request->email}' AND user2 = A.email AND type = 'follow') * 3 AS friend,
				(SELECT COUNT(user1) FROM relations WHERE user2 = A.email AND type = 'follow') * 3 AS popular,
				RAND() as luck,
				(SELECT count(email) FROM _pizarra_seen_notes WHERE _pizarra_seen_notes.email = '{$request->email}' AND _pizarra_seen_notes.note = A.id) * 3 as seen
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.email = B.email
			WHERE A.email NOT IN (SELECT relations.user2 FROM relations WHERE relations.user1 = '{$request->email}' AND relations.type = 'blocked')
			ORDER BY inserted DESC
			LIMIT 300");

		// sort results by weight. Too complex and slow in MySQL
		usort($listOfNotes, function($a, $b) {
			$one = $a->loved + $a->days + $a->friend + $a->popular + $a->luck - $a->seen;
			$two = $b->loved + $b->days + $b->friend + $b->popular + $b->luck - $b->seen;
			if ($one == $two) return 0;
			return ($one > $two) ? -1 : 1;
		});

		// format the array of notes
		$emails = array();
		$notes = array();
		foreach ($listOfNotes as $note)
		{
			// only accept the first 5 notes per person
			if( ! isset($emails[$note->email])) $emails[$note->email] = 1;
			elseif($emails[$note->email] < 3) $emails[$note->email]++;
			else continue;

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
			$connection->deepQuery("INSERT IGNORE INTO _pizarra_seen_notes (note, email) VALUES ('{$note->id}', '{$request->email}');");

			// only parse the first 50 notes
			if(count($notes) > 50) break;
		}

		// highlight hash tags
		for ($i = 0; $i < count($notes); $i ++)
		{
			$notes[$i]['text'] = $this->highlightHashTags($notes[$i]['text']);
		}

		// get the likes, follows and blocks
		$likes = $connection->deepQuery("SELECT SUM(likes) as likes FROM _pizarra_notes WHERE email='{$request->email}'")[0]->likes;
		$follows = $connection->deepQuery("SELECT COUNT(id) as follows FROM relations WHERE user2='{$request->email}'")[0]->follows;
		$blocks = $connection->deepQuery("SELECT COUNT(id) as blocks FROM relations WHERE user2='{$request->email}'")[0]->blocks;

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
		$usern = $connection->deepQuery("SELECT username FROM person WHERE email='{$request->email}'");
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
		$listOfNotes = $connection->deepQuery("
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
		$email = $connection->deepQuery("SELECT email FROM person WHERE username = '$username'");

		// add one to the likes for that post
		if(count($email) > 0)
		{
			$person = $request->email;
			$friend = $email[0]->email;
			$connection->deepQuery("INSERT IGNORE INTO relations (user1,user2,type, confirmed) VALUES ('$person','$friend','blocked',1);");
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
		$email = $connection->deepQuery("SELECT email FROM person WHERE username = '$username'");

		if(count($email) > 0)
		{
			$person = $request->email;
			$friend = $email[0]->email;
			$connection->deepQuery("DELETE FROM relations WHERE user1 = '$person' AND user2 = '$friend' AND type = 'blocked' AND confirmed = 1;");
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
		$connection->deepQuery("UPDATE _pizarra_notes SET likes = likes + 1 WHERE id='{$request->query}'");

		// Generate a notification
		$note = $connection->deepQuery("SELECT * FROM _pizarra_notes WHERE id='{$request->query}'");
		if (isset($note[0])) {
			$email = $note[0]->email;
			$person = $connection->deepQuery("SELECT * FROM person WHERE email = '{$request->email}';");
			$person = $person[0];
			$this->utils->addNotification($request->email, 'pizarra like', 'A @'.$person->username.' le gusta tu nota <b>"'.substr($note[0]->text,0,30).'"</b> en Pizarra.', "PERFIL @{$person->username}");
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
		// add one to the likes for that post
		$connection = new Connection();
		$connection->deepQuery("UPDATE _pizarra_notes SET likes = likes - 1 WHERE id='{$request->query}'");

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
		$email = $connection->deepQuery("SELECT email FROM person WHERE username = '$username'");

		// add one to the likes for that post
		if(count($email) > 0)
		{
			// check if the person is already following
			$person = $request->email;
			$friend = $email[0]->email;
			$res = $connection->deepQuery("SELECT * FROM relations WHERE user1='$person' AND user2='$friend'");

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
			$connection->deepQuery($sql);
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

			// check real matches agains the database
			$connection = new Connection();
			$users = $connection->deepQuery("SELECT email,username FROM person WHERE username in ($usernames)");

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
		// do not allow default text to be posted
		if ($text == "reemplace este texto por su nota") return new Response();

		// only post notes with real content
		if(strlen($text) < 16) return new Response();

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
		$pattern = '/((http|https)\:\/\/)?[a-zA-Z0-9\.\/\?\:@\-_=#]+\.([a-zA-Z0-9\&\.\/\?\:@\-_=#])*/';
		preg_match_all($pattern, $text, $matches);
		if( ! empty($matches[0]))
		{
			foreach ($matches[0] as $e) {
				$shortUrl = $this->utils->shortenUrl($e);
				$text = str_replace($e, $shortUrl, $text);
			}
		}

		// save note to the database
		$text = substr($text, 0, 130);
		$connection = new Connection();
		$text = $connection->escape($text);
		$connection->deepQuery("INSERT INTO _pizarra_notes (email, `text`) VALUES ('{$profile->email}', '$text')");

		// search for mentions and alert the user mentioned
		$mentions = $this->findUsersMentionedOnText($text);
		$usersMentioned = "";
		foreach ($mentions as $mention)
		{
			// do not allow self-mentioning
			if ($mention[0] == $profile->username) continue;

			// save the list of users mentioned
			$usersMentioned .= "@" . $mention[0] . ", ";

			// email the user mentioned
			$responseContent = array("message" => "El usuario <b>@{$profile->username}</b> le ha mencionado en una nota escrita en la pizarra. La nota se lee a continuaci&oacute;n:<br/><br/><br/>{$text}");
			$response = new Response();
			$response->setEmailLayout('pizarra.tpl');
			$response->setResponseEmail($mention[1]); // email the user mentioned
			$response->setResponseSubject("Han mencionado su nombre en la pizarra");
			$response->createFromTemplate("message.tpl", $responseContent);
			$responses[] = $response;

			// generate a notification
			$this->utils->addNotification($mention[1], 'pizarra', "<b>@{$profile->username}</b> le ha mencionado en Pizarra.<br/>&gt;{$text}", "PIZARRA BUSCAR @{$profile->username}", 'IMPORTANT');
		}

		// save a notificaction
		$this->utils->addNotification($profile->email, 'pizarra', 'Su nota ha sido publicada en Pizarra', 'PIZARRA');

		// do not return any response when posting
		return new Response();
	}
}
