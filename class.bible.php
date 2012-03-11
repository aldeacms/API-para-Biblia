<?php

require('settings.bible.php');

class Bible {

	var $dbhost = null;
	var $dbuser = null;
	var $dbpass = null;
	var $dbname = null;
	var $dbprefix = null;
	var $bible = null;
	var $db = null;

	function __construct() {
		global $dbhost, $dbuser, $dbpass, $dbname, $dbprefix, $bible;

		$this->dbhost = $dbhost;
		$this->dbuser = $dbuser;
		$this->dbpass = $dbpass;
		$this->dbname = $dbname;
		$this->dbprefix = $dbprefix;
		$this->bible = $bible;
		$this->db = null;

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
						$verse['text'] = str_replace('{\cf6', '', $verse['text']);

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

	function checkVerse($verseString) {
		$verseString = utf8_decode($verseString);
		$verseString = trim($verseString);
		$pre = "";
		// si parte con 1, 2 o 3, excepcion
		if (substr($verseString, 0, 1) == 1 || substr($verseString, 0, 1) == 2 || substr($verseString, 0, 1) == 3) {
			// si el segundo es espacio, lo eliminamos y agregamos al pre
			if (substr($verseString, 1, 1) == " ") {
				$pre = substr($verseString, 0, 2);
				$verseString = substr($verseString, 2, strlen($verseString) - 1);
			} else {
				$pre = substr($verseString, 0, 1) . " ";
				$verseString = substr($verseString, 1, strlen($verseString) - 1);
			}
		}

		$verseArray = explode(' ', $verseString);
		// si hay mas de 2 espacios no corresponde
		if (count($verseArray) > 2) {
			$responseArray["status"] = false;
			$responseArray["error"] = "Cita mal formada";
			$responseArray["verse"] = $pre . $verseString;
		} else {
			$bookString = $pre . $verseArray[0];
			$explodeChapter = explode(':', $verseArray[1]);
			// si hay mas de 2 punto y coma no corresponde
			if (count($explodeChapter) > 2) {
				$responseArray["status"] = false;
				$responseArray["error"] = "Cita mal formada";
				$responseArray["verse"] = $pre . $verseString;
				$responseArray["book"] = $bookString;
			} else {
				$bookChapter = $explodeChapter[0];
				$verses = $explodeChapter[1];
				$responseArray["status"] = true;
				$responseArray["verse"] = $verseString;
				$responseArray["book"] = $bookString;
				$responseArray["chapter"] = $bookChapter;
				$responseArray["verses"] = $verses;
			}
		}

		return $responseArray;
	}

	function checkBook($bookString) {
		
	}

	function checkBookChapter($bookString, $bookChapter) {
		
	}

	function checkBookVerse($bookString, $bookChapter, $bookVese) {
		
	}

	function getBooks() {

		$response = null;
		$sql = "SELECT A.idBook id,A.name nombre, A.testament testamento, count(distinct(B.chapter)) capitulos FROM " . $this->dbprefix . "books A, " . $this->dbprefix . "verses B WHERE B.idBook = A.idBook GROUP BY A.idBook ORDER BY A.idBook ASC";
		$rs = mysql_query($sql, $this->db);


		if (count($rs) == 0) {
			$response["status"] = "error";
			$response["error"] = mysql_error();
		} else {
			$response["status"] = "ok";
			while ($row = mysql_fetch_assoc($rs)) {
				$response["books"][] = array(
					"number" => $row['id'],
					"name" => $row['nombre'],
					"testament" => $row['testamento'],
					"chapters" => $row['capitulos']
				);
			}
		}

		return $response;
	}

	function validKey($key) {
		$keys = Array("aldeacms", "ibef");
		if (in_array($key, $keys)) {
			return true;
		} else {
			return false;
		}
	}

	function validFunction($fn) {
		$functions = Array("books", "book", "verse", "checkverse");
		if (in_array($fn, $functions)) {
			return true;
		} else {
			return false;
		}
	}

	function sanitize($var) {
		// todo: sanitize functions
		return $var;
	}

	function print_json($array) {
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Content-type: application/json');
		$response = json_encode($array);
		echo $response;
	}

}

function d($var) {
	echo '<pre style="font-size:11px;background:#eee;padding:10px;">';
	print_r($var);
	echo '</pre>';
}

?>