<?php

// include the Twitter library
use Abraham\TwitterOAuth\TwitterOAuth;
include_once "../vendor/abraham/twitteroauth/autoload.php";

class Pizarra extends Service
{
	private $KEY = "nXbz7LXFcKSemSb9v2pUh5XWV";
	private $KEY_SECRET = "kjSF6NOppBgR3UsP4u9KjwavrLUFGOcWEeFKmcWCZQyLLpOWCm";
	private $TOKEN = "4247250736-LgRlKf0MgOLQZY6VnaZTJUKTuDU7q0GefcEPYyB";
	private $TOKEN_SECRET = "WXpiTky2v9RVlnJnrwSYlX2BOmJqv8W3Sfb1Ve61RrWa3";

	/**
	 * To list lastest notes or post a new note
	 *
	 * @param Request
	 * @return Response
	 */
	public function _main (Request $request)
	{
		if ($request->query == "reemplace este texto por su nota")
		{
			$response = new Response();
			$responseContent = array("message" => 'Para que podamos escribir su nota, &iexcl;Usted primero debe escribirla!</p><p>Por favor presione el bot&oacute;n m&oacute;s abajo y reemplace en el asunto del email donde dice <b>"reemplace este texto por su nota"</b> con el texto a escribir e intente nuevamente.');
			$response->setResponseSubject("No nos ha enviado ninguna nota!");
			$response->createFromTemplate("message.tpl", $responseContent);
			return $response;
		}

		// connect to the database
		$connection = new Connection();
		$email = $request->email;
		
		// get the user from the database
		$res = $connection->deepQuery("SELECT username FROM person WHERE email='$email'");
		
		$user = null;
		if (isset($res[0]))
			$user = $res[0]->username;
		
		// post whatever the user types
		if ( ! empty($request->query))
		{
			// do not post notes without real information like empty mentions
			if(strlen($request->query) < 16) return new Response();

			// emails in text
			$emailsMentioned = $this->getAddressFrom($request->query);
			if (is_array($emailsMentioned))
			{
				foreach($emailsMentioned as  $em){
					$person = $this->utils->getPerson($em);
					if ($person !== false)
					{
						$request->query = str_replace($em, '@'.$person->username, $request->query);
					}
				}
			}
			
			// save note to the database
			$text = substr($request->query, 0, 130);
			$text = $connection->escape($text);
			$connection->deepQuery("INSERT INTO _pizarra_notes (email, text) VALUES ('$email', '$text')");

			// search for mentions and alert the user mentioned
			$mentions = $this->findUsersMentionedOnText($request->query);
			$usersMentioned = "";
			foreach ($mentions as $mention)
			{
				// do not allow self-mentioning
				if ($mention[0] == $user) continue;

				// save the list of users mentioned
				$usersMentioned .= "@" . $mention[0] . ", ";

				// email the user mentioned
				$responseContent = array("message" => "El usuario <b>@$user</b> le ha mencionado en una nota escrita en la pizarra. La nota se lee a continuaci&oacute;n:<br/><br/><br/>{$request->query}");
				$response = new Response();
				$response->setResponseEmail($mention[1]); // email the user mentioned
				$response->setResponseSubject("Han mencionado su nombre en la pizarra");
				$response->createFromTemplate("message.tpl", $responseContent);
				$responses[] = $response;
				
				// generate a notification
				$this->utils->addNotification($mention[1], 'pizarra', "<b>@$user</b> le ha mencionado en Pizarra.<br/>&nbsp;&nbsp;- {$request->query}", 'PIZARRA BUSCAR @'.$user, 'IMPORTANT');
			}

			// post in tweeter
			$text = trim(str_replace(" @", " ", $text), "@"); // remove @usernames for twitter
			$twitter = new TwitterOAuth($this->KEY, $this->KEY_SECRET, $this->TOKEN, $this->TOKEN_SECRET);
			try {
				$twitter->post("statuses/update", array("status" => "$user~> $text"));
			} catch (Exception $e) {}

			// save a notificaction
			$this->utils->addNotification($request->email, 'pizarra', 'Su nota ha sido publicada en Pizarra', 'PIZARRA');
			
			// do not return any response when posting
			return new Response();
		}

		// get the last 50 records from the db
		/**
		 SELECT * FROM (
				SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender
				FROM _pizarra_notes A
				LEFT JOIN person B
				ON A.email = B.email
				WHERE NOT EXISTS (SELECT * FROM _pizarra_block WHERE _pizarra_block.email = '{$email}' AND _pizarra_block.blocked = B.email)
				AND EXISTS (SELECT * FROM _pizarra_follow WHERE _pizarra_follow.email = '{$email}' AND _pizarra_follow.followed = B.email)
				LIMIT 30

				UNION

				SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender
				FROM _pizarra_notes A
				LEFT JOIN person B
				ON A.email = B.email
				WHERE NOT EXISTS (SELECT * FROM _pizarra_block WHERE _pizarra_block.email = '{$email}' AND _pizarra_block.blocked = B.email)
				AND NOT EXISTS (SELECT * FROM _pizarra_follow WHERE _pizarra_follow.email = '{$email}' AND _pizarra_follow.followed = B.email)
			) as subq
			ORDER BY inserted DESC 
			LIMIT 50
		 */
		/*
		 -- (SELECT COUNT(email) FROM _pizarra_follow WHERE email='{$request->email}' AND followed=A.email)*3 AS friend,
				-- (SELECT COUNT(email) FROM _pizarra_follow WHERE followed=A.email) AS popular,
		 */
		$listOfNotes = $connection->deepQuery("
			SELECT 
				A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender,
				A.likes*0.5 as loved,
				DATEDIFF(inserted,CURRENT_DATE)+7 as days,
				(SELECT COUNT(user1) FROM relations WHERE user1='{$request->email}' AND user2 = A.email AND type = 'follow') * 3 AS friend,
				(SELECT COUNT(user1) FROM relations WHERE user2 = A.email AND type = 'follow')*3 AS popular,
				RAND() as luck
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.email = B.email
			WHERE A.email NOT IN (SELECT user2 FROM relations WHERE user1 = '{$request->email}' and type = 'blocked')
			-- (SELECT blocked FROM _pizarra_block WHERE email = '{$request->email}')
			AND A.email NOT IN (SELECT relations.user2 FROM relations WHERE relations.user1 = '{$request->email}' AND relations.type = 'blocked')
			AND A.email <> '{$request->email}'
			ORDER BY inserted DESC
			LIMIT 300");

		// sort results by weigh. Too complex and slow in MySQL
		function cmp($a, $b) {
			$one = $a->loved + $a->days + $a->friend + $a->popular + $a->luck;
			$two = $b->loved + $b->days + $b->friend + $b->popular + $b->luck;
			if ($one == $two) return 0;
			return ($one > $two) ? -1 : 1;
		}
		
		usort($listOfNotes, "cmp");

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
			$note->text = $this->hightlightUsernames($note->text, $user);
			
			// add the text to the array
			$notes[] = array(
				"id" => $note->id,
				"name" => $note->username,
				"location" => $location,
				"gender" => $note->gender,
				"picture" => $note->picture,
				"text" => $note->text,
				"inserted" => date("Y-m-d H:i:s", strtotime($note->inserted)), // mysql timezone must be America/New_York
				"likes" => $note->likes,
				'source' => $note->source,
				'email' => $note->email,
				"friend" => $note->friend > 0
			);

			// only parse the first 50 notes
			if(count($notes) > 50) break;
		}

		// highlight hash tags
		for ($i = 0; $i < count($notes); $i ++)
		{
			$notes[$i]['text'] = ucfirst(strtolower($notes[$i]['text'])); // fix case
			$notes[$i]['text'] = $this->highlightHashTags($notes[$i]['text']);
		}

		// get the likes, follows and blocks
		$likes = $connection->deepQuery("SELECT SUM(likes) as likes FROM _pizarra_notes WHERE email='$email'")[0]->likes;
		
		$follows = $connection->deepQuery("SELECT COUNT(*) as follows FROM relations WHERE user2='$email'")[0]->follows;
		//$follows = $connection->deepQuery("SELECT COUNT(*) as follows FROM _pizarra_follow WHERE followed='$email'")[0]->follows;
		
		$blocks = $connection->deepQuery("SELECT COUNT(*) as blocks FROM relations WHERE user2='$email'")[0]->blocks;
		//$blocks = $connection->deepQuery("SELECT COUNT(*) as blocks FROM _pizarra_block WHERE blocked='$email'")[0]->blocks;

		// get last note
		$lastnote = $connection->deepQuery("SELECT * FROM _pizarra_notes WHERE email = '$email' ORDER BY inserted DESC LIMIT 1 OFFSET 0;");
		
		if ( ! isset($lastnote[0])) 
			$lastnote = false;
		else 
			$lastnote = $lastnote[0];
		
		// create variables for the template
		$responseContent = array(
			"likes" => $likes,
			"follows" => $follows,
			"blocks" => $blocks,
			"isProfileIncomplete" => $this->utils->getProfileCompletion($email) < 70,
			"notes" => $notes,
			"lastnote" => $lastnote,
			"username" => $user
		);

		// create the response
		$response = new Response();
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
				foreach($valid_formats as $vf)
				{
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
	
				// $where = "B.username = '$username' OR A.text like '%$username%'";
				
				if (strcasecmp(trim($username), trim($usern)) === 0)
					$subject = 'Mis notas en pizarra';
				else 
					$subject = "Notas de $query";
	
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
				"name" => $note->username,
				"location" => $location,
				"gender" => $note->gender,
				"picture" => $note->picture,
				"text" => $note->text,
				"inserted" => date("Y-m-d H:i:s", strtotime($note->inserted)), // mysql server timezone must be in America/New_York
				"likes" => $note->likes,
				'source' => $note->source,
				'email' => $note->email
			);
		}

