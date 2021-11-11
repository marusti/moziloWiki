<?php

/*

		moziloWiki - WikiSmileys.php
		
		A useful addition to moziloWiki, which replaces 
		Emoticons with graphical smileys. 
		Feel free to change it to your personal purposes.

		Arvid Zimmermann 2007 <moziloWiki@azett.com>
		
*/
require_once("Properties.php");
require_once("WikiGlobals.php");

class WikiSmileys {
	
	var $smileysarray;
    var $search = array("&",";","&amp&#059;","/","\\",":","!","'",'"','[',']','{','}','|');
    var $replace = array('&amp;','&#059;','&amp;','&#047;','&#092;','&#058;','&#033;','&apos;','&quot;','&#091;','&#093;','&#123;','&#125;','&#124;');

	
	function __construct() {
		global $SMILEYS_LIST;
		$smileys = new Properties($SMILEYS_LIST);
		$this->smileysarray = $smileys->toArray();
	}
	
	function replaceEmoticons($content) {
		foreach ($this->smileysarray as $icon => $emoticon) {
            $icon = trim($icon);
            $emoticon = str_replace($this->search,$this->replace,$emoticon);
            $emoticon = '<img src="pic/smileys/'.$icon.'.gif" alt="'.$emoticon.'" />';
            $content = str_replace(":".$icon.":",$emoticon,$content);
        }
		return $content;
	}
	
	function getSmileysArray() {
		return $this->smileysarray;
	}	
}

?>
