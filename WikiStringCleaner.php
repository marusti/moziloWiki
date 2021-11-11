<?php

/*

		moziloWiki - WikiStringCleaner.php
		
		A useful addition to moziloWiki, which replaces special 
		characters like German umlauts. Feel free to change it to 
		your personal purposes.

		Arvid Zimmermann 2007 <moziloWiki@azett.com>
		
*/
require_once("Properties.php");
require_once("WikiGlobals.php");
require_once("WikiSettings.php");

class WikiStringCleaner {
	
	var $specialcharsarray;
	var $regexarray;
	var $replacementsarray;
	var $wikisettings;
	
	function __construct() {
		global $SPECIALCHARS_LIST;
		$specialchars = new Properties($SPECIALCHARS_LIST);
		$scarray = $specialchars->toArray();
		$this->specialcharsarray = array();
		$this->regexarray = array();
		$this->replacementsarray = array();
		$this->mainsettings = new WikiSettings();
		foreach($scarray as $sc => $re) {
			array_push($this->specialcharsarray, '/'.quotemeta($sc).'/');
			array_push($this->regexarray, quotemeta($sc));
			array_push($this->replacementsarray, $re);
		}
	}
	
// clean given String from any special characters
	function cleanThatString($string, $keepdots) {
		// replace all invalid special chars by underline
		$regex = "0-9A-Za-z\-";
		foreach($this->regexarray as $sc)
			$regex .= $sc;
		$string = preg_replace("/[^".$regex."\.]/i", "_", html_entity_decode($string));

		// replace valid special chars by their replacements
		$string = preg_replace($this->specialcharsarray, $this->replacementsarray, $string);
	
		// replace dots
		if (!$keepdots)
			$string = str_replace(".", "-", $string);
		
		// return the whole cleaned string
		return $string;
	}

// shorten too long names
	function shortenName($name) {
		global $mainsettings;
		if (strlen($name) > $mainsettings->getShortenNameLength()) {
			if (strlen($name) > 5)
				$shorthalf = floor(($mainsettings->getShortenNameLength()-2) / 2);
			else
				$shorthalf = 1;
			return substr($name, 0, $shorthalf)."...".substr($name, strlen($name)-$shorthalf, $shorthalf);
		}
		else 
			return $name;
	}

// shorten too long links
	function shortenLink($link) {
		global $mainsettings;
		if (strlen($link) > $mainsettings->getShortenLinkLength()) {
			if (strlen($link) > 5)
				$shorthalf = floor(($mainsettings->getShortenLinkLength()-2)/2);
			else
				$shorthalf = 1;
			return substr($link, 0, $shorthalf)."...".substr($link, strlen($link)-$shorthalf, $shorthalf);
		}
		else 
			return $link;
	}


}
	
?>
