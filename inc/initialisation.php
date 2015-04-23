<?php 
// initialisation

include "inc/rain.tpl.class.php"; //include Rain TPL
raintpl::$tpl_dir = "tpl/"; // template directory
if (!is_dir('tmp')) { mkdir('tmp',0705); chmod('tmp',0705); }
raintpl::$cache_dir = "tmp/"; // cache directory

ob_start();  // Output buffering for the page cache.


// In case stupid admin has left magic_quotes enabled in php.ini:
if (get_magic_quotes_gpc())
{
    function stripslashes_deep($value) { $value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value); return $value; }
    $_POST = array_map('stripslashes_deep', $_POST);
    $_GET = array_map('stripslashes_deep', $_GET);
    $_COOKIE = array_map('stripslashes_deep', $_COOKIE);
}

// Prevent caching on client side or proxy: (yes, it's ugly)
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Directories creations (Note that your web host may require differents rights than 705.)
if (!is_writable(realpath(dirname(__FILE__)))) die('<pre>ERROR: Shaarli does not have the right to write in its own directory ('.realpath(dirname(__FILE__)).').</pre>');
if (!is_dir($GLOBALS['config']['DATADIR'])) { mkdir($GLOBALS['config']['DATADIR'],0705); chmod($GLOBALS['config']['DATADIR'],0705); }
if (!is_dir('tmp')) { mkdir('tmp',0705); chmod('tmp',0705); } // For RainTPL temporary files.
if (!is_file($GLOBALS['config']['DATADIR'].'/.htaccess')) { file_put_contents($GLOBALS['config']['DATADIR'].'/.htaccess',"Allow from none\nDeny from all\n"); } // Protect data files.
// Second check to see if Shaarli can write in its directory, because on some hosts is_writable() is not reliable.
if (!is_file($GLOBALS['config']['DATADIR'].'/.htaccess')) die('<pre>ERROR: Shaarli does not have the right to write in its data directory ('.realpath($GLOBALS['config']['DATADIR']).').</pre>');
if ($GLOBALS['config']['ENABLE_LOCALCACHE'])
{
    if (!is_dir($GLOBALS['config']['CACHEDIR'])) { mkdir($GLOBALS['config']['CACHEDIR'],0705); chmod($GLOBALS['config']['CACHEDIR'],0705); }
    if (!is_file($GLOBALS['config']['CACHEDIR'].'/.htaccess')) { file_put_contents($GLOBALS['config']['CACHEDIR'].'/.htaccess',"Allow from none\nDeny from all\n"); } // Protect data files.
}

// Handling of old config file which do not have the new parameters.
if (empty($GLOBALS['title'])) $GLOBALS['title']='Shared links on '.htmlspecialchars(indexUrl());
if (empty($GLOBALS['timezone'])) $GLOBALS['timezone']=date_default_timezone_get();
if (empty($GLOBALS['redirector'])) $GLOBALS['redirector']='';
if (empty($GLOBALS['disablesessionprotection'])) $GLOBALS['disablesessionprotection']=false;
if (empty($GLOBALS['disablejquery'])) $GLOBALS['disablejquery']=false;
if (empty($GLOBALS['privateLinkByDefault'])) $GLOBALS['privateLinkByDefault']=false;
// I really need to rewrite Shaarli with a proper configuration manager.

// Run config screen if first run:
if (!is_file($GLOBALS['config']['CONFIG_FILE'])) install();

require $GLOBALS['config']['CONFIG_FILE'];  // Read login/password hash into $GLOBALS.

// a token depending of deployment salt, user password, and the current ip
define('STAY_SIGNED_IN_TOKEN', sha1($GLOBALS['hash'].$_SERVER["REMOTE_ADDR"].$GLOBALS['salt']));

autoLocale(); // Sniff browser language and set date format accordingly.
header('Content-Type: text/html; charset=utf-8'); // We use UTF-8 for proper international characters handling.


?>
