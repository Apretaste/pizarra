<?php

// include the Twitter library
use Abraham\TwitterOAuth\TwitterOAuth;

class Pizarra extends Service
{
	// some very secret keys that I should not put in this slides
	private $KEY = "fBAjPSxevxomufUKi55Ppm1mU";
	private $KEY_SECRET = "19Eq98kSkHejFl1PNJREL723q8shAp8vltQlPpUsEovdZ2UB2O";
	private $TOKEN = "4247250736-g7UbSlqkxxy1L5pJoAPimgtbuPo2RelwkihJrHf";
	private $TOKEN_SECRET = "wguacd2XwTWlppVlz3aGrTkFleJBDiOai7wzX2LV5Czm7";

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
			// add the user to the database if he/she does not exist
			$res = $connection->deepQuery("SELECT email, penalized_until FROM __pizarra_users WHERE email = '$email'");
			if(count($res) == 0) $this->newUserFromEmail($email);

			// do not post if the user is penalized
			if(count($res)>0 && time() < strtotime($res[0]->penalized_until))
			{
				$date = date("d/m/Y h:i:s A", strtotime($res[0]->penalized_until));
				$response = new Response();
				$response->setResponseSubject("Usted esta penalizado");
				$response->createFromText("Una cantidad significativa de usuarios han reportado sus notas como de mal gusto u ofensivas, y hemos derogado temporalmente su privilegio de publicar.</p><p>Usted esta penalizado hasta el <b>$date</b>. Sentimos las molestias, y de coraz&oacute;n le agradecemos su entendimiento y futura cooperaci&oacute;n manteniendo limpia la pizarra. Si cree que esta medida fue tomada por error, por favor escr&iacute;bamos a soporte@apretaste.com</p>");
				return $response;
			}

			// save note to the database
			$text = substr($request->query, 0, 130);
			$text = $connection->escape($text);
			$connection->deepQuery("INSERT INTO __pizarra_notes (email, text) VALUES ('$email', '$text')");

			// get the user from the database
			$res = $connection->deepQuery("SELECT user FROM __pizarra_users WHERE email = '$email'");
			$user = $res[0]->user;

			// post in tweeter
			$res = $twitter->post("statuses/update", array("status"=>"$user~> $text"));
//			print_r($res); exit; // check errors posting in twitter

			// create the response
			$response = new Response();
			$responseContent = array("message" => "Hemos escrito su nota en la pizarra. Usted es ahora parte de la conversaci&oacute;n.");
			$response->setResponseSubject("Su nota se ha escrito en la pizarra");
			$response->createFromTemplate("message.tpl", $responseContent);
			return $response;
		}

		// get the latest 100 tweets
		$listOfTweets = $twitter->get("search/tweets", array("q"=>"#heycuba", "count"=>"100"));

		// format the array of tweets
		$tweets = array();
		foreach ($listOfTweets->statuses as $tweet)
		{
			// no need to start with #HeyCuba
			$text = $tweet->text;
			if(stripos($text, '#HeyCuba')===0) $text = substr($text, 9);

			// do not show heycuba as name
			$name = ($tweet->user->name == "HeyCuba!") ? "Apretaste" : $tweet->user->name;

			$tweets[] = array(
				"id" => "",
				"email" => "",
				"name" => $name,
				"location" => $tweet->user->location,
				"gender" => "",
				"picture" => "",
				"text" => $text,
				"inserted" => $this->GmtTimeToLocalTime($tweet->created_at),
				"likes" => "",
				"source" => "twitter"
			);
		}

		// get the last 100 records from the db
		$listOfNotes = $connection->deepQuery(
			"SELECT A.*, C.user, B.first_name, B.last_name, B.province, B.picture, B.gender
			FROM __pizarra_notes A
			LEFT JOIN person B
			ON A.email = B.email
			LEFT JOIN __pizarra_users C
			ON A.email = C.email
			ORDER BY inserted DESC 
			LIMIT 100");

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
				"name" => $note->user,
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

		// get only the first 100 posts
		$pizarra = array_slice($pizarra, 0, 100);

		// highlight hash tags
		for ($i=0; $i<count($pizarra); $i++)
		{ 
			$pizarra[$i]['text'] = ucfirst(strtolower($pizarra[$i]['text'])); // fix case
			$pizarra[$i]['text'] = $this->highlightHashTags($pizarra[$i]['text']);
		}

		// create variables for the template
		$responseContent = array(
			"email" => $email,
			"editProfileText" => $this->utils->createProfileEditableText($email),
			"isProfileIncomplete" => $this->utils->getProfileCompletion($email) < 70,
			"notes" => $pizarra
		);

		// create the response
		$response = new Response();
		$response->setResponseSubject("Ultimas 100 notas");
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
		// add one to the reports counter, or set up a penalization for 3 days if the counter gets to 5
		$email = $request->query;
		$sql = 
			"START TRANSACTION;
			UPDATE __pizarra_users SET reports=reports+1 WHERE email = '$email' AND penalized_until < CURRENT_TIMESTAMP;
			UPDATE __pizarra_users SET penalized_until = NOW() + INTERVAL 3 DAY, reports = 0 WHERE email = '$email' AND reports > 4;
			COMMIT;";
		$connection = new Connection();
		$connection->deepQuery($sql);

		// create the response
		$response = new Response();
		$responseContent = array("message" => 'Gracias por reportarnos este usuario. Vamos a revisar sus notas, y en caso ser ofensivas o de mal gusto tomaremos una desici&oacute;n.</p><p>Sea tolerante. Muchos usuarios escriben sobre su credo, orientaci&oacute;n sexual, pensamiento pol&iacute;tico, diferencia racial o cultural, lo cual no significa que sus notas sean de mal gusto solo porque otros no est&eacute;n de acuerdo.');
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
		$res = $connection->deepQuery("UPDATE __pizarra_notes SET likes=likes+1 WHERE id=$id");

		// create the response
		$response = new Response();
		$responseContent = array("message" => 'Hemos agregado un coraz&oacute;n al post, lo cual adem&aacute;s se reflejar&aacute; en la reputaci&oacute;n de quien lo escribi&oacute;. &iexcl;Gracias por compartir su opini&oacute;n!');
		$response->setResponseSubject("Gracias por el reporte");
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
	 * Creates a new user from the email and save it to the database
	 * 
	 * @author salvipascual
	 * @param String, email
	 * @return String, user
	 * */
	private function newUserFromEmail($email)
	{
		$user = explode("@", $email)[0]; // get only the name part
		$user = preg_replace('/[^A-Za-z0-9]/', '', $user); // remove special chars
		$user = substr($user, 0, 5); // get the first 5 chars

		$connection = new Connection();

		// check if the user already exist and a number after if it exist
		$res = $connection->deepQuery("SELECT user FROM __pizarra_users WHERE user LIKE '$user%'");
		if(count($res) > 0) $user = $user . count($res);

		// save the new user
		$connection->deepQuery("INSERT INTO __pizarra_users (email,user) VALUES ('$email','$user')");
	}

	/**
	 * Convert from UTC to local time
	 * 
	 * @author stackoverflow
	 * @param String datetime
	 * @return String datetime
	 * */
	private function GmtTimeToLocalTime($time)
	{
		date_default_timezone_set('UTC');
		$new_date = new DateTime($time);
		$new_date->setTimeZone(new DateTimeZone('America/New_York'));
		return $new_date->format("Y-m-d H:i:s");
	}
}