<?php
require('class.bible.php');

$Bible = new Bible();

// Variables GET
$fn = $Bible->sanitize($_GET['fn']);
$key = $Bible->sanitize($_GET['key']);
$format = $Bible->sanitize($_GET['format']);
$bible = $Bible->sanitize($_GET['bible']);
$book = $Bible->sanitize($_GET['book']);
$chapter = $Bible->sanitize($_GET['chapter']);
$verse = $Bible->sanitize($_GET['verse']);
$verseFrom = $Bible->sanitize($_GET['versefrom']);
$verseTo = $Bible->sanitize($_GET['verseto']);

// Valid key?
if (!$Bible->validKey($key) || $key == null) {
    $responseArray["status"] = "error";
    $responseArray["error"] = "Key invalida";
    $Bible->print_json($responseArray);
    die();
}

// Valid function?
if (!$Bible->validFunction($fn) || $fn == null) {
    $responseArray["status"] = "error";
    $responseArray["error"] = "Funcion desconocida";
    $Bible->print_json($responseArray);
    die();
}


switch ($fn) {
    case 'books': // All bible books
        $responseArray = $Bible->getBooks();
        $Bible->print_json($responseArray);
        break;
    case 'checkverse': // Valid verse?
        $responseArray = $Bible->checkVerse($verse);
        $Bible->print_json($responseArray);
        break;
    case 'verse':
        $response = $Bible->getVerse($verse);
        $Bible->print_json($response);
        break;
    case 'getFullVerse':
        $fullVerse = $Bible->getFullVerse($verse);
        echo $fullVerse;
        break;
    default:
        echo 'Error';
        break;
}
?>