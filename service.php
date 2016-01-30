<?php

// include the Twitter library
use Abraham\TwitterOAuth\TwitterOAuth;

class Pizarra extends Service
{
	private $KEY = "nXbz7LXFcKSemSb9v2pUh5XWV";
	private $KEY_SECRET = "kjSF6NOppBgR3UsP4u9KjwavrLUFGOcWEeFKmcWCZQyLLpOWCm";
	private $TOKEN = "4247250736-LgRlKf0MgOLQZY6VnaZTJUKTuDU7q0GefcEPYyB";
	private $TOKEN_SECRET = "WXpiTky2v9RVlnJnrwSYlX2BOmJqv8W3Sfb1Ve61RrWa3";

	/**
	 * Function executed when the service is called
	 * 
	 * @param Request
	 * @return Response
	 * */
	public function _main(Request $request)
	{
		if($request->query == "reemplace este texto por su nota")
		{
			$response = new Response();
			$responseContent = array("message" => 'Para que podamos escribir su nota, &iexcl;Usted primero debe escribirla!</p><p>Por favor presione el bot&oacute;n m&oacute;s abajo y reemplace en el asunto del email donde dice <b>"reemplace este texto por su nota"</b> con el texto a escribir e intente nuevamente.');
			$response->setResponseSubject("No nos ha enviado ninguna nota!");
			$response->createFromTemplate("message.tpl", $responseContent);
			return $response;
		}

		// connect to Twitter
		$twitter = new TwitterOAuth($this->KEY, $this->KEY_SECRET, $this->TOKEN, $this->TOKEN_SECRET);

		// connect to the database
		$connection = new Connection();

		// post whatever the user types
		$email = $request->email;
		if( ! empty($request->query))
		{
			$responses = array();

			// do not post if the user is penalized
			$res = $connection->deepQuery("SELECT email, penalized_until FROM _pizarra_users WHERE email = '$email'");
			if(count($res)>0 && time() < strtotime($res[0]->penalized_until))
			{
				$date = date("d/m/Y h:i:s A", strtotime($res[0]->penalized_until));
				$response = new Response();
				$response->setResponseSubject("Usted esta penalizado");
				$response->createFromText("Una cantidad significativa de usuarios han reportado sus notas como de mal gusto u ofensivas, y hemos derogado temporalmente su privilegio de publicar.</p><p>Usted esta penalizado hasta el <b>$date</b>. De coraz&oacute;n le agradecemos su entendimiento y futura cooperaci&oacute;n manteniendo limpia la pizarra. Si cree que esta medida fue tomada por error, por favor escr&iacute;bamos a soporte@apretaste.com</p>");
				return $response;
			}

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
				if($mention[0] == $user) continue;

				// save the list of users mentioned
				$usersMentioned .= "@".$mention[0].", ";

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
			$twitter->post("statuses/update", array("status"=>"$user~> $text"));

			// create the response
			$response = new Response();
			$textMentioned = empty($usersMentioned) ? "" : "<br/><br/>Hemos compartido esta nota con " . rtrim($usersMentioned, ", ");
			$responseContent = array("message" => "Hemos escrito su nota en la pizarra. Usted es ahora parte de la conversaci&oacute;n. $textMentioned");
			$response->setResponseSubject("Su nota se ha escrito en la pizarra");
			$response->createFromTemplate("message.tpl", $responseContent);
			$responses[] = $response;
			return $responses;
		}

		// get the latest 50 tweets
		$listOfTweets = $twitter->get("search/tweets", array("q"=>"#heycuba", "count"=>"50"));

		// format the array of tweets
		$tweets = array();
		foreach ($listOfTweets->statuses as $tweet)
		{
			// no need to start with #HeyCuba
			$text = $tweet->text;
			if(stripos($text, '#HeyCuba')===0) $text = substr($text, 9);

			// do not show heycuba as name
			$name = ($tweet->user->name == "HeyCuba!") ? "Apretaste" : $tweet->user->name;

			$dateInEST = new DateTime($tweet->created_at);
			$dateInEST->setTimeZone(new DateTimeZone('America/New_York'));
			$dateInEST = $dateInEST->format("Y-m-d H:i:s");

			$tweets[] = array(
				"id" => "",
				"email" => "",
				"name" => $name,
				"location" => $tweet->user->location,
				"gender" => "",
				"picture" => "",
				"text" => $text,
				"inserted" => $dateInEST,
				"likes" => "",
				"source" => "twitter"
			);
		}

		// get the last 50 records from the db
		$listOfNotes = $connection->deepQuery(
			"SELECT A.*, B.username, B.first_name, B.last_name, B.province, B.picture, B.gender
			FROM _pizarra_notes A
			LEFT JOIN person B
			ON A.email = B.email
			ORDER BY inserted DESC 
			LIMIT 50");

		// format the array of notes
		$notes = array();
		foreach ($listOfNotes as $note)
		{
			// get the name 
			$name = trim("{$note->first_name} {$note->last_name}");
			if(empty($name)) $name = $note->email;

			// get the location
			if(empty($note->province)) $location = "Cuba";
			else $location = ucwords(strtolower(str_replace("_", " ", $note->province)));

			// add the text to the array
			$notes[] = array(
				"id" => $note->id,
				"email" => $note->email,
				"name" => $note->username,
				"location" => $location,
				"gender" => $note->gender,
				"picture" => $note->picture,
				"text" => $note->text,
				"inserted" => date("Y-m-d H:i:s", strtotime($note->inserted)), // mysql server timezone must be in America/New_York 
				"likes" => $note->likes,
				"source" => "apretaste"
			);
		}

		// merge all data
		$pizarra = array_merge($tweets, $notes);
		
		// sort the final array by post date
		usort($pizarra, function($a, $b) {
			return $a['inserted'] < $b['inserted'];
		});

		// get only the first 50 posts
		$pizarra = array_slice($pizarra, 0, 50);

		// highlight hash tags
		for ($i=0; $i<count($pizarra); $i++)
		{ 
			$pizarra[$i]['text'] = ucfirst(strtolower($pizarra[$i]['text'])); // fix case
			$pizarra[$i]['text'] = $this->highlightHashTags($pizarra[$i]['text']);
		}

		// create variables for the template
		$responseContent = array(
			"email" => $email,
			"isProfileIncomplete" => $this->utils->getProfileCompletion($email) < 70,
			"notes" => $pizarra
		);

		// create the response
		$response = new Response();
		$response->setResponseSubject("Ultimas 50 notas");
		$response->createFromTemplate("basic.tpl", $responseContent);
		return $response;
	}

	/**
	 * Function executed when a user reports another user
	 *
	 * @param Request
	 * @return Response
	 * */
	public function _reportar(Request $request)
	{
		// get the email
		$connection = new Connection();
		$username = trim(strtolower(str_replace("@", "", $request->query)));
		$email = $connection->deepQuery("SELECT email FROM person WHERE username = '$username'");
		$email = empty($email) ? "" : $email = $email[0]->email;

		// add one to the reports counter, or set up a penalization for 3 days if the counter gets to 5
		$sql = 
			"START TRANSACTION;
			UPDATE _pizarra_users SET reports=reports+1 WHERE email='$email' AND penalized_until < CURRENT_TIMESTAMP;
			UPDATE _pizarra_users SET penalized_until=NOW() + INTERVAL 3 DAY, reports=0 WHERE email='$email' AND reports > 4;
			COMMIT;";
		$connection->deepQuery($sql);

		// create the response
		$response = new Response();
		$responseContent = array("message" => "Gracias por reportarnos a @$username. Vamos a revisar sus notas, y en caso ser ofensivas o de mal gusto tomaremos medidas.</p><p>Sea tolerante. Muchos usuarios escriben sobre su credo, orientaci&oacute;n sexual, pensamiento pol&iacute;tico, diferencia racial o cultural, lo cual no significa que sus notas sean de mal gusto solo porque otros no est&eacute;n de acuerdo.");
		$response->setResponseSubject("Gracias por el reporte");
		$response->createFromTemplate("message.tpl", $responseContent);
		return $response;
	}

	/**
	 * Function executed when a user likes a note
	 *
	 * @param Request
	 * @return Response
	 * */
	public function _like(Request $request)
	{
		// add one to the likes for that post
		$id = $request->query;
		$connection = new Connection();
		$res = $connection->deepQuery("UPDATE _pizarra_notes SET likes=likes+1 WHERE id='$id'");

		// create the response
		$response = new Response();
		$responseContent = array("message" => 'Hemos agregado un coraz&oacute;n al post, lo cual adem&aacute;s se reflejar&aacute; en la reputaci&oacute;n de quien lo escribi&oacute;. &iexcl;Gracias por compartir su opini&oacute;n!');
		$response->setResponseSubject("Gracias por el hacer like");
		$response->createFromTemplate("message.tpl", $responseContent);
		return $response;
	}

	/**
	 * Highlight words with a #hashtag
	 * 
	 * @param String $text
	 * @return String
	 * */
	private function highlightHashTags($text)
	{
		return preg_replace_callback(
			'/#\w*/', 
			function($matches){
				return "<b>{$matches[0]}</b>";
			}, 
			$text);
	}

	/**
	 * Find all mentions on a text
	 * 
	 * @param String $text
	 * @return Array, [[username,email],[username,email]...]
	 * */
	private function findUsersMentionedOnText($text)
	{
		// find all users mentioned
		preg_match_all('/@\w*/', $text, $matches);

		$return = array();
		if( ! empty($matches[0]))
		{
			// get string of possible matches
			$usernames = "'".implode("','", $matches[0])."'";
			$usernames = str_replace("@", "", $usernames);

			// check real matches agains the database
			$connection = new Connection();
			$users = $connection->deepQuery("SELECT email,username FROM person WHERE username in ($usernames)");

			// format the return
			foreach($users as $user)
			{
				$return[] = array($user->username, $user->email);
			}
		}
		return $return;
	}
}