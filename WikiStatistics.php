<?php
/*

		moziloWiki - WikiStatistics.php
		
		A statistics management for moziloWiki. Feel free to
		change it to your personal purposes.
		
		Arvid Zimmermann 2006 <moziloWiki@azett.com>
		
*/

require_once("WikiGlobals.php");
require_once("Properties.php");

class WikiStatistics {
	
	// the arrays which contain the user data
	var $filestats;
	var $pagestats;
	var $userstats;

	// initialization: read users from files
	function __construct() {
		global $STATS_FILES;
		global $STATS_PAGES;
		global $STATS_USERS;
		$this->filestats = new Properties($STATS_FILES);
		$this->pagestats = new Properties($STATS_PAGES);
		$this->userstats = new Properties($STATS_USERS);
	}
	
	// get last changer of file
	function getLastFileChanger($file){
		global $LANG_NOUSERFOUND;
		$lastchanger = $this->filestats->get("$file.lastchanged");
		if ($lastchanger == "")
			$lastchanger = $LANG_NOUSERFOUND;
		return $lastchanger;
	}

	// get last changer of page
	function getLastPageChanger($page){
		global $LANG_NOUSERFOUND;
		$lastchanger = $this->pagestats->get("$page.lastchanged");
		if ($lastchanger == "")
			$lastchanger = $LANG_NOUSERFOUND;
		return $lastchanger;
	}

	// set last changer of file
	function setLastFileChanger($file, $userid){
		if (($file == "") || ($userid == ""))
			return false;
		$this->filestats->set("$file.lastchanged", $userid);
	}

	// set last changer of page
	function setLastPageChanger($page, $userid){
		if (($page == "") || ($userid == ""))
			return false;
		$this->pagestats->set("$page.lastchanged", $userid);
	}

	// delete last changer of file
	function deleteFileStats($file){
		echo "deleteFileStats($file)";
		$this->filestats->delete($file.".comment");
		$this->filestats->delete($file.".commentchanger");
		$this->filestats->delete($file.".downloads");
		$this->filestats->delete($file.".lastchanged");
		$this->filestats->delete($file.".linkers");
	}

	// delete last changer of page
	function deleteLastPageChanger($page){
		return $this->pagestats->delete($page.".lastchanged");
	}
	
	// get file linkers
	function getFileLinkers($file) {
		global $DIR_PAGES;
		global $DEFAULT_PAGESEXT;
		$linkersarray = explode(",", $this->filestats->get($file.".linkers"));
		foreach ($linkersarray as $linker) {
			if ($linker == "")
				unset($linkersarray[array_search($linker, $linkersarray)]);
			elseif(!file_exists($DIR_PAGES.$linker.$DEFAULT_PAGESEXT))
				$this->deleteFileLinker($file, $linker, $linkersarray);
		}
		natcasesort($linkersarray);
		return $linkersarray;
	}
	
	// set file linkers
	function setFileLinkers($file, $filelinkers) {
		$arraystring = "";
		foreach ($filelinkers as $filelinker)
			if ($filelinker <> "")
				$arraystring .= $filelinker.",";
		$this->filestats->set($file.".linkers", $arraystring);
	}

	// add single file linker
	function addFileLinker($file, $page) {
		$filelinkers = $this->getFileLinkers($file);
		// not listed? add
		if (!in_array($page, $filelinkers))
			array_push($filelinkers, $page);
		$this->setFileLinkers($file, $filelinkers);
	}
	
	// unset file linker from given array	
	function deleteFileLinker($file, $page, $filelinkers) {
		// listed? delete
		if(in_array($page, $filelinkers)) {
			unset($filelinkers[array_search($page, $filelinkers)]);
			$filelinkers = array_values($filelinkers);
		}
		$this->setFileLinkers($file, $filelinkers);
	}

	// delete a page from linkers
	function deletePageFromLinkerValues($page) {
		global $DIR_FILES;
		$dir = opendir(getcwd() . "/$DIR_FILES");
		while ($file = readdir($dir)) {
	  	if (($file <> ".") && ($file <> "..")) {
	    	$filelinkers = $this->getFileLinkers($file);
	    	$this->deleteFileLinker($file, $page, $filelinkers);
	    }
	  }
	}
	
	// get file comment
	function getFileComment($file) {
		return $this->filestats->get($file.".comment");
	}
	
	// set file comment
	function setFileComment($file, $value) {
		return $this->filestats->set($file.".comment", $value);
	}
	
	// get last file comment changer
	function getFileCommentChanger($file) {
		global $LANG_NOUSERFOUND;
		$lastchanger = $this->filestats->get($file.".commentchanger");
		if ($lastchanger == "")
			$lastchanger = $LANG_NOUSERFOUND;
		return $lastchanger;
	}
	
	// set last file comment changer
	function setFileCommentChanger($file, $userid) {
		if (($file == "") || ($userid == ""))
			return false;
		$this->filestats->set($file.".commentchanger", $userid);
	}
	
	// get file comment
	function getFileDownloadCount($file) {
		if ($this->filestats->get($file.".downloads") == 0)
			return "0";
		return $this->filestats->get($file.".downloads");
	}
	
	// set file comment
	function setFileDownloadCount($file, $value) {
		return $this->filestats->set($file.".downloads", $value);
	}
	
	// increase file's download counter
	function increaseFileDownloadCount($file) {
		$counter = $this->filestats->get($file.".downloads");
		if (($counter == null) || ($counter == ""))
			$this->filestats->set($file.".downloads", 1);
		else
			$this->filestats->set($file.".downloads", $counter+1);
	}
	
	function getMostLinkersArray($allpagesarray) {
		if (count($allpagesarray) == 0)
			return null;
		$alllinkers = array();
		$mostlinkers = array();
		foreach ($allpagesarray as $file => $timestamp) {
			$linkers = $this->getFileLinkers($file);
			foreach($linkers as $linker) {
				// not counted yet? add to array
				if (!array_key_exists($file, $alllinkers)) 
					$alllinkers[$file] = 1;
				// already counted? increase counter
				else
					$alllinkers[$file] = $alllinkers[$file] + 1;
			}
		}
		arsort($alllinkers);
		$highestcount = 0;
		// go through array and add most backlinked pages (can be more than one at the same time)
		foreach ($alllinkers as $file => $count) {
			if ($count >= $highestcount) {
				$mostlinkers[$file] = $count;
				$highestcount = $count;
			}
		}
		// sort and return
		uksort($mostlinkers, 'strnatcasecmp');
		return $mostlinkers;
	}
	
	// return array of shortest pages
	function getShortestAndLongestFile($dirtocheck) {
		$shortest = "";
		$longest = "";
		$shortestlength = 0;
		$longestlength = 0;
		$dir = opendir(getcwd() . "/$dirtocheck");
		while ($file = readdir($dir)) {
	  	if (($file <> ".") && ($file <> "..")) {
	  		$filesize = filesize("$dirtocheck$file");
	  		if(($filesize < $shortestlength) || ($shortestlength == 0)) {
	  			$shortest = $file;
	  			$shortestlength = $filesize;
	  		}
	  		if($filesize > $longestlength) {
	  			$longest = $file;
	  			$longestlength = $filesize;
	  		}
	    }
	  }
	  if ($shortest == "")
	  	return null;
	  else
			return array($shortest, $longest);
	}
	
}
?>
