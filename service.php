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

		// post whatever the user types
		if( ! empty($request->query))
		{
			// add a new tweet
			$tweet = "#HeyCuba " . substr($request->query,0,131);
			$res = $twitter->post("statuses/update", array("status"=>$tweet));

			// create the response
			$response = new Response();
			$responseContent = array("message" => "Hemos escrito su nota en la pizarra. Usted es ahora parte de la conversaci&oacute;n. Gracias!");
			$response->setResponseSubject("Su nota ha sido escrita en la pizarra");
			$response->createFromTemplate("message.tpl", $responseContent);
			return $response;
		}

		// get the latest 100 tweets
		$content = $twitter->get("search/tweets", array("q"=>"@heycuba OR #heycuba", "count"=>"100"));

		// get the tweets from the array
		$tweets = array();
		foreach ($content->statuses as $tweet)
		{
			$text = $tweet->text;
			if(stripos($text, '#HeyCuba')===0) $text = substr($text, 9); // no need to start with #HeyCuba
			$text = str_replace("@", "", $text); // no need to mention twitter users
			$text = $this->highlightHashTags($text);
			$tweets[] = array("date"=>$tweet->created_at, "text"=>$text);
		}

		// create a json object to send to the template
		$responseContent = array(
			"tweets" => $tweets
		);

		// create the response
		$response = new Response();
		$response->setResponseSubject("Ultimas 100 notas");
		$response->createFromTemplate("basic.tpl", $responseContent);
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
}