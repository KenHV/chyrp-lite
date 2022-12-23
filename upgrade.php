<?php
    /**
     * File: upgrade
     * A task-based general purpose upgrader for Chyrp Lite, enabled modules and enabled feathers.
     */

    header("Content-Type: text/html; charset=UTF-8");

    define('DEBUG',            true);
    define('CHYRP_VERSION',    "2022.03.02");
    define('CHYRP_CODENAME',   "Elegant");
    define('CHYRP_IDENTITY',   "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")");
    define('MAIN',             false);
    define('ADMIN',            false);
    define('AJAX',             false);
    define('XML_RPC',          false);
    define('UPGRADING',        true);
    define('INSTALLING',       false);
    define('DIR',              DIRECTORY_SEPARATOR);
    define('MAIN_DIR',         dirname(__FILE__));
    define('INCLUDES_DIR',     MAIN_DIR.DIR."includes");
    define('CACHES_DIR',       INCLUDES_DIR.DIR."caches");
    define('MODULES_DIR',      MAIN_DIR.DIR."modules");
    define('FEATHERS_DIR',     MAIN_DIR.DIR."feathers");
    define('THEMES_DIR',       MAIN_DIR.DIR."themes");
    define('USE_GETTEXT_SHIM', stripos(PHP_OS, "Win") === 0);
    define('USE_OB',           true);
    define('CAN_USE_ZLIB',     false);
    define('USE_ZLIB',         false);

    ob_start();
    define('OB_BASE_LEVEL', ob_get_level());

    # File: error
    # Functions for handling and reporting errors.
    require_once INCLUDES_DIR.DIR."error.php";

    # File: helpers
    # Various functions used throughout the codebase.
    require_once INCLUDES_DIR.DIR."helpers.php";

    # File: Config
    # See Also:
    #     <Config>
    require_once INCLUDES_DIR.DIR."class".DIR."Config.php";

    # File: SQL
    # See Also:
    #     <SQL>
    require INCLUDES_DIR.DIR."class".DIR."SQL.php";

    # File: Translation
    # See Also:
    #     <Translation>
    require_once INCLUDES_DIR.DIR."class".DIR."Translation.php";

    # Register our autoloader.
    spl_autoload_register("autoload");

    # Boolean: $upgraded
    # Has Chyrp Lite been upgraded?
    $upgraded = false;

    # Load the config settings.
    $config = Config::current();

    # Prepare the SQL interface.
    $sql = SQL::current();

    # Initialize connection to SQL server.
    $sql->connect();

    # Set the locale.
    set_locale($config->locale);

    # Load the translation engine.
    load_translator("chyrp", INCLUDES_DIR.DIR."locale");

    /**
     * Function: alert
     * Logs an alert message and returns the log to date.
     */
    function alert($message = null): ?array {
        static $log = array();

        if (isset($message))
            $log[] = (string) $message;

        return empty($log) ? null : $log ;
    }

    /**
     * Function: test_directories
     * Tests whether or not the directories that need write access have it.
     */
    function test_directories(): void {
        # Test if we can write to MAIN_DIR (needed for the .htaccess file).
        if (!is_writable(MAIN_DIR))
            alert(
                __("Please CHMOD or CHOWN the installation directory to make it writable.")
            );

        # Test if we can write to INCLUDES_DIR (needed for config.json.php).
        if (!is_writable(INCLUDES_DIR))
            alert(
                __("Please CHMOD or CHOWN the <em>includes</em> directory to make it writable.")
            );

        # Test if we can write to CACHES_DIR (needed by some extensions).
        if (!is_writable(CACHES_DIR))
            alert(
                __("Please CHMOD or CHOWN the <em>caches</em> directory to make it writable.")
            );

        # Test if we can write to twig cache.
        if (!is_writable(CACHES_DIR.DIR."twig"))
            alert(
                __("Please CHMOD or CHOWN the <em>twig</em> directory to make it writable.")
            );

        # Test if we can write to thumbs cache.
        if (!is_writable(CACHES_DIR.DIR."thumbs"))
            alert(
                __("Please CHMOD or CHOWN the <em>thumbs</em> directory to make it writable.")
            );
    }

    /**
     * Function: update_htaccess
     * Updates the .htaccess file to ensure all features are supported.
     *
     * Versions: 2018.02 => 2018.03
     */
    function update_htaccess(): void {
        $config = Config::current();

        if (file_exists(MAIN_DIR.DIR.".htaccess")) {
            $set = htaccess_conf();

            if ($set === false)
                alert(__("Failed to write file to disk."));
        }
    }

    /**
     * Function: update_caddyfile
     * Updates the caddyfile to ensure all features are supported.
     *
     * Versions: 2019.03 => 2019.04
     */
    function update_caddyfile(): void {
        $config = Config::current();

        if (file_exists(MAIN_DIR.DIR."caddyfile")) {
            $set = caddyfile_conf();

            if ($set === false)
                alert(__("Failed to write file to disk."));
        }
    }

    /**
     * Function: update_nginx
     * Updates the nginx configuration to ensure all features are supported.
     *
     * Versions: 2019.03 => 2019.04
     */
    function update_nginx(): void {
        $config = Config::current();

        if (file_exists(MAIN_DIR.DIR."include.conf")) {
            $set = nginx_conf();

            if ($set === false)
                alert(__("Failed to write file to disk."));
        }
    }

    /**
     * Function: add_markdown
     * Adds the enable_markdown config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function add_markdown(): void {
        $set = Config::current()->set("enable_markdown", true, true);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: add_homepage
     * Adds the enable_homepage config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function add_homepage(): void {
        $set = Config::current()->set("enable_homepage", false, true);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: add_uploads_limit
     * Adds the uploads_limit config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function add_uploads_limit(): void {
        $set = Config::current()->set("uploads_limit", 10, true);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: remove_trackbacking
     * Removes the enable_trackbacking config setting.
     *
     * Versions: 2015.06 => 2015.07
     */
    function remove_trackbacking(): void {
        $set = Config::current()->remove("enable_trackbacking");

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: add_admin_per_page
     * Adds the admin_per_page config setting.
     *
     * Versions: 2015.07 => 2016.01
     */
    function add_admin_per_page(): void {
        $set = Config::current()->set("admin_per_page", 25, true);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: disable_importers
     * Disables the importers module.
     *
     * Versions: 2016.03 => 2016.04
     */
    function disable_importers(): void {
        $config = Config::current();
        $set = $config->set(
            "enabled_modules",
            array_diff($config->enabled_modules, array("importers"))
        );

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: add_export_content
     * Adds the export_content permission.
     *
     * Versions: 2016.03 => 2016.04
     */
    function add_export_content(): void {
        $sql = SQL::current();

        if (
            !$sql->count(
                "permissions",
                array(
                    "id" => "export_content",
                    "group_id" => 0
                )
            )
        )
            $sql->insert(
                "permissions",
                array(
                    "id" => "export_content",
                    "name" => "Export Content",
                    "group_id" => 0
                )
            );
    }

    /**
     * Function: add_feed_format
     * Adds the feed_format config setting.
     *
     * Versions: 2017.02 => 2017.03
     */
    function add_feed_format(): void {
        $set = Config::current()->set("feed_format", "AtomFeed", true);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: remove_captcha
     * Removes the enable_captcha config setting.
     *
     * Versions: 2017.03 => 2018.01
     */
    function remove_captcha(): void {
        $set = Config::current()->remove("enable_captcha");

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: disable_recaptcha
     * Disables the recaptcha module.
     *
     * Versions: 2017.03 => 2018.01
     */
    function disable_recaptcha(): void {
        $config = Config::current();
        $set = $config->set(
            "enabled_modules",
            array_diff($config->enabled_modules, array("recaptcha"))
        );

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: remove_feed_url
     * Removes the feed_url config setting.
     *
     * Versions: 2018.03 => 2018.04
     */
    function remove_feed_url(): void {
        $set = Config::current()->remove("feed_url");

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: remove_cookies_notification
     * Removes the cookies_notification config setting.
     *
     * Versions: 2019.01 => 2019.02
     */
    function remove_cookies_notification(): void {
        $set = Config::current()->remove("cookies_notification");

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: remove_ajax
     * Removes the enable_ajax config setting.
     *
     * Versions: 2019.02 => 2019.03
     */
    function remove_ajax(): void {
        $set = Config::current()->remove("enable_ajax");

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: disable_simplemde
     * Disables the simplemde module.
     *
     * Versions: 2019.03 => 2019.04
     */
    function disable_simplemde(): void {
        $config = Config::current();
        $set = $config->set(
            "enabled_modules",
            array_diff($config->enabled_modules, array("simplemde"))
        );

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: add_search_pages
     * Adds the search_pages config setting.
     *
     * Versions: 2020.03 => 2020.04
     */
    function add_search_pages(): void {
        $set = Config::current()->set("search_pages", false, true);

        if ($set === false)
            error(
                __("Error"),
                __("Could not write the configuration file.")
            );
    }

    /**
     * Function: fix_sqlite_post_pinned
     * Fixes the pinned status of posts created without bool-to-int conversion.
     *
     * Versions: 2021.01 => 2021.02
     */
    function fix_sqlite_post_pinned(): void {
        $sql = SQL::current();

        if ($sql->adapter != "sqlite")
            return;

        $results = $sql->select(
            tables:"posts",
            fields:"id",
            conds:array("pinned" => "")
        )->fetchAll();

        foreach ($results as $result)
            $sql->update(
                table:"posts",
                conds:array("id" => $result["id"]),
                data:array("pinned" => false)
            );
    }

    /**
     * Function: fix_post_updated
     * Normalizes "0000-00-00 00:00:00" updated_at values to "0001-01-01 00:00:00".
     *
     * Versions: 2022.01 => 2022.02
     */
    function fix_post_updated(): void {
        $sql = SQL::current();

        if ($sql->adapter == "pgsql")
            return;

        $results = $sql->select(
            tables:"posts",
            fields:"id",
            conds:array("updated_at" => "0000-00-00 00:00:00")
        )->fetchAll();

        foreach ($results as $result)
            $sql->update(
                table:"posts",
                conds:array("id" => $result["id"]),
                data:array("updated_at" => "0001-01-01 00:00:00")
            );
    }

    /**
     * Function: mysql_utf8mb4
     * Upgrades MySQL database tables and columns to utf8mb4.
     *
     * Versions: 2022.01 => 2022.02
     */
    function mysql_utf8mb4(): void {
        $sql = SQL::current();

        if ($sql->adapter != "mysql")
            return;

        $tables = $sql->query("SHOW TABLE STATUS")->fetchAll();

        foreach ($tables as $table) {
            if (strpos($table["Collation"], "utf8mb4_") === 0)
                continue;

            $sql->query(
                "ALTER TABLE \"".$table["Name"].
                "\" CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
            );
        }
    }

    /**
     * Function: add_import_content
     * Adds the import_content permission.
     *
     * Versions: 2022.02 => 2022.03
     */
    function add_import_content(): void {
        $sql = SQL::current();

        if (
            !$sql->count(
                "permissions",
                array(
                    "id" => "import_content",
                    "group_id" => 0
                )
            )
        )
            $sql->insert(
                "permissions",
                array(
                    "id" => "import_content",
                    "name" => "Import Content",
                    "group_id" => 0
                )
            );
    }

    #---------------------------------------------
    # Output Starts
    #---------------------------------------------
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo __("Chyrp Lite Upgrader"); ?></title>
        <meta name="viewport" content="width = 800">
        <style type="text/css">
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-Regular.woff') format('woff');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-Bold.woff') format('woff');
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-Italic.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Open Sans webfont';
                src: url('./fonts/OpenSans-BoldItalic.woff') format('woff');
                font-weight: bold;
                font-style: italic;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('./fonts/Cousine-Regular.woff') format('woff');
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('./fonts/Cousine-Bold.woff') format('woff');
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('./fonts/Cousine-Italic.woff') format('woff');
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: 'Cousine webfont';
                src: url('./fonts/Cousine-BoldItalic.woff') format('woff');
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
                font-weight: bold;
                margin: 1rem 0rem;
                text-align: center;
                line-height: 1;
            }
            h1:first-child {
                margin-top: 0rem;
            }
            h2 {
                font-size: 1.5em;
                font-weight: bold;
                text-align: center;
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
            pre.pane {
                height: 15rem;
                overflow: auto;
            }
            pre.pane:empty {
                display: none;
            }
            pre.pane:empty + h1 {
                margin-top: 0rem;
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
    # Upgrading Starts
    #---------------------------------------------

    if (isset($_POST['upgrade']) and $_POST['upgrade'] == "yes") {
        # Perform core upgrade tasks.
        test_directories();
        update_htaccess();
        update_caddyfile();
        update_nginx();
        add_markdown();
        add_homepage();
        add_uploads_limit();
        remove_trackbacking();
        add_admin_per_page();
        disable_importers();
        add_export_content();
        add_feed_format();
        remove_captcha();
        disable_recaptcha();
        remove_feed_url();
        remove_cookies_notification();
        remove_ajax();
        disable_simplemde();
        add_search_pages();
        fix_sqlite_post_pinned();
        fix_post_updated();
        mysql_utf8mb4();
        add_import_content();

        # Perform module upgrades.
        foreach ($config->enabled_modules as $module) {
            if (file_exists(MAIN_DIR.DIR."modules".DIR.$module.DIR."upgrades.php"))
                require MAIN_DIR.DIR."modules".DIR.$module.DIR."upgrades.php";
        }

        # Perform feather upgrades.
        foreach ($config->enabled_feathers as $feather) {
            if (file_exists(MAIN_DIR.DIR."feathers".DIR.$feather.DIR."upgrades.php"))
                require MAIN_DIR.DIR."feathers".DIR.$feather.DIR."upgrades.php";
        }

        @unlink(INCLUDES_DIR.DIR."upgrading.lock");
        $upgraded = true;
    }

    #---------------------------------------------
    # Upgrading Ends
    #---------------------------------------------

    foreach ((array) alert() as $message)
        echo '<span role="alert">'.sanitize_html($message).'</span>'."\n";

            ?></pre>
<?php if (!$upgraded): ?>
            <h1><?php echo __("Halt!"); ?></h1>
            <p><?php echo __("Please take these precautionary measures before you upgrade:"); ?></p>
            <ol>
                <li><?php echo __("<strong>Backup your database before proceeding!</strong>"); ?></li>
                <li><?php echo __("Tell your users that your site is offline for maintenance."); ?></li>
            </ol>
            <form action="upgrade.php" method="post">
                <button type="submit" name="upgrade" value="yes"><?php echo __("Upgrade me!"); ?></button>
            </form>
<?php else: ?>
            <h1><?php echo __("Upgrade Complete"); ?></h1>
            <h2><?php echo __("What now?"); ?></h2>
            <ol>
                <li><?php echo __("Take action to resolve any errors reported on this page."); ?></li>
                <li><?php echo __("Run this upgrader again if you need to."); ?></li>
                <li><?php echo __("Delete <em>upgrade.php</em> once you are finished upgrading."); ?></li>
            </ol>
            <a class="big" href="<?php echo $config->url.'/'; ?>"><?php echo __("Take me to my site!"); ?></a>
<?php endif; ?>
        </div>
    </body>
</html>
