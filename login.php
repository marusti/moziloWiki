<?php

/*

		moziloWiki - login.php
		
		A login page for moziloWiki, written by Marian Heddesheimer
		<http://www.heddesheimer.de/> and slightly adapted to
		moziloWiki by Arvid Zimmermann <moziloWiki@azett.com>. Feel 
		free to change it to your personal purposes.
		
*/

	// finish script even if user connection broke
	ignore_user_abort(true);

	// Initial: Fehlerausgabe unterdrücken, um Path-Disclosure-Attacken ins Leere laufen zu lassen
	@ini_set("display_errors", 0);

	require_once("WikiUsers.php");
	require_once("WikiGroups.php");
	require_once("WikiGlobals.php");
	require_once("WikiLanguage.php");
	require_once("WikiSettings.php");
	session_start();

	$wikiusers 				= new WikiUsers();
	$wikigroups 			= new WikiGroups();
	$wikisettings 		= new WikiSettings();
	$wikilanguage			= new WikiLanguage($wikisettings->getDefaultWikiLanguage().".prop");
	$wikistyle				= $wikisettings->getDefaultWikiStyle();

	$html = "<!DOCTYPE html>" ."\n"
	. "<html>" ."\n"
	. "<head>" ."\n"
	. "<meta charset=\"utf-8\">" ."\n"
   . "<meta name=\"viewport\" content=\"width = device-width, initial-scale = 1.0\" />" ."\n" 
	. "<link rel=\"stylesheet\" href=\"$MAINDIR_STYLES$wikistyle/$CSS_FILE\">" ."\n";
	// Logout?
	if (isset($_GET['logout'])) {
    // Session beenden und die Sessiondaten löschen
    if (isset($_SESSION['username']))
    	$wikiusers->setIsOnline($_SESSION['username'], "false");
    session_destroy();
    // Auch bei zerstörter Session, ist die Variable
    // $_SESSION noch vorhanden, bis die Seite im Browser
    // neu angezeigt wird. Daher muss auch diese Variable
    // gesondert zerstört werden.
    unset($_SESSION);
    if ($wikisettings->getAnonymousAccess() == "true")
    	header("location:index.php");
    else
    	session_start();
	}
	
	// Wurde das Anmeldeformular verschickt?
	// Dann die Zugangsdaten in der Funktion check_login() prüfen
	if  (isset($_POST['login'])) {
		if (check_login($_POST['username'], $_POST['password']))
	  	// überprüfen: ist der User online?
			if (!$wikiusers->isOnline($_SESSION['username']))
		     $_SESSION['login_okay'] = true;
	}
	
// Anmeldung erfolgreich
	if (isset($_SESSION['login_okay']) and $_SESSION['login_okay'])
		header("location:index.php?login=true&page=".$_POST['page']."&action=".$_POST['action']);
// Anmeldung fehlgeschlagen
	elseif (isset($_POST['login'])) {
		// Fehlversuche protokollieren
		if ($wikiusers->userExists($_POST['username'])) {
			$wikiusers->setFalseLoginCount($_POST['username'], $wikiusers->getFalseLoginCount($_POST['username'])+1);
			// User sperren, wenn Login zu oft fehlgeschlagen (nur, wenn User nicht eh schon gesperrt ist)
			if ($wikiusers->getFalseLoginCount($_POST['username']) >= $wikisettings->getFailLogins() && ($wikiusers->getIsBanned($_POST['username']) == 0)) {
				$wikiusers->setIsBanned($_POST['username'], time());
				$wikiusers->setBanTime($_POST['username'], $wikisettings->getFailLoginBanTime());
				$wikiusers->setFalseLoginCount($_POST['username'], "0");
			}
		}
		if ($wikisettings->getAnonymousAccess() == "true")
			header("location:index.php?login=true");
		else
			$html .= getContent($wikilanguage->get("LANG_LOGINFAILED"));
  }
  
// Keine erfolgreiche Anmeldung und noch kein
// Formular versandt? Dann wurde die Seite
// zum ersten Mal aufgerufen.
	else 
		$html .= getContent($wikilanguage->get("LANG_LOGIN"));
	
	$html .= "</body>" ."\n"
	."</html>";
	echo $html;

	// Die Funktion login_formular() zeigt das Formular mit
	// den Eingabefeldern an. Da dieses Formular oben zweimal
	// benötigt wurde (beim ersten Aufruf und bei fehlerhafter
	// Anmeldung), wird es in eine Funktion gepackt, die man
	// leicht mehrmals verwenden kann.
	function getContent($msg) {
		global $wikilanguage;
    $content = "<title>$msg</title>" ."\n"
    . "</head>" ."\n"
    . "<body>" ."\n"
    . "<div class=\"login-container\">" ."\n"
 #		. "<div class=\"login_bgtext_mozilo\">mozilo</div>"
 #		. "<div class=\"login_bgtext_wiki\">Wiki</div>"
 		. "<div class=\"login_loginbox login\">"
 		. "<img src=\"pic\mozilo-logo.png\" class=\"avatar\" alt=\"moziloCMS Logo\">"."\n"
 		. "<h3>$msg</h3>" ."\n"
 		. "<form name=\"loginform\" action=\"login.php\" method=\"post\">"
 		. "<input type=\"text\" class=\"menutext\" name=\"username\" placeholder=\"".$wikilanguage->get("LANG_USERNAME")."\" value=\"\" accesskey=\"l\" />"
    . "<input type=\"password\" class=\"menutext\" name=\"password\" placeholder=\"".$wikilanguage->get("LANG_PASSWORD")."\" value=\"\" />"
    . "<input type=\"submit\" class=\"menutext\" name=\"login\" value=\"".$wikilanguage->get("LANG_LOGIN")."\" />"
    . "<a href=\"http://wiki.mozilo.de\" target=\"_blank\" class=\"login_wikilink\" title=\"wiki.mozilo.de\">wiki.mozilo.de</a>"
    . "</form>"
    . "</div>"
    . "</div>";
    return $content;
}

// Die Funktion check_login prüft Benutzername und Passwort.
// Diese Funktion könnte man später mit weiteren Zugangsdaten
// erweitern. Am Besten wäre es, die Zugangsdaten aus
// einer Datenbank zu holen, da man hier die Benutzer
// flexibel verwalten kann, ohne jedes Mal den Code
// zu ändern.
	function check_login($username, $password) {
		global $wikiusers;
		if (($username == "") || ($password == ""))
			return false;
		// Abfrage username-password oder fullusername-password
		if ($password == $wikiusers->getPassword($username)) {
 			$_SESSION['username'] = $username;
			return true;
		}
		else if ($password == $wikiusers->getPassword($wikiusers->getIDByFullName($username))){
 			$_SESSION['username'] = $wikiusers->getIDByFullName($username);
			return true;
		}
    else
    	return false;
	}

// thx to Marian Heddesheimer

