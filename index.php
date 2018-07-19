<?php

$parts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));

if (count($parts) != 3)
{
	header("HTTP/1.1 400 Bad Request");
	exit("Invalid request: too few arguments");
}

$source = array_shift($parts);

if ($source == "itunes")
{
	$countries = require_once "../lib/country.php";

	$id = $parts[0];
	$country = $parts[1];

	if (!is_numeric($id))
	{
		header("HTTP/1.1 404 Bad Request");
		exit("iTunes ID must be numeric.");
	}

	$headers = getallheaders();

	$url = "https://itunes.apple.com/" . $country . "/rss/customerreviews/id=" . $id . "/sortBy=mostRecent/xml?l=en";

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('User-Agent: ' . $headers['User-Agent']));

	$output = curl_exec($ch);

	header("HTTP/1.1 " . curl_getinfo($ch, CURLINFO_HTTP_CODE));
	header("Content-Type: " . curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
	header("Content-Length: " . curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD));

#	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);

	// Replace title with country name
	$output = preg_replace("/<title>iTunes Store(.*?)<\/title>/u", "<title>" . $countries[strtoupper($country)] . "</title>", $output);

	// Remove first iTunes metadata entry
	$output = preg_replace("/<entry>(.*?)<\/entry>/us", "", $output, 1);

	// Get the ratings from each entry
	preg_match_all("/<im:rating>([0-5])<\/im:rating>/um", $output, $ratings);

	// Add rating to entry titles
	define('STAR_CHAR', '★');
	$i = 0;
	function titleReplace($matches) {
		global $ratings, $i;
		$newTitle = "<title>" . str_repeat(STAR_CHAR, $ratings[1][$i++]) . " " . $matches[1] . "</title>";
		return str_replace("<title>" . $matches[1] . "</title>", $newTitle, $matches[0]);
	}
	$output = preg_replace_callback("/<entry>(?:.*?)<title>(.*?)<\/title>(?:.*?)<\/entry>/us", titleReplace, $output);

	echo $output;
}
else
{
	header("HTTP/1.1 404 Not Found");
	exit();
}

