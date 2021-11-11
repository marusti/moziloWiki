/*
 * @objDisplayToggle = kann ein string oder ein DOM-Objekt sein
 * @objTextToggle = kann ein string oder ein DOM-Objekt sein
 * @showText = Text der angeizeigt werden soll, wenn das objDisplayToggle sichtbar ist
 * @hideText = Text der angeizeigt werden soll, wenn das objDisplayToggle versteckt ist
 *
 * toggle("ID des Objekts oder Objekt selber was angezeigt/versteckt werden soll"[ , "ID des Objekts oder Objekt selber bei dem sicher der Text �ndern soll", "Text der angezeigt werden soll wenn das Objekt sichtbar", "Text der angezeigt wird wenn das objekt versteckt ist"]);
 */
function toggle(objDisplayToggle, objTextToggle, showText, hideText) {
	// Falls die �bergebenen objekte strings sind wird angenommen das es sich um die ID des Elements handelt
	if (typeof(objDisplayToggle) == "string") {
		objDisplayToggle = document.getElementById(objDisplayToggle);
	}
	if (typeof(objTextToggle) == "string") {
		objTextToggle = document.getElementById(objTextToggle);
	}

	// Fehler abfangen falls objDisplayToggle nicht gefunden wurde
	if (objDisplayToggle == null) {
		alert("Das zu �ndernde Objekt wurde nicht gefunden.");
		return;
	}
	
	// Sichtbarkeit des objDisplayToggle �ndern
	if (objDisplayToggle.style.display == "none") { // objDisplayToggle Sichtbar
		// Definition Inlineelemente (String muss mit | beginnen und enden)
		var inline = "|a|abbr|acronym|applet|b|basefont|bdo|big|br|button|cite|code|del|dfn|em|font|i|img|ins|input|iframe|kbd|label|map|object|q|samp|script|select|small|span|strong|sub|sup|textarea|tt|var|";
		if (inline.indexOf("|"+objDisplayToggle.nodeName.toLowerCase()+"|") >= 0) {
			objDisplayToggle.style.display = "inline"; // objDisplayToggle als Inline-Element anzeigen
		} else {
			objDisplayToggle.style.display = "block"; // objDisplayToggle als Block-Element anzeigen		
		}		
	} else {
		objDisplayToggle.style.display = "none"; // objDisplayToggle verstecken
	}

	// Wenn ein objTextToggle angegeben wurde
	if (objTextToggle != null) {
		if (objDisplayToggle.style.display == "none") {
			objTextToggle.replaceChild( document.createTextNode(hideText) , objTextToggle.firstChild ); // Text hideText anzeigen, das das obj unsichtbar ist	
		} else {
			objTextToggle.replaceChild( document.createTextNode(showText) , objTextToggle.firstChild ); // Text showText anzeigen, da das obj sichtbar ist
		}
	}
}

function insert(aTag, eTag) {
  var input = document.forms['form'].elements['content'];
  input.focus();
  /* f�r Internet Explorer */
  if(typeof document.selection != 'undefined') {
    /* Einf�gen des Formatierungscodes */
    var range = document.selection.createRange();
    var insText = range.text;
    range.text = aTag + insText + eTag;
    /* Anpassen der Cursorposition */
    range = document.selection.createRange();
    if (insText.length == 0) {
      range.move('character', -eTag.length);
    } else {
      range.moveStart('character', aTag.length + insText.length + eTag.length);      
    }
    range.select();
  }
  /* f�r neuere auf Gecko basierende Browser */
  else if(typeof input.selectionStart != 'undefined')
  {
    /* Einf�gen des Formatierungscodes */
    var start = input.selectionStart;
    var end = input.selectionEnd;
    var insText = input.value.substring(start, end);
    input.value = input.value.substr(0, start) + aTag + insText + eTag + input.value.substr(end);
    /* Anpassen der Cursorposition */
    var pos;
    if (insText.length == 0) {
      pos = start + aTag.length;
    } else {
      pos = start + aTag.length + insText.length + eTag.length;
    }
    input.selectionStart = pos;
    input.selectionEnd = pos;
  }
  /* f�r die �brigen Browser */
  else
  {
    /* Abfrage der Einf�geposition */
    var pos;
    var re = new RegExp('^[0-9]{0,3}$');
    while(!re.test(pos)) {
      pos = prompt("Einf�gen an Position (0.." + input.value.length + "):", "0");
    }
    if(pos > input.value.length) {
      pos = input.value.length;
    }
    /* Einf�gen des Formatierungscodes */
    var insText = prompt("Bitte geben Sie den zu formatierenden Text ein:");
    input.value = input.value.substr(0, pos) + aTag + insText + eTag + input.value.substr(pos);
  }
}