<?php
/*

		moziloWiki - CSV.php
		
		A class for handling csv files. Feel free to
		change it to your personal purposes.
		
		Arvid Zimmermann 2006 <moziloWiki@azett.com>
		
*/

class CSV {

	var $file = "";
	var $csv = array();
	var $sep = ",";

	//Constructor
	function __construct($file) {
		if ($file == "")
			die("CSV: No file given");
		$this->file = $file;
		$this->loadCSV();
	}

	// Load CSV from a file
	function loadCSV() {
		$handle = fopen($this->file, "r");
		if (filesize($this->file) == 0)
			$filesize = 1;
		else
			$filesize = filesize($this->file);
	  $content = fread($handle, $filesize);
	  fclose($handle);
		$this->csv = explode($this->sep, $content);
	}

	/// Save CSV to a file
	function saveCSV() {
   	if (!$file = @fopen($this->file, "w")) 
    	die("CSV.php: Could not write to $this->file");
    @flock($file,2);
    $content = "";
    // sort
    if (!$this->csv == null)
   		asort($this->csv);
    // read...
		foreach ($this->csv as $value) {
			if ($value <> "")
				$content .= "$value$this->sep";
		}
		// delete last separator
		$content = substr($content, 0, strlen($content)-strlen($this->sep));
		// ...and save
		fputs($file, $content);
		@flock($file,3);
		fclose($file);        
	}

	// add value
	function add($value) {
		if ($value != "") {
			array_push ($this->csv, $value);
			$this->saveCSV();
		}
	}

	// delete value
	function delete($deletevalue) {
		if ($deletevalue == "")
			return false;
		$counter = 0;
		$success = false;
		foreach ($this->csv as $value) {
			if ($value == $deletevalue) {
				unset($this->csv[$counter]);
				$success = true;
			}
			$counter++;
		}
		if (!$success)
			return false;
		$this->saveCSV();
		return true;
	}

	// return existance of element
	function exists($existsvalue) {
		if ($existsvalue != "") {
			foreach ($this->csv as $value) {
				if ($value == $existsvalue)
					return true;
			}
		}
		return false;
	}

	// get the internal PHP array
	function toArray() {
		return $this->csv;
	}

	// get size of the internal PHP array
	function count() {
		return count($this->csv);
	}

	// set CSV from an array
	function setFromArray($values) {
		$this->csv = $values;
		$this->saveCSV();
	}
}

?>
