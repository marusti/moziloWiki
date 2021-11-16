<?php

/*

		moziloWiki - WikiSyntax.php
		
		The syntax conversion for moziloWiki. 
		Feel free to change it to your personal purposes.
		
		Arvid Zimmermann 2006 <moziloWiki@azett.com>
		
*/
	require_once("WikiGlobals.php");
	require_once("WikiSettings.php");
	require_once("WikiBacklinks.php");
	require_once("WikiStatistics.php");

	class WikiSyntax {

	var $DEFAULT_PAGESEXT;
	var $DIR_FILES;
	var $DIR_PAGES;
	var $GRP_ANONYMOUS;
	var $GRP_EDITCONTENT;
	var $PRINTERFRIENDLY;
	var $wikilanguage;
	var $wikistringcleaner;
	var $mainsettings;
	var $wikistats;
	var $wikibacklinks;
	var $anchorcounter;
	var $backtotoplink;

	function __construct($pagesext, $filesdir, $pagesdir, $isanonymous, $caneditcontent, $wikilanguage, $wikistringcleaner, $printerfriendly) {
		$this->DEFAULT_PAGESEXT 	= $pagesext;
		$this->DIR_FILES 					= $filesdir;
		$this->DIR_PAGES 					= $pagesdir;
		$this->GRP_ANONYMOUS			= $isanonymous;
		$this->GRP_EDITCONTENT 		= $caneditcontent;
		$this->wikilanguage 			= $wikilanguage;
		$this->wikistringcleaner 	= $wikistringcleaner;
		$this->mainsettings 			= new WikiSettings();
		$this->anchorcounter			= 1;
		$this->wikibacklinks			= new WikiBacklinks();
		$this->wikistats					= new WikiStatistics();
		// for normal page view, show "back to top" link in front of headlines; for print view, don't
		if ($printerfriendly)
			$this->backtotoplink		= "";
		else

if (isset($_GET['page'])) {
    $this->backtotoplink		= "<a href=\"#\" title=\"".$wikilanguage->get("LANG_BACKTOTOP")."\"><img src=\"pic/backtotopicon.gif\" alt=\"&uArr;\" /></a>";

}		
		
				}

// replace the wiki's syntax with HTML	
	function convertWikiSyntax($currentpage, $content, $firstrecursion) {
		global $DEFAULT_PAGESEXT;
		global $DIR_FILES;
		global $DIR_PAGES;
		global $wikilanguage;
		global $wikistringcleaner;

		if ($firstrecursion) {
			// content formatting
	    $content = htmlentities($content);
			$content = preg_replace("/&amp;#036;/Umsi", "&#036;", $content);
			$content = preg_replace("/&amp;#092;/Umsi", "&#092;", $content);
#			$content = preg_replace("/\^(.)/", "'&#'.ord('\\1').';'", $content);
			// at first: delete entry's backlinks
			$this->wikibacklinks->deletePageFromBacklinkValues($currentpage);
			// also: delete from file linkers
			$this->wikistats->deletePageFromLinkerValues($currentpage);
		}
		
		// preparing paragraph links: read all headlines
		preg_match_all("/\[(ueber[\d])\|([^\[\]]+)\]/", $content, $matches);
		$headlines = array();
		$headlines[0] = $wikilanguage->get("LANG_BACKTOTOP");

		$i = 0;
		foreach ($matches[0] as $match) {
			// ...save found headlines to array $headlines
			$attribute = $matches[1][$i];
			$value = $matches[2][$i];
			$headlines[$i+1] = $value;
			$i++;
		}
		
		// get all block elements in square brackets
		preg_match_all("/\[([\w|=]+)\|([^\[\]]+)\]/", $content, $matches);
		$i = 0;
		// replacement: for every "[...|...]"
		foreach ($matches[0] as $match) {
			// get attribute and value
			$attribute = $matches[1][$i];
			$value = $matches[2][$i];

// BLOCK
			// left aligned text
			if ($attribute == "links"){
				$content = str_replace ("$match", "<div style=\"text-align:left;\">".$value."</div>", $content);
			}

			// centered text
			elseif ($attribute == "zentriert"){
				$content = str_replace ("$match", "<div style=\"text-align:center;\">".$value."</div>", $content);
			}

			// justified text
			elseif ($attribute == "block"){
				$content = str_replace ("$match", "<div style=\"text-align:justified;\">".$value."</div>", $content);
			}

			// right aligned text
			elseif ($attribute == "rechts"){
				$content = str_replace ("$match", "<div style=\"text-align:right;\">".$value."</div>", $content);
			}

			// headline big
			elseif ($attribute == "ueber1"){
				$content = str_replace ("$match", "<h1 id=\"a".$this->anchorcounter."\">".$this->backtotoplink."$value</h1>", $content);
				$this->anchorcounter++;
			}

			// headline medium
			elseif ($attribute == "ueber2"){
				$content = str_replace ("$match", "<h2 id=\"a".$this->anchorcounter."\">".$this->backtotoplink."$value</h2>", $content);
				$this->anchorcounter++;
			}

			// headline small
			elseif ($attribute == "ueber3"){
				$content = str_replace ("$match", "<h3 id=\"a".$this->anchorcounter."\">".$this->backtotoplink."$value</h3>", $content);
				$this->anchorcounter++;
			}

			// list item, single-indented
			elseif ($attribute == "liste1"){
				$content = str_replace ("$match", "<ul><li>$value</li></ul>", $content);
			}

			// list item, double-indented
			elseif ($attribute == "liste2"){
				$content = str_replace ("$match", "<ul><ul><li>$value</li></ul></ul>", $content);
			}

			// list item, triple-indented
			elseif ($attribute == "liste3"){
				$content = str_replace ("$match", "<ul><ul><ul><li>$value</li></ul></ul></ul>", $content);
			}
			
			// code
			elseif ($attribute == "code"){
				$content = str_replace ("$match", "<div class=\"code\">".$value."</div>", $content);
			}
			
// INLINE
			// external link
			if ($attribute == "link"){
				// validate: protocol :// (username:password@) [(sub.)server.tld|ip] (:port) (subdirs|files)
						// protocol 						(http|https|ftp|gopher|telnet|mms|irc|skype|icq|steam|xfire):\/\/
						// username:password@		(\w)+\:(\w)+\@
						// (sub.)server.tld 		((\w)+\.)?(\w)+\.[a-zA-Z]{2,4}
						// ip (ipv4)						([\d]{1,3}\.){3}[\d]{1,3}
						// port									\:[\d]{1,5}
						// subdirs|files				(\w)+
			if (preg_match("/^(http|https|ftp|gopher|telnet|mms|irc|skype|icq|steam|xfire)\:\/\/((\w)+\:(\w)+\@)?[((\w)+\.)?(\w)+\.[a-zA-Z]{2,4}|([\d]{1,3}\.){3}[\d]{1,3}](\:[\d]{1,5})?((\w)+)?$/", $value))
					$content = str_replace ($match, "<a href=\"$value\" class=\"url\" title=\"".$wikilanguage->get("LANG_SHOWURL")."&quot;$value&quot;\" target=\"_blank\">".htmlentities($wikistringcleaner->shortenLink(html_entity_decode($value)))."</a>", $content);
				else
					$content = str_replace ($match, "<em class=\"deadlink\" title=\"".$wikilanguage->get("LANG_INVALIDURL")." &quot;$value&quot;\">".html_entity_decode($wikistringcleaner->shortenLink(html_entity_decode($value)))."</em>", $content);
			}

			// mail link
			elseif ($attribute == "mail"){
				// Überprüfung auf Validität
				if (preg_match("/^\w[\w|\.|\-]+@\w[\w|\.|\-]+\.[a-zA-Z]{2,4}$/", $value))
					$content = str_replace ($match, "<a class=\"mail\" title=\"".$wikilanguage->get("LANG_SHOWMAIL")." &quot;$value&quot;\" href=\"mailto:$value\">$value</a>", $content);
				else
					$content = str_replace ($match, "<em class=\"deadlink\" title=\"".$wikilanguage->get("LANG_INVALIDMAIL")." &quot;$value&quot;\">$value</em>", $content);
			}

			// entry link (validate entry's existance)
			elseif ($attribute == "eintrag"){
				$cleanedvalue = $this->wikistringcleaner->cleanThatString(html_entity_decode($value), false);
				if (file_exists($DIR_PAGES.$cleanedvalue.$DEFAULT_PAGESEXT)) {
					$content = str_replace ($match, "<a class=\"page\" title=\"".$wikilanguage->get("LANG_SHOWPAGE")." &quot;$cleanedvalue&quot;\" href=\"$cleanedvalue\">".str_replace('_', ' ', $cleanedvalue)."</a>", $content);
					// get links to other entries and save them as their backlink
					if ($currentpage <> "")
						$this->wikibacklinks->addBacklink($cleanedvalue, $wikistringcleaner->cleanThatString($currentpage, false));
				}
				elseif (!$this->GRP_EDITCONTENT)
					$content = str_replace("$match", "<a class=\"pending\" title=\"".$wikilanguage->get("LANG_NOSUCHPAGE")." &quot;$cleanedvalue&quot;\">$cleanedvalue</a>", $content);
				else
					$content = str_replace ($match, "<a class=\"pending\" title=\"".$wikilanguage->get("LANG_SHOWNEWPAGE")." &quot;$cleanedvalue&quot;\" href=\"$cleanedvalue\">$cleanedvalue</a>", $content);
			}

			// link to headline within this entry (paragraph link)
			elseif ($attribute == "absatz") {
				$pos = 0;
				foreach ($headlines as $headline) {
					if ($headline == $value) {
						if ($pos == 0)
							$content = str_replace("$match", "<a href=\"".htmlentities($_GET['page'])."#a$pos\" class=\"paragraph\" title=\"".$wikilanguage->get("LANG_BACKTOTOP")."\">$value</a>", $content);
						else
							$content = str_replace("$match", "<a href=\"".htmlentities($_GET['page'])."#a$pos\" class=\"paragraph\" title=\"".$wikilanguage->get("LANG_SHOWPARAGRAPH")." &quot;$value&quot;\">$value</a>", $content);
					}
					$pos++;
				}
				$content = str_replace ($match, "<em class=\"deadlink\" title=\"".$wikilanguage->get("LANG_INVALIDPARAGRAPH")." &quot;$value&quot;\">$value</em>", $content);
			}

			// file (validate existance)
			elseif ($attribute == "datei") {
				$cleanedvalue = $this->wikistringcleaner->cleanThatString(html_entity_decode($value), true);
				if ($this->GRP_ANONYMOUS)
					$content = str_replace ($match, "<em class=\"inactivemenupoint\" title=\"".$wikilanguage->get("LANG_FILEAFTERLOGIN")."\">$cleanedvalue</em>", $content);
				elseif (file_exists($DIR_FILES.$cleanedvalue)) {
					$content = str_replace ($match, "<a class=\"file\" title=\"".$wikilanguage->get("LANG_SHOWFILE")." &quot;$cleanedvalue&quot;\" href=\"download.php?file=$cleanedvalue\">$cleanedvalue</a> <a href=\"index.php?action=fileinfo&amp;file=$cleanedvalue\" title=\"".$wikilanguage->get("LANG_FILEINFO").": &quot;$cleanedvalue&quot;\"><img src=\"pic/fileinfoicon.gif\" alt=\"(i)\" /></a>", $content);
					$this->wikistats->addFileLinker($cleanedvalue, $currentpage);
				}
				else
					$content = str_replace ($match, "<em class=\"deadlink\" title=\"".$wikilanguage->get("LANG_NOSUCHFILE")." &quot;$cleanedvalue&quot;\">$cleanedvalue</em>", $content);
			}

			// image from files dir (validate existance)
			elseif ($attribute == "bild"){
				$cleanedvalue = $this->wikistringcleaner->cleanThatString(html_entity_decode($value), true);
				if (file_exists($DIR_FILES.$cleanedvalue))
					$content = str_replace ($match, "<img src=\"download.php?file=$cleanedvalue&amp;imagefile=true\" alt=\"".$wikilanguage->get("LANG_IMAGE")." $cleanedvalue\" />", $content);
				else
					$content = str_replace ($match, "<img src=\"$value\" alt=\"".$wikilanguage->get("LANG_IMAGE")." $value\" />", $content);
			}

			// left-floating image (validate existance)
			elseif ($attribute == "bildlinks"){
				$cleanedvalue = $this->wikistringcleaner->cleanThatString(html_entity_decode($value), true);
				if (file_exists($DIR_FILES.$cleanedvalue))
					$content = str_replace ($match, "<img src=\"download.php?file=$cleanedvalue&amp;imagefile=true\" class=\"leftcontentimage\" alt=\"".$wikilanguage->get("LANG_IMAGE")." $cleanedvalue\" />", $content);
				else
					$content = str_replace ($match, "<img src=\"$value\" class=\"leftcontentimage\" alt=\"".$wikilanguage->get("LANG_IMAGE")." $value\" />", $content);
			}

			// right-floating image (validate existance)
			elseif ($attribute == "bildrechts"){
				$cleanedvalue = $this->wikistringcleaner->cleanThatString(html_entity_decode($value), true);
				if (file_exists($DIR_FILES.$cleanedvalue))
					$content = str_replace ($match, "<img src=\"download.php?file=$cleanedvalue&amp;imagefile=true\" class=\"rightcontentimage\" alt=\"".$wikilanguage->get("LANG_IMAGE")." $cleanedvalue\" />", $content);
				else
					$content = str_replace ($match, "<img src=\"$value\" class=\"rightcontentimage\" alt=\"".$wikilanguage->get("LANG_IMAGE")." $value\" />", $content);
			}

			// bold text
			elseif ($attribute == "fett"){
				$content = str_replace ($match, "<em class=\"bold\">$value</em>", $content);
			}

			// italic text
			elseif ($attribute == "kursiv"){
				$content = str_replace ($match, "<em class=\"italic\">$value</em>", $content);
			}

			// bold-italic text
			elseif ($attribute == "fettkursiv"){
				$content = str_replace ($match, "<em class=\"bolditalic\">$value</em>", $content);
			}

			// underlined text
			elseif ($attribute == "unter"){
				$content = str_replace ($match, "<em class=\"underlined\">$value</em>", $content);
			}

			// HTML contents (check global settings)
			elseif ($attribute == "html") {
				if ($this->mainsettings->getUseHtmlTag() == "true")
					$content = str_replace ("$match", html_entity_decode($value), $content);
				else
					$content = str_replace ("$match", $value, $content);
			}
			
			// colored text
			elseif (substr($attribute,0,6) == "farbe=") {
				// check for correct hexadecimal value
				if (preg_match("/^([a-f]|\d){6}$/i", substr($attribute, 6, strlen($attribute)-6))) 
					$content = str_replace ("$match", "<em style=\"color:#".substr($attribute, 6, strlen($attribute)-6).";\">".$value."</em>", $content);
				else
					$content = str_replace ("$match", "<em class=\"deadlink\" title=\"".$wikilanguage->get("LANG_INVALIDHEXCOLOR")." &quot;".substr($attribute, 6, strlen($attribute)-6)."&quot;\">$value</em>", $content);
			}

			// attributes that can't be associated
			else
					$content = str_replace ($match, "<em class=\"deadlink\" title=\"".$wikilanguage->get("LANG_INVALIDSYNTAXATTRIB")." &quot;$attribute&quot;\">$value</em>", $content);

			$i++;
		}

		// blanks as indentions
		while (preg_match('/^  /Um', $content))
			$content = preg_replace('/^( +) ([^ ])/Um', '$1&nbsp;&nbsp;&nbsp;$2', $content);
		$content = preg_replace('/^ /Um', '&nbsp;&nbsp;&nbsp;', $content);
		// horizontal rulers
		$content = preg_replace('/\[----\](\r\n|\r|\n)?/m', '<hr />', $content);
		// line feeds
		$content = preg_replace('/\n/', '<br />', $content);
		// delete line feeds before and after block elements
		$content = preg_replace('/<\/ul>(\r\n|\r|\n)<br \/>/', "</ul>", $content);
		$content = preg_replace('/(<\/h[123]>)(\r\n|\r|\n)<br \/>/', "$1", $content);

		// recursion for further matches
		if ($i > 0)
			$content = $this->convertWikiSyntax($currentpage, $content, false);
			
		// return converted content
    return $content;
	}
}
?>
