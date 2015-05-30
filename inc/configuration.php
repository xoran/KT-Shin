<?php
// Config file: contains shaarli and php's configuration data

// -----------------------------------------------------------------------------------------------
// Hardcoded parameter (These parameters can be overwritten by creating the file /config/options.php)
$GLOBALS['config']['DATADIR'] = 'data'; // Data subdirectory
$GLOBALS['config']['CONFIG_FILE'] = $GLOBALS['config']['DATADIR'].'/config.php'; // Configuration file (user login/password)
$GLOBALS['config']['DATASTORE'] = $GLOBALS['config']['DATADIR'].'/datastore.php'; // Data storage file.
$GLOBALS['config']['LINKS_PER_PAGE'] = 50; // Default links per page.
$GLOBALS['config']['IPBANS_FILENAME'] = $GLOBALS['config']['DATADIR'].'/ipbans.php'; // File storage for failures and bans.
$GLOBALS['config']['BAN_AFTER'] = 4;        // Ban IP after this many failures.
$GLOBALS['config']['BAN_DURATION'] = 1800;  // Ban duration for IP address after login failures (in seconds) (1800 sec. = 30 minutes)
$GLOBALS['config']['OPEN_SHAARLI'] = false; // If true, anyone can add/edit/delete links without having to login
$GLOBALS['config']['HIDE_TIMESTAMPS'] = false; // If true, the moment when links were saved are not shown to users that are not logged in.
$GLOBALS['config']['ENABLE_THUMBNAILS'] = true; // Enable thumbnails in links.
$GLOBALS['config']['CACHEDIR'] = 'cache'; // Cache directory for thumbnails for SLOW services (like flickr)
$GLOBALS['config']['PAGECACHE'] = 'pagecache'; // Page cache directory.
$GLOBALS['config']['ENABLE_LOCALCACHE'] = true; // Enable Shaarli to store thumbnail in a local cache. Disable to reduce webspace usage.
$GLOBALS['config']['PUBSUBHUB_URL'] = ''; // PubSubHubbub support. Put an empty string to disable, or put your hub url here to enable.
$GLOBALS['config']['UPDATECHECK_FILENAME'] = $GLOBALS['config']['DATADIR'].'/lastupdatecheck.txt'; // For updates check of Shaarli.
$GLOBALS['config']['UPDATECHECK_INTERVAL'] = 86400 ; // Updates check frequency for Shaarli. 86400 seconds=24 hours

                                          // Note: You must have publisher.php in the same directory as Shaarli index.php
// -----------------------------------------------------------------------------------------------
// You should not touch below (or at your own risks !)
// Optionnal config file.
if (is_file($GLOBALS['config']['DATADIR'].'/options.php')) require($GLOBALS['config']['DATADIR'].'/options.php');


define('shaarli_version','0.0.41 beta');
define('KTSHIN_VERSION', '0.2.22 beta');
define('LANG','fr');
define('PHPPREFIX','<?php /* '); // Prefix to encapsulate data in php code.
define('PHPSUFFIX',' */ ?>'); // Suffix to encapsulate data in php code.
// http://server.com/x/shaarli --> /shaarli/
define('WEB_PATH', substr($_SERVER["REQUEST_URI"], 0, 1+strrpos($_SERVER["REQUEST_URI"], '/', 0)));

// Force cookie path (but do not change lifetime)
$cookie=session_get_cookie_params();
$cookiedir = ''; if(dirname($_SERVER['SCRIPT_NAME'])!='/') $cookiedir=dirname($_SERVER["SCRIPT_NAME"]).'/';
session_set_cookie_params($cookie['lifetime'],$cookiedir,$_SERVER['HTTP_HOST']); // Set default cookie expiration and path.


// Set session parameters on server side.
define('INACTIVITY_TIMEOUT',3600); // (in seconds). If the user does not access any page within this time, his/her session is considered expired.
ini_set('session.use_cookies', 1);       // Use cookies to store session.
ini_set('session.use_only_cookies', 1);  // Force cookies for session (phpsessionID forbidden in URL)
ini_set('session.use_trans_sid', false); // Prevent php to use sessionID in URL if cookies are disabled.
session_name('shaarli');
if (session_id() == '') session_start();  // Start session if needed (Some server auto-start sessions).

// PHP Settings
ini_set('max_input_time','60');  // High execution time in case of problematic imports/exports.
ini_set('memory_limit', '128M');  // Try to set max upload file size and read (May not work on some hosts).
ini_set('post_max_size', '16M');
ini_set('upload_max_filesize', '16M');
ini_set('session.save_path', dirname($_SERVER['SCRIPT_FILENAME']).'/sessions');



?>
