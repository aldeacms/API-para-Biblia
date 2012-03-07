<?php
require('class.bible.php');


if (isset($_POST['fn'])) {
  $fn = $_POST['fn'];
} elseif (isset($_GET['fn'])) {
  $fn = $_GET['fn'];
}

$Bible = new Bible();

switch ($fn) {
  case 'getFullVerse':
	$verse = $_POST['verse'];
	$fullVerse = $Bible->getFullVerse($verse);
	echo $fullVerse;
	break;
  case 'getVerseText':
	$fullVerse = $_POST['fullVerse'];
	$verseText = $Bible->getVerseText($fullVerse);
	echo $verseText;
	break;
  case 'getBooks':

	$xmlBooks = $Bible->getBooks();
	header("content-type: text/xml");
	echo $xmlBooks;
	break;
  default:
	echo 'Error';
	break;
}
?>