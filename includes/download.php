<?php
    /**
     * File: Download
     * Send an uploaded file to the visitor.
     */

    define('DEBUG',        false);
    define('JAVASCRIPT',   false);
    define('MAIN',         false);
    define('ADMIN',        false);
    define('AJAX',         false);
    define('XML_RPC',      false);
    define('UPGRADING',    false);
    define('INSTALLING',   false);
    define('TESTER',       isset($_SERVER['HTTP_USER_AGENT']) and $_SERVER['HTTP_USER_AGENT'] == "TESTER");
    define('DIR',          DIRECTORY_SEPARATOR);
    define('MAIN_DIR',     dirname(dirname(__FILE__)));
    define('INCLUDES_DIR', dirname(__FILE__));
    define('USE_ZLIB',     false);

    # Constant: JSON_PRETTY_PRINT
    # Define a safe value to avoid warnings pre-5.4
    if (!defined('JSON_PRETTY_PRINT'))
        define('JSON_PRETTY_PRINT', 0);

    # Constant: JSON_UNESCAPED_SLASHES
    # Define a safe value to avoid warnings pre-5.4
    if (!defined('JSON_UNESCAPED_SLASHES'))
        define('JSON_UNESCAPED_SLASHES', 0);

    require_once "error.php";
    require_once "helpers.php";
    require_once "class".DIR."Config.php";

    # Sanitize input depending on magic_quotes_gpc's enabled status.
    sanitize_input($_GET);

    if (empty($_GET['file'])) {
        header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request");
        exit("Missing Argument.");
    }

    $filename = oneof(trim($_GET['file']), DIR);
    $filepath = uploaded($filename, false);

    if (substr_count($filename, DIR)) {
        header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request");
        exit("Malformed URI.");
    }

    if (!is_readable($filepath) or !is_file($filepath)) {
        header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
        exit("File Not Found.");
    }

    header("Last-Modified: ".gmdate("D, d M Y H:i:s", filemtime($filepath))." GMT");
    header("Content-type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"".$filename."\"");
    header("Content-length: ".filesize($filepath));
    readfile($filepath);
    flush();