		// highlight hash tags
		for ($i = 0; $i < count($notes); $i ++)
		{
			$notes[$i]['text'] = ucfirst(strtolower($notes[$i]['text'])); // fix case
			$notes[$i]['text'] = $this->highlightHashTags($notes[$i]['text']);
		}

		$content = array(
			"header" => $subject,
			"notes" => $notes
		);

		// create the response
		$response = new Response();
		$response->setResponseSubject($subject);
		$response->createFromTemplate("notas.tpl", $content);
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
			
			// @TODO: Drop _pizarra_block table and related code?
			//$connection->deepQuery("INSERT IGNORE INTO _pizarra_block (email, blocked) VALUES ('$person','$friend')");
			$connection->deepQuery("INSERT IGNORE INTO relations (user1,user2,type, confirmed) VALUES ('$person','$friend','blocked',1);");
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
		$connection->deepQuery("UPDATE _pizarra_notes SET likes=likes+1 WHERE id='{$request->query}'");

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
			//$res = $connection->deepQuery("SELECT * FROM _pizarra_follow WHERE email='$person' AND followed='$friend'");
			$res = $connection->deepQuery("SELECT * FROM relations WHERE user1='$person' AND user2='$friend'");
				
			// delete if exists
			if(count($res) > 0) $sql = "DELETE FROM relations WHERE user1='$person' AND user2='$friend'";
			// insert if does not exist
			else 
			{
				// @TODO: Drop _pizarra_follow table and related code?
				//$sql = "INSERT INTO _pizarra_follow (email, followed) VALUES ('$person','$friend');";
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
	private function findUsersMentionedOnText ($text)
	{
		// find all users mentioned
		preg_match_all('/@\w*/', $text, $matches);

		$return = array();
		if (! empty($matches[0])) {
			// get string of possible matches
			$usernames = "'" . implode("','", $matches[0]) . "'";
			$usernames = str_replace("@", "", $usernames);
				
			// check real matches agains the database
			$connection = new Connection();
			$users = $connection->deepQuery("SELECT email,username FROM person WHERE username in ($usernames)");
				
			// format the return
			foreach ($users as $user) {
				$return[] = array(
						$user->username,
						$user->email
				);
			}
		}

		return $return;
	}
	
	/*
	 * Extract email addresses from the text
	 *
	 * @author kuma
	 * @version 1.0
	 * @param string $text
	 * @return array
	 */
	private function getAddressFrom($text)
	{
		$chars = '1234567890abcdefghijklmnopqrstuvwxyz._-@ ';
		$text = strtolower($text);
	
		// Cleanning the text
		for ($i = 0; $i < 256; $i ++)
		{
			if (stripos($chars, chr($i)) === false)
			{
				$text = str_replace(chr($i), ' ', $text);
			}
		}
	
		$text = trim(str_replace(array(
			". ",
			" .",
			"- ",
			"_ "
		), " ", " $text "));
	
		// extract all phrases from text
		$words = explode(' ', $text);
	
		// checking each phrase
		$addresses = array();
		foreach ($words as $w)
		{
			if (trim($w) === '')
				continue;
	
				if ($this->checkAddress($w) === true && strpos($w, '@') !== false)
					$addresses[] = $w;
		}
	
		return $addresses;
	}
	
	/**
	 * Check if a string is an email address
	 *
	 * @author kuma
	 * @version 1.0
	 * @param string $email
	 * @return boolean
	 */
	private function checkAddress($email)
	{
		$regex = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/';
	
		if (preg_match($regex, $email))
			return true;
	
		return false;
	}
	
	/**
	 * Search usernames mentioned on text and replace it with link to NOTA
	 *
	 * @param string $text
	 * @return mixed
	 */
	function hightlightUsernames($text, $current_user){
		
		// TODO: if exists 2 mentions @foo and @fooo, this method dont work
		
		// highlight usernames and link it to NOTA
		$mentions = $this->findUsersMentionedOnText($text);
		
		if (is_array($mentions))
		{
			foreach($mentions as $mention)
			{
				// do not allow self-mentioning
				if ($mention[0] == $current_user) continue;
					
				$validEmailAddress = $this->utils->getValidEmailAddress();
				
				$generatedLink = '<a href="mailto:'.$validEmailAddress.'?subject=NOTA @' . $mention[0].' hola amigo, vi que te mencionaron en PIZARRA y te escribo esta nota&body=Envie+el+correo+tal+y+como+esta,+ya+esta+preparado+para+usted">@' . $mention[0] . '</a>';
					
				$text = str_replace('@'.$mention[0], $generatedLink, $text);
			}
		}
		return $text;
	}
}