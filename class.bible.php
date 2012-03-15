<?php
require('settings.bible.php');
class Bible
{
    var $dbhost = null;
    var $dbuser = null;
    var $dbpass = null;
    var $dbname = null;
    var $dbprefix = null;
    var $bible = null;
    var $db = null;

    function __construct()
    {
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

    function getBibles()
    {

	$sql = "SELECT * FROM " . $this->dbprefix . "bibles";
	$rs = mysql_query($sql, $this->db) or die(mysql_error());

	while ($row = mysql_fetch_assoc($rs)) {
	    $bibles[] = $row;
	}
	return $bibles;
    }

    function getFullVerse($verse)
    {
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

    function getVerse($verse)
    {

	$verseArray = $this->getVerseArray($verse);
	if (!$verseArray['status']) {
	    return $verseArray;
	} else {
	    $verses="";
	    $verseFrom=$verseArray['verseFrom'];
	    $verseTo=$verseArray['verseTo'];
	    $bookString=$verseArray['book'];
	    $bookChapter=$verseArray['chapter'];
	    $sql = "SELECT * FROM " . $this->dbprefix . "verses v, " . $this->dbprefix . "books b  WHERE  UPPER(b.name)=UPPER(\"" . $bookString . "\") AND b.idBook=v.idBook AND v.chapter=\"" . $bookChapter . "\" AND v.verse between \"" . $verseFrom . "\" AND \"" . $verseTo . "\"";
	    $rs = mysql_query($sql, $this->db) or die(mysql_error());

	    if (!$rs) {
		mail('daniel@aldeacms.com', 'error', 'SQL:' . $sql . '      Error:' . $this->db->ErrorMsg());
		$verseArray['status']=false;
		$verseArray['error']="Se ha producido un error en la consulta";
		return $verseArray;
	    } else {
		while ($row = mysql_fetch_assoc($rs)) {
		    $verses[] = array(
			"verse"=>$row['verse'],
			"texto"=>utf8_encode($row['text'])
			);
		}
	    }
	    $verseArray['verses']=$verses;
	    return $verseArray;
	}
    }

    function getVerseArray($verse)
    {
	$verse = utf8_decode($verse);
	$verse = trim($verse);
	$pre = "";
	$responseArray["status"] = true; //por defecto status es true
	// si parte con 1, 2 o 3, excepcion en pre
	if (substr($verse, 0, 1) == 1 || substr($verse, 0, 1) == 2 || substr($verse, 0, 1) == 3) {
	    // si el segundo es espacio, lo eliminamos y agregamos al pre
	    $pre = $pre = substr($verse, 0, 1);
	    $pre.=" ";
	    $verse = trim(substr($verse, 1));
	}
	// ahora verse tiene el libro, capitulo y versiculo sin el pre...

	$verseArray = explode(' ', $verse);
	// si hay mas de 2 espacios no corresponde
	if (count($verseArray) > 2) {
	    $responseArray["status"] = false;
	    $responseArray["error"] = "Cita mal formada";
	    $responseArray["verse"] = $pre . $verse;
	} else {
	    $bookString = $pre . $verseArray[0];
	    $realBookString = trim($this->checkBook($bookString));
	    if (!$realBookString) {
		$responseArray["status"] = false;
		$responseArray["error"] = "Libro no encontrado";
		$responseArray["verse"] = $pre . $verse;
		$responseArray["book"] = $bookString;
		return $responseArray;
	    } else {
		$responseArray["book"] = $realBookString;
		$explodeChapter = explode(':', $verseArray[1]);

		// si hay mas de 2 punto y coma no corresponde
		if (count($explodeChapter) > 2) {
		    $responseArray["status"] = false;
		    $responseArray["error"] = "Cita mal formada";
		    $responseArray["verse"] = $pre . $verse;
		    return $responseArray;
		} else {
		    $bookChapter = trim($explodeChapter[0]);
		    $realBookChapter = $this->checkBookChapter($realBookString, $bookChapter);
		    if (!$realBookChapter) {
			$responseArray["status"] = false;
			$responseArray["error"] = "Capitulo invalido";
			$responseArray["chapter"] = $bookChapter;
			$responseArray["verse"] = $pre . $verse;
			return $responseArray;
		    } else {
			$verses = trim($explodeChapter[1]);
			if (strrpos($verses, "-")) {
			    $versesArray = explode("-", $verses);
			    $verseFrom = $versesArray[0];
			    $verseTo = $versesArray[1];
			} else {
			    $verseFrom = $verses;
			    $verseTo = $verses;
			}
			
			$realVerses=$this->checkBookVerse($realBookString, $bookChapter, $verseFrom, $verseTo);
			if (!$realVerses) {
			    $responseArray["status"] = false;
			    $responseArray["error"] = "Versiculos invalidos";
			    $responseArray["chapter"] = $bookChapter;
			    $responseArray["verseFrom"] = $verseFrom;
			    $responseArray["verseTo"] = $verseTo;
			    $responseArray["verse"] = $pre . $verse;
			    $responseArray["sql"] = $verseTo;
			    $responseArray["verse"] = $pre . $verse;
			    return $responseArray;
			} else {
			    $responseArray["verse"] = $pre . $verse;
			    $responseArray["chapter"] = $bookChapter;
			    $responseArray["verses"] = $verses;
			    $responseArray["verseFrom"] = $verseFrom;
			    $responseArray["verseTo"] = $verseTo;
			    return $responseArray;
			}
		    }
		}
	    }
	}

	return $responseArray;
    }

    function checkBook($bookString)
    {
	$sql = "SELECT * FROM " . $this->dbprefix . "books_references br, " . $this->dbprefix . "books b  WHERE  UPPER(br.text)=UPPER(\"" . $bookString . "\") AND br.idBook=b.idBook";
	$rs = mysql_query($sql, $this->db) or die(mysql_error());

	if (!$rs) {
	    mail('daniel@aldeacms.com', 'error', 'SQL:' . $sql . '      Error:' . $this->db->ErrorMsg());
	    return false;
	} else {
	    while ($row = mysql_fetch_assoc($rs)) {
		$books[] = $row;
	    }
	    $book = $books[0]['name'];
	    return $book;
	}
    }

    function checkBookChapter($bookString, $bookChapter)
    {
	$verses = null;
	$sql = "SELECT * FROM " . $this->dbprefix . "verses v, " . $this->dbprefix . "books b  WHERE  UPPER(b.name)=UPPER(\"" . $bookString . "\") AND b.idBook=v.idBook AND v.chapter=\"" . $bookChapter . "\"";
	$rs = mysql_query($sql, $this->db) or die(mysql_error());

	if (!$rs) {
	    mail('daniel@aldeacms.com', 'error', 'SQL:' . $sql . '      Error:' . $this->db->ErrorMsg());
	    return false;
	} else {
	    while ($row = mysql_fetch_assoc($rs)) {
		$verses[] = $row;
	    }
	    if (count($verses) > 0) {
		return $bookChapter;
	    } else {
		return false;
	    }
	}
    }

    function checkBookVerse($bookString, $bookChapter, $verseFrom, $verseTo)
    {
	if (!($verseFrom <= $verseTo)) {
	    return false;
	}

	$verse1 = array();
	$verse2 = array();

	$sql1 = "SELECT * FROM " . $this->dbprefix . "verses v, " . $this->dbprefix . "books b  WHERE  UPPER(b.name)=UPPER(\"" . $bookString . "\") AND b.idBook=v.idBook AND v.chapter=" . $bookChapter . " AND v.verse=" . $verseFrom ;
	$rs1 = mysql_query($sql1, $this->db) or die(mysql_error());

	if (!$rs1) {
	    mail('daniel@aldeacms.com', 'error', 'SQL:' . $sql1 . '      Error:' . $this->db->ErrorMsg());
	    return false;
	} else {
	    while ($row1 = mysql_fetch_assoc($rs1)) {
		$verse1[] = $row1;
	    }

	    if (count($verse1) < 1) {
		return false;
	    } else {
		$sql2 = "SELECT * FROM " . $this->dbprefix . "verses v, " . $this->dbprefix . "books b  WHERE  UPPER(b.name)=UPPER(\"" . $bookString . "\") AND b.idBook=v.idBook AND v.chapter=" . $bookChapter . " AND v.verse=" . $verseTo;
		$rs2 = mysql_query($sql2, $this->db) or die(mysql_error());
		
		if (!$rs2) {
		    mail('daniel@aldeacms.com', 'error', 'SQL:' . $sql2 . '      Error:' . $this->db->ErrorMsg());
		    return false;
		} else {
		    while ($row2 = mysql_fetch_assoc($rs2)) {
			$verse2[] = $row2;
		    }

		    if (count($verse2) < 1) {
			return false;
		    } else {
			return true;
		    }
		}
	    }
	}
    }

    function getBooks()
    {

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
		    "name" => utf8_encode($row['nombre']),
		    "testament" => $row['testamento'],
		    "chapters" => $row['capitulos']
		);
	    }
	}

	return $response;
    }

    function validKey($key)
    {
	$keys = Array("aldeacms", "ibef");
	if (in_array($key, $keys)) {
	    return true;
	} else {
	    return false;
	}
    }

    function validFunction($fn)
    {
	$functions = Array("books", "book", "verse", "checkverse");
	if (in_array($fn, $functions)) {
	    return true;
	} else {
	    return false;
	}
    }

    function sanitize($var)
    {
	// todo: sanitize functions
	return $var;
    }

    function print_json($array)
    {
	header('Cache-Control: no-cache, must-revalidate');
	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
	header('Content-type: application/json');
	$response = json_encode($array);
	echo $response;
    }

}

function d($var)
{
    echo '<pre style="font-size:11px;background:#eee;padding:10px;">';
    print_r($var);
    echo '</pre>';
}

?>