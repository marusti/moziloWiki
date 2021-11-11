<?php

/*

		moziloWiki - WikiBacklinks.php
		
		A lightweight backlink management for moziloWiki. 
		Feel free to change it to your personal purposes.
		
		Arvid Zimmermann 2006 <moziloWiki@azett.com>
		
*/

require_once("Properties.php");
require_once("WikiGlobals.php");

class WikiBacklinks {

	var $separator;
	var $wikibacklinks;
	var $backlinkarray;

	function __construct() {
		global $WIKIMGT_BACKLINKS;
		$this->separator = ",";
		$this->wikibacklinks = new Properties($WIKIMGT_BACKLINKS);
		$this->backlinkarray = array();
	}

	// return the list of backlinks
	function getBacklinkList($page) {
		$array = explode($this->separator, $this->wikibacklinks->get($page));
		if ((count($array) == 1) && ($array[0] == ""))
			return array();
		natcasesort($array);
		return $array;
	}

	// set the list of backlinks
	function setBacklinkList($page, $list) {
		$backlinkstring = "";
		foreach ($list as $backlink)
			if ($backlink <> "")
				$backlinkstring .= $backlink.$this->separator;
		$backlinkstring = substr($backlinkstring, 0, strlen($backlinkstring)-strlen($this->separator));
		return $this->wikibacklinks->set($page, $backlinkstring);
	}

	// add a backlink to the list of backlinks
	function addBacklink($page, $backlink) {
		$this->backlinkarray = $this->getBacklinkList($page);
		if (!in_array($backlink, $this->backlinkarray))
			array_push($this->backlinkarray, $backlink);
		return $this->setBacklinkList($page, $this->backlinkarray);
	}

	// delete a page from the backlinks
	function deletePageFromBacklinkValues($page) {
		$propertiesarray = $this->wikibacklinks->toArray();
		foreach ($propertiesarray as $key => $value) {
			$valuesarray = explode($this->separator, $value);
			if (in_array($page, $valuesarray)) {
				unset($valuesarray[array_search($page, $valuesarray)]);
				$valuesarray = array_values($valuesarray);
				sort($valuesarray);
				$this->setBacklinkList($key, $valuesarray);
			}
		}
	}

	// delete a page's backlinks
	function deletePageFromBacklinkKey($page) {
		$this->wikibacklinks->delete($page);
		$this->deletePageFromBacklinkValues($page);
	}
	
	// return an array of orphaned pages
	function getOrphanesArray($allpagesarray) {
		global $DEFAULT_PAGESEXT;
		$array = array();
		if (count($allpagesarray) == 0)
			return null;
		foreach ($allpagesarray as $key=>$value)
			if (count($this->getBacklinkList(substr($key,0,strlen($key)-strlen($DEFAULT_PAGESEXT)))) == 0)
				$array[$key] = $value;
		uksort($array, "strnatcasecmp");
		return $array;
	}
	
	// return an array of pages with the most backlinks
	function getMostBacklinksArray($allpagesarray) {
		if (count($allpagesarray) == 0)
			return null;
		global $DEFAULT_PAGESEXT;
		$allbacklinks = array();
		$mostbacklinks = array();
		foreach ($allpagesarray as $key => $value) {
			$page = substr($key,0,strlen($key)-strlen($DEFAULT_PAGESEXT));
			$pagesbacklinks = $this->getBacklinkList($page);
			foreach($pagesbacklinks as $backlink) {
				// not counted yet? add to array
				if (!array_key_exists($page, $allbacklinks)) 
					$allbacklinks[$page] = 1;
				// already counted? increase counter
				else
					$allbacklinks[$page] = $allbacklinks[$page] + 1;
			}
		}
		arsort($allbacklinks);
		$highestcount = 0;
		// go through array and add most backlinked pages (can be more than one at the same time)
		foreach ($allbacklinks as $page => $count) {
			if ($count >= $highestcount) {
				$mostbacklinks[$page] = $count;
				$highestcount = $count;
			}
		}
		// sort and return
		uksort($mostbacklinks, 'strnatcasecmp');
		return $mostbacklinks;
	}

}
?>
