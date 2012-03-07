<?php

require('settings.bible.php');

class Bible {

	var $dbhost 	= null;
	var $dbuser 	= null;
	var $dbpass 	= null;
	var $dbname 	= null;
	var $dbprefix 	= null;
	var $bible 		= null;
	var $db 		= null;

	function __construct() {

		global $dbhost,  $dbuser, $dbpass, $dbname, $dbprefix, $bible;
		$this->dbhost 	= $dbhost;
		$this->dbuser 	= $dbuser;
		$this->dbpass 	= $dbpass;
		$this->dbname 	= $dbname;
		$this->dbprefix = $dbprefix;
		$this->bible 	= $bible;
		$this->db 		= null;

		$this->db = mysql_connect($this->dbhost, $this->dbuser, $this->dbpass);
		mysql_select_db($this->dbname, $this->db);
	}

	function getBibles() {

		$sql = "SELECT * FROM " . $this->dbprefix . "bibles";
		$rs = mysql_query($sql, $this->db) or die(mysql_error());

		while ($row = mysql_fetch_assoc($rs)) {
			$bibles[] = $row;
		}
		return $bibles;
	}

	function getFullVerse($verse) {
		$verse = trim($verse);
		$verseArray = explode(' ', $verse);
		if ($verseArray[0] == 1 || $verseArray[0] == 2) {
			// Libro compuesto
			$bookString = $verseArray[0] . ' ' . $verseArray[1];
		} else {
			// Libro normal
			$bookString = $verseArray[0];
		}
		$secondPart = str_replace($bookString, '', $verse);
		$numbersArray = explode(':', $secondPart);
		$chapter = trim($numbersArray[0]);
		$verses = trim($numbersArray[1]);

		$versesArray = explode('-', $verses);
		if (count($versesArray) == 2) {
			$firstVerse = trim($versesArray[0]);
			$lastVerse = trim($versesArray[1]);
		} else {
			$firstVerse = trim($versesArray[0]);
			$lastVerse = trim($versesArray[0]);
		}

		$fullVerse = $bookString . '|' . $chapter . '|' . $firstVerse . '|' . $lastVerse;
		return $fullVerse;
	}

	function getVerseText($fullVerse) {

		$findArray = explode('|', $fullVerse);
		// Libro
		$sql = "SELECT * FROM " . $this->dbprefix . "books WHERE idBook = (SELECT idBook FROM bible_books_references WHERE UPPER(text)='" . mb_strtoupper(utf8_decode($findArray[0]), 'iso-8859-1') . "' LIMIT 0,1)";
		$rs = mysql_query($sql, $this->db) or die(mysql_error());

		if (!$rs) {
			return 'Error';
		} else {

			while ($row = mysql_fetch_assoc($rs)) {
				$books[] = $row;
			}

			if (count($books) == 1) {
				// Libro
				$book = $books[0];

				// Capitulo
				$chapter = $findArray[1];

				// Versiculos
				if ($findArray[2] != '' && $findArray[3] != '') {
					if ($findArray[2] == $findArray[3]) {
						// 1 solo versiculo
						$result = '<strong>' . $book['name'] . ' ' . $chapter . ':' . $findArray[2] . '</strong><br/>';
						$sql = "SELECT * FROM " . $this->dbprefix . "verses WHERE idBook=" . $book['idBook'] . " AND chapter=" . $chapter . " AND verse=" . $findArray[2];
					} else {
						// mas de 1 versiculo
						$result = '<strong>' . $book['name'] . ' ' . $chapter . ':' . $findArray[2] . '-' . $findArray[3] . '</strong><br/>';
						$sql = "SELECT * FROM " . $this->dbprefix . "verses WHERE idBook=" . $book['idBook'] . " AND chapter=" . $chapter . " AND (verse>=" . $findArray[2] . " AND verse<=" . $findArray[3] . ")";
					}
				} else {
					// todo el libro
					$result = '<strong>' . $book['name'] . ' ' . $chapter . '</strong><br/>';
					$sql = "SELECT * FROM " . $this->dbprefix . "verses WHERE idBook=" . $book['idBook'] . " AND chapter=" . $chapter;
				}

				$rs = mysql_query($sql, $this->db) or die(mysql_error());
				if (!$rs) {
					mail('daniel@aldeacms.com', 'error', 'SQL:' . $sql . '      Error:' . $this->db->ErrorMsg());
					return 'Error';
				} else {
					while ($row = mysql_fetch_assoc($rs)) {
						$verses[] = $row;
					}

					foreach ($verses as $verse) {
						$verse['text'] = str_replace('{\i', '', $verse['text']);
						$verse['text'] = str_replace('}', '', $verse['text']);
						$verse['text'] = str_replace('\par', '<br/>&nbsp;&nbsp;&nbsp;', $verse['text']);
						$verse['text'] = str_replace('{\cf6', '',$verse['text']);								 
						
						$result.="<strong>" . $verse['verse'] . "</strong> " . $verse['text'] . "<br/>";
					}
					return utf8_encode($result);
				}
			} elseif (count($books) == 0) {
				return 'Libro no encontrado';
			} else {
				return 'Devolvi&oacute; mas de un libro';
			}
		}
	}

	function validate() {
		if (isset($_GET['book'])) {
			return true;
		} else {
			return false;
		}
	}

	function prepareQuery() {
		$query = "";
		if (isset($_GET['book'])) {
			$query['book'] = $_GET['book'];
		}
		if (isset($_GET['chapter'])) {
			$query['chapter'] = $_GET['chapter'];
		}
		if (isset($_GET['verses'])) {
			$query['verses'] = $_GET['verses'];
		}

		return $query;
	}

	function getBooks() {
		$sql = "SELECT A.idBook id,A.name nombre, A.testament testamento, count(distinct(B.chapter)) capitulos FROM " . $this->dbprefix . "books A, " . $this->dbprefix . "verses B WHERE B.idBook = A.idBook GROUP BY A.idBook ORDER BY A.idBook ASC";
		$rs = mysql_query($sql, $this->db) or die(mysql_error());

		$xml="<?xml version=\"1.0\"?>";
		$xml.="<books>";
		while ($row = mysql_fetch_assoc($rs)) {
			$xml.="<book>";
			$xml.="<number>" . $row['id'] . "</number>";
			$xml.="<name><![CDATA[" . $row['nombre'] . "]]></name>";
			$xml.="<testament><![CDATA[" . $row['testamento'] . "]]></testament>";
			$xml.="<chapters><![CDATA[" . $row['capitulos'] . "]]></chapters>";
			$xml.="</book>";
		}
		$xml.="</books>";
		return utf8_encode($xml);
	}

}

function d($var) {
	echo '<pre style="font-size:11px;background:#eee;padding:10px;">';
	print_r($var);
	echo '</pre>';
}

?>