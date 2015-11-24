<?php

require('oauth-helpers.php');
require('oauth-keys.php');

/*  desc:    returns random item of array
    params:  arr (Array) the array
    returns: Object (the type of the random item)
*/
function choose ($arr) {
    return $arr[rand(0, count($arr) - 1)];
};

class Markov {
	var $source;
	// array of words known to start a sentence / phrase
	// needs to be an array because we want to capture nonuniques to preserve correct probabilities
	var $initials;
	// object whose keys are known to end a sentence / phrase
	// needs to be an object because we just need to know whether the word is terminal, not how many times it occurs
	var $terminals;
	// main dictionary
	var $stats;

	function init ($source) {
		$this->source = $source;
		$this->initials = array();
		$this->terminals = array();
		$this->stats = array();

		// for each item in the source array
		for ($i = 0; $i < count($source); $i++) {
			// break up into an array of words
			$words = split(' ', $source[$i]);
		    // record the first word of the sentence / phrase
		    array_push($this->initials, $words[0]);
		    // record the last word of the sentence / phrase
		    $this->terminals[$words[count($words) - 1]] = true;
		    // for each word in the sentence / phrase
		    for ($j = 0; $j < count($words) - 1; $j++) {
		        // record the word that follows it in the main dictionary
		        if ($this->stats[$words[$j]]) {
		            array_push($this->stats[$words[$j]], $words[$j + 1]);
		        } 
		        else {
		            $this->stats[$words[$j]] = array($words[$j + 1]);
		        }
		    }
		}
	}

	/*  desc:    generates a Markov chain 
	    params:  minLength (Number) the minumum number of words of the desired Markov chain
	    returns: String
	*/
	function generate ($minLength) {
		// choose starting word at random
		$word = choose($this->initials);
		// put into array
		$title = array($word);
		// loop over random words
		while ($this->stats[$word]) {
		    $nextWords = $this->stats[$word];
		    $word = choose($nextWords);
		    array_push($title, $word);
		    // if this word is known to end a sentence / phrase and the minimum string length has been met
		    if (count($title) > $minLength && $this->terminals[$word]) {
		        // exit
		        break;  
		    } 
		}
		// join by spaces
		$str = implode(' ', $title);
		// ensure that the randomly generated string doesn't exist in the source
		if (count($title) < $minLength || in_array($str, $this->source)) {
		    return $this->generate($minLength);
		}
		return $str;
	}
}

function address () {
    // allowed addresses
    $letters = array('Esplanade', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'Trash Fence');
    $letter = choose($letters);
    // random number 2 thru 10
    $hour = rand(2, 10);
    // random multiple of 5 from 0 to 55
    $minute = rand(0, 11) * 5;

    // add leading zero to minute if needed
    if (strlen(strval($minute)) === 1) {
        $minute = '0' . $minute;
    }

    return "$hour:$minute & $letter";
};

// Assert that the generated string is tweetable. Twitter imposes a 140 character limit per tweet 
// so this long string enforeces that the Markov'd string will fit inside the 140 character limit
function composeTweet () {
	$titles = file('data-titles.txt', FILE_IGNORE_NEW_LINES);
	$camps = file('data-camps.txt', FILE_IGNORE_NEW_LINES);

	$TWITTER_CHARACTER_LIMIT = 140;

	$markov = new Markov();
	$markov->init($titles);
	$title = $markov->generate(4);

	$markov = new Markov();
	$markov->init($camps);
	$camp = $markov->generate(4);

	$chain = "$title @ $camp (" . address() . ") #burningman #bot";

	if (strlen($chain >= $TWITTER_CHARACTER_LIMIT)) {
		// try again
		composeTweet();
	}
	else {
		return $chain;
	}
}

// echo composeTweet();

$url = "https://api.twitter.com/1.1/statuses/update.json";
$oauth = array( 'oauth_consumer_key' => $consumer_key,
                'oauth_nonce' => time(),
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_token' => $oauth_access_token,
                'oauth_timestamp' => time(),
                'oauth_version' => '1.0');
 
$base_info = buildBaseString($url, 'POST', $oauth);
// echo $base_info;
$composite_key = rawurlencode($consumer_secret) . '&' . rawurlencode($oauth_access_token_secret);
$oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
$oauth['oauth_signature'] = $oauth_signature;
 
// make requests
$header = array(buildAuthorizationHeader($oauth), 'Expect:');
$options = array( CURLOPT_HTTPHEADER => $header,
                  CURLOPT_POSTFIELDS => array('status' => composeTweet()), 
                  CURLOPT_HEADER => false,
                  CURLOPT_URL => $url,
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_SSL_VERIFYPEER => false);
 
$feed = curl_init();
curl_setopt_array($feed, $options);
$json = curl_exec($feed);
curl_close($feed);


?>