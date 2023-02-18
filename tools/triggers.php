<?php
    /**
     * File: triggers
     * Scans the installation for Trigger calls and filters.
     */

    header("Content-Type: text/html; charset=UTF-8");

    define('DEBUG',          true);
    define('CHYRP_VERSION',  "2022.03.03");
    define('CHYRP_CODENAME', "Elegant");
    define('CHYRP_IDENTITY', "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")");
    define('MAIN',           false);
    define('ADMIN',          false);
    define('AJAX',           false);
    define('XML_RPC',        false);
    define('UPGRADING',      false);
    define('INSTALLING',     false);
    define('DIR',            DIRECTORY_SEPARATOR);
    define('MAIN_DIR',       dirname(dirname(__FILE__)));
    define('INCLUDES_DIR',   MAIN_DIR.DIR."includes");
    define('CACHES_DIR',     INCLUDES_DIR.DIR."caches");
    define('MODULES_DIR',    MAIN_DIR.DIR."modules");
    define('FEATHERS_DIR',   MAIN_DIR.DIR."feathers");
    define('THEMES_DIR',     MAIN_DIR.DIR."themes");
    define('USE_OB',         true);
    define('CAN_USE_ZLIB',   false);
    define('USE_ZLIB',       false);

    ob_start();
    define('OB_BASE_LEVEL', ob_get_level());

    # File: error
    # Functions for handling and reporting errors.
    require_once INCLUDES_DIR.DIR."error.php";

    # File: helpers
    # Various functions used throughout the codebase.
    require_once INCLUDES_DIR.DIR."helpers.php";

    # Array: $exclude
    # Paths to be excluded from directory recursion.
    $exclude = array(
        MAIN_DIR.DIR."tools",
        MAIN_DIR.DIR."uploads",
        MAIN_DIR.DIR."includes".DIR."caches",
        MAIN_DIR.DIR."includes".DIR."lib".DIR."Twig",
        MAIN_DIR.DIR."includes".DIR."lib".DIR."IXR",
        MAIN_DIR.DIR."includes".DIR."lib".DIR."cebe"
    );

    # Array: $trigger
    # Contains the calls and filters.
    $trigger = array(
        "call" => array(),
        "filter" => array()
    );

    # String: $str_reg
    # Regular expression representing a string.
    $str_reg = '(\"[^\"]+\"|\'[^\']+\')';

    # String: $arr_reg
    # Regular expression representing an array construct.
    $arr_reg = 'array\(([^\)]+)\)';

    /**
     * Function: scan_dir
     * Scans a directory in search of files or subdirectories.
     */
    function scan_dir($pathname) {
        global $exclude;

        $dir = new DirectoryIterator($pathname);

        foreach ($dir as $item) {
            if (!$item->isDot()) {
                $item_path = $item->getPathname();
                $extension = $item->getExtension();

                switch ($item->getType()) {
                    case "file":
                        scan_file($item_path, $extension);
                        break;
                    case "dir":
                        if (!in_array($item_path, $exclude))
                            scan_dir($item_path);

                        break;
                }
            }
        }
    }

    /**
     * Function: scan_file
     * Scans a file in search of triggers.
     */
    function scan_file($pathname, $extension) {
        if ($extension != "php" and $extension != "twig")
            return;

        $file = fopen($pathname, "r");
        $line = 1;

        if ($file === false)
            return;

        while (!feof($file)) {
            $text = fgets($file);

            switch ($extension) {
                case "php":
                    scan_call($pathname, $line, $text);
                    scan_call_array($pathname, $line, $text);
                    scan_filter($pathname, $line, $text);
                    scan_filter_array($pathname, $line, $text);
                    break;
                case "twig":
                    scan_twig($pathname, $line, $text);
                    break;
            }

            $line++;
        }

        fclose($file);
    }

    /**
     * Function: make_place
     * Makes a string detailing the place a trigger was found.
     */
    function make_place($pathname, $line) {
        return str_replace(
            array(MAIN_DIR.DIR, DIR),
            array("", "/"),
            $pathname
        )." on line ".$line;
    }

    /**
     * Function: scan_call
     * Scans text for trigger calls.
     */
    function scan_call($pathname, $line, $text) {
        global $trigger;
        global $str_reg;

        if (
            preg_match_all(
                "/(\\\$trigger|Trigger::current\(\))->call\($str_reg(,\s*(.+))?\)/",
                $text,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            foreach ($matches as $match) {
                $call = trim($match[2], "'\"");

                if (isset($trigger["call"][$call]))
                    $trigger["call"][$call]["places"][] = make_place(
                        $pathname,
                        $line
                    );
                else
                    $trigger["call"][$call] = array(
                        "places"    => array(make_place($pathname, $line)),
                        "arguments" => trim(fallback($match[4], ""), ", ")
                    );
            }
        }
    }

    /**
     * Function: scan_call_array
     * Scans text for trigger call arrays.
     */
    function scan_call_array($pathname, $line, $text) {
        global $trigger;
        global $arr_reg;

        if (
            preg_match_all(
                "/(\\\$trigger|Trigger::current\(\))->call\($arr_reg(,\s*(.+))?\)/",
                $text,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            foreach ($matches as $match) {
                $calls = explode(",", $match[2]);

                foreach ($calls as $call) {
                    $call = trim($call, "'\" ");

                    if (empty($call) or preg_match('/[^A-Za-z0-9_]/', $call))
                        continue;

                    if (isset($trigger["call"][$call]))
                        $trigger["call"][$call]["places"][] = make_place(
                            $pathname,
                            $line
                        );
                    else
                        $trigger["call"][$call] = array(
                            "places"    => array(make_place($pathname, $line)),
                            "arguments" => trim(fallback($match[4], ""), ", ")
                        );
                }
            }
        }
    }

    /**
     * Function: scan_filter
     * Scans text for trigger filters.
     */
    function scan_filter($pathname, $line, $text) {
        global $trigger;
        global $str_reg;

        if (
            preg_match_all(
                "/(\\\$trigger|Trigger::current\(\))->filter\(([^,]+),\s*$str_reg(,\s*(.+))?\)/",
                $text,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            foreach ($matches as $match) {
                $filter = trim($match[3], "'\"");

                if (isset($trigger["filter"][$filter]))
                    $trigger["filter"][$filter]["places"][] = make_place(
                        $pathname,
                        $line
                    );
                else
                    $trigger["filter"][$filter] = array(
                        "places"    => array(make_place($pathname, $line)),
                        "target"    => trim($match[2], ", "),
                        "arguments" => trim(fallback($match[5], ""), ", ")
                    );
            }
        }
    }

    /**
     * Function: scan_filter_array
     * Scans text for trigger filter arrays.
     */
    function scan_filter_array($pathname, $line, $text) {
        global $trigger;
        global $arr_reg;

        if (
            preg_match_all(
                "/(\\\$trigger|Trigger::current\(\))->filter\(([^,]+),\s*$arr_reg(,\s*(.+))?\)/",
                $text,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            foreach ($matches as $match) {
                $filters = explode(",", $match[3]);

                foreach ($filters as $filter) {
                    $filter = trim($filter, "'\" ");

                    if (empty($filter) or preg_match('/[^A-Za-z0-9_]/', $filter))
                        continue;

                    if (isset($trigger["filter"][$filter]))
                        $trigger["filter"][$filter]["places"][] = make_place(
                            $pathname,
                            $line
                        );
                    else
                        $trigger["filter"][$filter] = array(
                            "places"    => array(make_place($pathname, $line)),
                            "target"    => trim($match[2], ", "),
                            "arguments" => trim(fallback($match[5], ""), ", ")
                        );
                }
            }
        }
    }

    /**
     * Function: scan_twig
     * Scans text for trigger calls in Twig statements.
     */
    function scan_twig($pathname, $line, $text) {
        global $trigger;
        global $str_reg;

        if (
            preg_match_all(
                "/\{\{\s*trigger\.call\($str_reg(,\s*(.+))?\)\s*\}\}/",
                $text,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            foreach ($matches as $match) {
                $call = trim($match[1], "'\"");

                if (isset($trigger["call"][$call]))
                    $trigger["call"][$call]["places"][] = make_place(
                        $pathname,
                        $line
                    );
                else
                    $trigger["call"][$call] = array(
                        "places"    => array(make_place($pathname, $line)),
                        "arguments" => trim(fallback($match[3], ""), ", ")
                    );
            }
        }
    }

    /**
     * Function: create_file
     * Generates the triggers list and writes it to disk.
     */
    function create_file() {
        global $trigger;

        $contents = "==============================================\n".
                    " Trigger Calls\n".
                    "==============================================\n";

        foreach ($trigger["call"] as $call => $attributes) {
            $contents.= "\n\n";
            $contents.= $call."\n";
            $contents.= str_repeat("-", strlen($call))."\n";
            $contents.= "Called from:\n";

            foreach ($attributes["places"] as $place)
                $contents.= "\t".$place."\n";

            if (!empty($attributes["arguments"])) {
                $contents.= "\nArguments:\n";
                $contents.= "\t".$attributes["arguments"]."\n";
            }
        }

        $contents.= "\n\n\n\n";
        $contents.= "==============================================\n".
                    " Trigger Filters\n".
                    "==============================================\n";

        foreach ($trigger["filter"] as $filter => $attributes) {
            $contents.= "\n\n";
            $contents.= $filter."\n";
            $contents.= str_repeat("-", strlen($filter))."\n";
            $contents.= "Called from:\n";

            foreach ($attributes["places"] as $place)
                $contents.= "\t".$place."\n";

            $contents.= "\nTarget:\n";
            $contents.= "\t".$attributes["target"]."\n";

            if (!empty($attributes["arguments"])) {
                $contents.= "\nArguments:\n";
                $contents.= "\t".$attributes["arguments"]."\n";
            }
        }

        @file_put_contents(
            MAIN_DIR.DIR."tools".DIR."triggers_list.txt",
            $contents
        );
        echo fix($contents);
    }

    #---------------------------------------------
    # Output Starts
    #---------------------------------------------
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo "Triggers"; ?></title>
        <meta name="viewport" content="width = 800">
        <style type="text/css">
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('../fonts/OpenSans-Regular.woff') format('woff');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('../fonts/OpenSans-Bold.woff') format('woff');
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('../fonts/OpenSans-Italic.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('../fonts/OpenSans-BoldItalic.woff') format('woff');
                font-weight: bold;
                font-style: italic;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('../fonts/Cousine-Regular.woff') format('woff');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('../fonts/Cousine-Bold.woff') format('woff');
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('../fonts/Cousine-Italic.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('../fonts/Cousine-BoldItalic.woff') format('woff');
                font-weight: bold;
                font-style: italic;
            }
            *::selection {
                color: #ffffff;
                background-color: #ff7f00;
            }
            html {
                font-size: 14px;
            }
            html, body, ul, ol, li,
            h1, h2, h3, h4, h5, h6,
            form, fieldset, a, p, pre {
                margin: 0em;
                padding: 0em;
                border: 0em;
            }
            body {
                font-size: 1rem;
                font-family: "Open Sans webfont", sans-serif;
                line-height: 1.5;
                color: #1f1f23;
                background: #efefef;
                padding: 2rem;
            }
            h1 {
                font-size: 2em;
                text-align: center;
                font-weight: bold;
                margin: 1rem 0rem;
                line-height: 1;
            }
            h1:first-child {
                margin-top: 0em;
            }
            h2 {
                font-size: 1.5em;
                text-align: center;
                font-weight: bold;
                margin: 1rem 0rem;
            }
            h3 {
                font-size: 1em;
                font-weight: bold;
                margin: 1rem 0rem;
                border-bottom: 1px solid #cfcfcf;
            }
            p {
                margin-bottom: 1rem;
            }
            p:last-child,
            p:empty {
                margin-bottom: 0rem;
            }
            strong {
                font-weight: normal;
                color: #c11600;
            }
            ul, ol {
                margin: 0rem 0rem 2rem 2rem;
                list-style-position: outside;
            }
            li {
                margin-bottom: 1rem;
            }
            pre {
                font-family: "Cousine webfont", monospace;
                font-size: 0.9em;
                background-color: #efefef;
                margin: 1rem 0rem;
                padding: 1rem;
                overflow-x: auto;
            }
            code {
                font-family: "Cousine webfont", monospace;
                font-size: 0.9em;
                background-color: #efefef;
                padding: 0px 2px;
                border: 1px solid #cfcfcf;
                vertical-align: bottom;
            }
            pre > code {
                font-size: 0.9rem;
                display: block;
                border: none;
                padding: 0px;
            }
            a:link,
            a:visited {
                color: #1f1f23;
                text-decoration: underline;
            }
            a:focus {
                outline: #ff7f00 dashed 2px;
                outline-offset: 1px;
            }
            a:hover,
            a:focus,
            a:active {
                color: #1e57ba;
                text-decoration: underline;
            }
            a.big,
            button {
                box-sizing: border-box;
                display: block;
                font-size: 1.25em;
                text-align: center;
                color: #1f1f23;
                text-decoration: none;
                line-height: 1.25;
                margin: 1rem 0rem;
                padding: 0.4em 0.6em;
                background-color: #f2fbff;
                border: 2px solid #b8cdd9;
                border-radius: 0.3em;
                cursor: pointer;
            }
            button {
                width: 100%;
            }
            a.big:last-child,
            button:last-child {
                margin-bottom: 0em;
            }
            a.big:hover,
            button:hover,
            a.big:focus,
            button:focus,
            a.big:active,
            button:active {
                border-color: #1e57ba;
                outline: none;
            }
            aside {
                margin-bottom: 1rem;
                padding: 0.5em 1em;
                border: 1px solid #e5d7a1;
                border-radius: 0.25em;
                background-color: #fffecd;
            }
            .window {
                width: 30rem;
                background: #ffffff;
                padding: 2rem;
                margin: 0rem auto 0rem auto;
                border-radius: 2rem;
            }
        </style>
    </head>
    <body>
        <div class="window">
            <pre role="status" class="pane"><?php

    #---------------------------------------------
    # Processing Starts
    #---------------------------------------------

    scan_dir(MAIN_DIR);
    create_file();

    #---------------------------------------------
    # Processing Ends
    #---------------------------------------------

            ?></pre>
        </div>
    </body>
</html>
