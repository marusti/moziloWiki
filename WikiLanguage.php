<?php

/*

		moziloWiki - WikiLanguage.php
		
		A language management for moziloWiki. Feel free to
		change it to your personal purposes.
		
		Arvid Zimmermann 2006 <moziloWiki@azett.com>
		
*/

require_once("Properties.php");
require_once("WikiGlobals.php");

class WikiLanguage {
	
	// the arrays which contain the user data
	var $language;

	// initialization: read users from files
	function __construct($languagefile) {
		global $DIR_LANGSETTINGS;
		if ($languagefile == ".prop")
			return;
		$this->language = new Properties($DIR_LANGSETTINGS.$languagefile);
	}
	
	// get value
	function get($key) {
		$value = $this->language->get($key);
		if ($value == "")
			return "NO LANGUAGE ENTRY FOUND - CHECK SETTINGS!";
		else
			return htmlentities($value);
	}
	// set value
	function set($key, $value) {
		return $this->language->set($key, $value);
	}
	

}
?>
