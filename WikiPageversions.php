<?php

/*

		moziloWiki - WikiPageversions.php
		
		A lightweight version management for moziloWiki. 
		Feel free to change it to your personal purposes.
		
		Arvid Zimmermann 2006 <moziloWiki@azett.com>
		
*/

require_once("Properties.php");
require_once("WikiGlobals.php");

class WikiPageversions {

	var $wikipageversions;

	function __construct($dir) {
		if ($dir == "")
			die("\$dir == \"\"");
		if (!file_exists($dir))
			die("!file_exists($dir)");
		$this->wikipageversions = new Properties($dir."versions.conf");
	}

	// return the changer of a given time
	function getChangerOfTime($time, $page) {
		return $this->wikipageversions->get($page."_".$time);
	}

	// set the changer of a given time
	function setChangerOfTime($time, $page, $changer) {
		return $this->wikipageversions->set($page."_".$time, $changer);
	}
	
	// return pages with most older versions as array
	function getMostChanges() {
		global $DIR_PAGES;
		global $DEFAULT_PAGESEXT;
		$allpageversions = $this->wikipageversions->toArray();
		$pageversioncounts = array();
		$mostchangedpages = array();
		foreach ($allpageversions as $pageandtime => $changer) {
			$page = substr($pageandtime, 0, strlen($pageandtime)-strlen("_".time()));
			// not counted yet? add to array
			if (!array_key_exists($page, $pageversioncounts))
				$pageversioncounts[$page] = 1;
			// already counted? increase counter
			else
				$pageversioncounts[$page] = $pageversioncounts[$page] + 1;
		}
		arsort($pageversioncounts);
		$highestcount = 0;
		// go through array and add most changed pages (can be more than one at the same time)
		foreach ($pageversioncounts as $page => $count) {
			if (($count >= $highestcount) && (file_exists($DIR_PAGES.$page.$DEFAULT_PAGESEXT))) {
				$mostchangedpages[$page] = $count;
				$highestcount = $count;
			}
		}
		// sort and return
		uksort($mostchangedpages, 'strnatcasecmp');
		return $mostchangedpages;
	}
	
}
?>
