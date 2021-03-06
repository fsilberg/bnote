<?php
/*
 * PHP Version >= 5.2 !!!
 *
 * WEB APPLICATION INTERFACE: Blue Note Interface (BNI)
 * This file can be called with various parameters to retrieve
 * information from the web application.
 * 
 * Usage: webapp.php?func=[function]&[p1]=[v1]&...
 */

// connect to application
$dir_prefix = "../../";
global $dir_prefix;

include $dir_prefix . "dirs.php";
include $dir_prefix . $GLOBALS["DIR_DATA"] . "database.php";
include $dir_prefix . $GLOBALS["DIR_DATA"] . "regex.php";
$GLOBALS["DIR_WIDGETS"] = $dir_prefix . $GLOBALS["DIR_WIDGETS"];
require_once($GLOBALS["DIR_WIDGETS"] . "error.php");

// Build Database Connection
$db = new Database();
global $db;

// "route" requst
if(isset($_GET["func"])) {
	$_GET["func"]();
}

/**
 * Shows the page.
 */
function getPage() {
	if(isset($_GET["id"])) {
		include $GLOBALS["dir_prefix"] . $GLOBALS["DATA_PATHS"]["webpages"]
					 . $_GET["id"] . ".html";
	}
}

/**
 * Calculates the image path and shows it.
 */
function getImagePath() {
	// check for id
	if(!isset($_GET["id"])) {
		new Error("ID not set.");
	}
	
	// get data
	$query = "SELECT * FROM galleryimage WHERE id = " . $_GET["id"];
	$img = $GLOBALS["db"]->getRow($query);
	
	// build path
	$res = "/" . $GLOBALS["DATA_PATHS"]["gallery"];
	$imgtype = substr($img["filename"], strrpos($img["filename"], ".")); // e.g. ".jpg"
	$res .= $img["gallery"] . "/" . $img["id"] . $imgtype;
	
	// output
	echo $res;
}

/**
 * Calculates the path to the thumbnail.
 */
function getThumbPath() {
	// check for id
	if(!isset($_GET["id"])) {
		new Error("ID not set.");
	}
	
	// get data
	$query = "SELECT * FROM galleryimage WHERE id = " . $_GET["id"];
	$img = $GLOBALS["db"]->getRow($query);
	
	// build path
	$res = "/" . $GLOBALS["DATA_PATHS"]["gallery"];
	$res .= "thumbs/";
	$imgtype = substr($img["filename"], strrpos($img["filename"], ".")); // e.g. ".jpg"
	$res .= $img["gallery"] . "/" . $img["id"] . $imgtype;
	
	// output
	echo $res;
}

/**
 * Shows JSON array with all galleries.
 */
function getGalleries() {
	$query = "SELECT * FROM gallery";
	echo json_encode($GLOBALS["db"]->getSelection($query));
}

/**
 * Shows JSON array with gallery infos.
 */
function getGallery() {
	// check for id
	if(!isset($_GET["id"])) {
		new Error("ID not set.");
	}
	
	$query = "SELECT * FROM gallery WHERE id = " . $_GET["id"];
	echo json_encode($GLOBALS["db"]->getRow($query));
}

/**
 * Shows JSON array with all images for the given (GET-id) gallery.
 */
function getImagesForGallery() {
	// check for id
	if(!isset($_GET["id"])) {
		new Error("ID not set.");
	}
	
	$query = "SELECT * FROM galleryimage WHERE gallery = " . $_GET["id"];
	echo json_encode($GLOBALS["db"]->getSelection($query));
}

/**
 * Shows JSON array with infos on the given (GET-id) image.
 */
function getImage() {
	// check for id
	if(!isset($_GET["id"])) {
		new Error("ID not set.");
	}
	
	$query = "SELECT * FROM galleryimage WHERE id = " . $_GET["id"];
	echo json_encode($GLOBALS["db"]->getRow($query));
}

?>
