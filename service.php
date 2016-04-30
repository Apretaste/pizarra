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

		// post whatever the user types
		if ( ! empty($request->query))
		{
			// do not post notes without real information like empty mentions
			if(strlen($request->query) < 16) return new Response();

			// save note to the database
			$text = substr($request->query, 0, 130);
			$text = $connection->escape($text);
			$connection->deepQuery("INSERT INTO _pizarra_notes (email, text) VALUES ('$email', '$text')");

			// get the user from the database
			$res = $connection->deepQuery("SELECT username FROM person WHERE email='$email'");
			$user = $res[0]->username;

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
			}

			// post in tweeter
			$text = trim(str_replace(" @", " ", $text), "@"); // remove @usernames for twitter
			$twitter = new TwitterOAuth($this->KEY, $this->KEY_SECRET, $this->TOKEN, $this->TOKEN_SECRET);
			try {
				$twitter->post("statuses/update", array("status" => "$user~> $text"));
			} catch (Exception $e) {}

			// do not return any response when posting
			return new Response();
		}

		// get the last 50 records from the db
		$listOfNotes = $connection->deepQuery("
			SELECT 
				A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender,
				A.likes*0.5 as loved,
				DATEDIFF(inserted,CURRENT_DATE)+7 as days,
				(SELECT COUNT(email) FROM _pizarra_follow WHERE email='{$request->email}' AND followed=A.email)*3 AS friend,
				(SELECT COUNT(email) FROM _pizarra_follow WHERE followed=A.email) AS popular,
				RAND() as luck
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.email = B.email
			WHERE A.email NOT IN (SELECT blocked FROM _pizarra_block WHERE email='{$request->email}')
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
		$follows = $connection->deepQuery("SELECT COUNT(*) as follows FROM _pizarra_follow WHERE followed='$email'")[0]->follows;
		$blocks = $connection->deepQuery("SELECT COUNT(*) as blocks FROM _pizarra_block WHERE blocked='$email'")[0]->blocks;

		// create variables for the template
		$responseContent = array(
			"likes" => $likes,
			"follows" => $follows,
			"blocks" => $blocks,
			"isProfileIncomplete" => $this->utils->getProfileCompletion($email) < 70,
			"notes" => $notes
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
			$where = "B.username = '$username'";
			$subject = "Notas de $query";
		}

		// check if the query is a hashtag
		if ($numberOfWords == 1 && strlen($query) > 2 && ($query[0] == "*" || $query[0] == "#"))
		{
			$hashtag = str_replace("*", "#", $query);
			$where = "A.text like '% $hashtag%'";
			$subject = "Veces que $hashtag es mencionado";
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

			// add the text to the array
			$notes[] = array(
				"id" => $note->id,
				"name" => $note->username,
				"location" => $location,
				"gender" => $note->gender,
				"picture" => $note->picture,
				"text" => $note->text,
				"inserted" => date("Y-m-d H:i:s", strtotime($note->inserted)), // mysql server timezone must be in America/New_York
				"likes" => $note->likes
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
			$connection->deepQuery("INSERT IGNORE INTO _pizarra_block (email, blocked) VALUES ('$person','$friend')");
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
			$res = $connection->deepQuery("SELECT * FROM _pizarra_follow WHERE email='$person' AND followed='$friend'");

			// delete if exists
			if(count($res) > 0) $sql = "DELETE FROM _pizarra_follow WHERE email='$person' AND followed='$friend'";
			// insert if does not exist
			else $sql = "INSERT INTO _pizarra_follow (email, followed) VALUES ('$person','$friend');";

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
}