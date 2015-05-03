<?php
// All the shaarli's functions are here
// except the core ones


// Check php version
function checkphpversion()
{
    if (version_compare(PHP_VERSION, '5.1.0') < 0)
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Your server supports php '.PHP_VERSION.'. Shaarli requires at least php 5.1.0, and thus cannot run. Sorry.';
        exit;
    }
}

// Checks if an update is available for Shaarli.
// (at most once a day, and only for registered user.)
// Output: '' = no new version.
//         other= the available version.
function checkUpdate()
{
    if (!isLoggedIn()) return ''; // Do not check versions for visitors.

    // Get latest version number at most once a day.
    if (!is_file($GLOBALS['config']['UPDATECHECK_FILENAME']) || (filemtime($GLOBALS['config']['UPDATECHECK_FILENAME'])<time()-($GLOBALS['config']['UPDATECHECK_INTERVAL'])))
    {
        $version=shaarli_version;
        list($httpstatus,$headers,$data) = getHTTP('http://sebsauvage.net/files/shaarli_version.txt',2);
        if (strpos($httpstatus,'200 OK')!==false) $version=$data;
        // If failed, nevermind. We don't want to bother the user with that.
        file_put_contents($GLOBALS['config']['UPDATECHECK_FILENAME'],$version); // touch file date
    }
    // Compare versions:
    $newestversion=file_get_contents($GLOBALS['config']['UPDATECHECK_FILENAME']);
    if (version_compare($newestversion,shaarli_version)==1) return $newestversion;
    return '';
}


// -----------------------------------------------------------------------------------------------
// Simple cache system (mainly for the RSS/ATOM feeds).

class pageCache
{
    private $url; // Full URL of the page to cache (typically the value returned by pageUrl())
    private $shouldBeCached; // boolean: Should this url be cached ?
    private $filename; // Name of the cache file for this url

    /*
         $url = url (typically the value returned by pageUrl())
         $shouldBeCached = boolean. If false, the cache will be disabled.
    */
    public function __construct($url,$shouldBeCached)
    {
        $this->url = $url;
        $this->filename = $GLOBALS['config']['PAGECACHE'].'/'.sha1($url).'.cache';
        $this->shouldBeCached = $shouldBeCached;
    }

    // If the page should be cached and a cached version exists,
    // returns the cached version (otherwise, return null).
    public function cachedVersion()
    {
        if (!$this->shouldBeCached) return null;
        if (is_file($this->filename)) { return file_get_contents($this->filename); exit; }
        return null;
    }

    // Put a page in the cache.
    public function cache($page)
    {
        if (!$this->shouldBeCached) return;
        if (!is_dir($GLOBALS['config']['PAGECACHE'])) { mkdir($GLOBALS['config']['PAGECACHE'],0705); chmod($GLOBALS['config']['PAGECACHE'],0705); }
        file_put_contents($this->filename,$page);
    }

    // Purge the whole cache.
    // (call with pageCache::purgeCache())
    public static function purgeCache()
    {
        if (is_dir($GLOBALS['config']['PAGECACHE']))
        {
            $handler = opendir($GLOBALS['config']['PAGECACHE']);
            if ($handler!==false)
            {
                while (($filename = readdir($handler))!==false)
                {
                    if (endsWith($filename,'.cache')) { unlink($GLOBALS['config']['PAGECACHE'].'/'.$filename); }
                }
                closedir($handler);
            }
        }
    }

}


// -----------------------------------------------------------------------------------------------
// Log to text file
function logm($message)
{
    $t = strval(date('Y/m/d_H:i:s')).' - '.$_SERVER["REMOTE_ADDR"].' - '.strval($message)."\n";
    file_put_contents($GLOBALS['config']['DATADIR'].'/log.txt',$t,FILE_APPEND);
}

// Same as nl2br(), but escapes < and >
function nl2br_escaped($html)
{
    return str_replace('>','&gt;',str_replace('<','&lt;',nl2br($html)));
}

/* Returns the small hash of a string, using RFC 4648 base64url format
   eg. smallHash('20111006_131924') --> yZH23w
   Small hashes:
     - are unique (well, as unique as crc32, at last)
     - are always 6 characters long.
     - only use the following characters: a-z A-Z 0-9 - _ @
     - are NOT cryptographically secure (they CAN be forged)
   In Shaarli, they are used as a tinyurl-like link to individual entries.
*/
function smallHash($text)
{
    $t = rtrim(base64_encode(hash('crc32',$text,true)),'=');
    return strtr($t, '+/', '-_');
}

// In a string, converts urls to clickable links.
// Function inspired from http://www.php.net/manual/en/function.preg-replace.php#85722
function text2clickable($url)
{
    $redir = empty($GLOBALS['redirector']) ? '' : $GLOBALS['redirector'];
    return preg_replace('!(((?:https?|ftp|file)://|apt:|magnet:)\S+[[:alnum:]]/?)!si','<a href="'.$redir.'$1" rel="nofollow">$1</a>',$url);
}

// This function inserts &nbsp; where relevant so that multiple spaces are properly displayed in HTML
// even in the absence of <pre>  (This is used in description to keep text formatting)
function keepMultipleSpaces($text)
{
    return str_replace('  ',' &nbsp;',$text);

}
// ------------------------------------------------------------------------------------------
// Sniff browser language to display dates in the right format automatically.
// (Note that is may not work on your server if the corresponding local is not installed.)
function autoLocale()
{
    $loc='en_US'; // Default if browser does not send HTTP_ACCEPT_LANGUAGE
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) // eg. "fr,fr-fr;q=0.8,en;q=0.5,en-us;q=0.3"
    {   // (It's a bit crude, but it works very well. Prefered language is always presented first.)
        if (preg_match('/([a-z]{2}(-[a-z]{2})?)/i',$_SERVER['HTTP_ACCEPT_LANGUAGE'],$matches)) $loc=$matches[1];
    }
    setlocale(LC_TIME,$loc);  // LC_TIME = Set local for date/time format only.
}

// ------------------------------------------------------------------------------------------
// PubSubHubbub protocol support (if enabled)  [UNTESTED]
// (Source: http://aldarone.fr/les-flux-rss-shaarli-et-pubsubhubbub/ )
if (!empty($GLOBALS['config']['PUBSUBHUB_URL'])) include './publisher.php';
function pubsubhub()
{
    if (!empty($GLOBALS['config']['PUBSUBHUB_URL']))
    {
       $p = new Publisher($GLOBALS['config']['PUBSUBHUB_URL']);
       $topic_url = array (
                       indexUrl().'?do=atom',
                       indexUrl().'?do=rss'
                    );
       $p->publish_update($topic_url);
    }
}

// ------------------------------------------------------------------------------------------
// Session management

// Returns the IP address of the client (Used to prevent session cookie hijacking.)
function allIPs()
{
    $ip = $_SERVER["REMOTE_ADDR"];
    // Then we use more HTTP headers to prevent session hijacking from users behind the same proxy.
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip=$ip.'_'.$_SERVER['HTTP_X_FORWARDED_FOR']; }
    if (isset($_SERVER['HTTP_CLIENT_IP'])) { $ip=$ip.'_'.$_SERVER['HTTP_CLIENT_IP']; }
    return $ip;
}

function fillSessionInfo() {
	$_SESSION['uid'] = sha1(uniqid('',true).'_'.mt_rand()); // generate unique random number (different than phpsessionid)
	$_SESSION['ip']=allIPs();                // We store IP address(es) of the client to make sure session is not hijacked.
	$_SESSION['username']=$GLOBALS['login'];
	$_SESSION['expires_on']=time()+INACTIVITY_TIMEOUT;  // Set session expiration.
}

// Check that user/password is correct.
function check_auth($login,$password)
{
    $hash = sha1($password.$login.$GLOBALS['salt']);
    if ($login==$GLOBALS['login'] && $hash==$GLOBALS['hash'])
    {   // Login/password is correct.
		fillSessionInfo();
        logm('Login successful');
        return true;
    }
    logm('Login failed for user '.$login);
    return false;
}

// Returns true if the user is logged in.
function isLoggedIn()
{
    if ($GLOBALS['config']['OPEN_SHAARLI']) return true;

    if (!isset($GLOBALS['login'])) return false;  // Shaarli is not configured yet.

	if (@$_COOKIE['shaarli_staySignedIn']===STAY_SIGNED_IN_TOKEN)
	{
		fillSessionInfo();
		return true;
	}
    // If session does not exist on server side, or IP address has changed, or session has expired, logout.
    if (empty($_SESSION['uid']) || ($GLOBALS['disablesessionprotection']==false && $_SESSION['ip']!=allIPs()) || time()>=$_SESSION['expires_on'])
    {
        logout();
        return false;
    }
    if (!empty($_SESSION['longlastingsession']))  $_SESSION['expires_on']=time()+$_SESSION['longlastingsession']; // In case of "Stay signed in" checked.
    else $_SESSION['expires_on']=time()+INACTIVITY_TIMEOUT; // Standard session expiration date.

    return true;
}

// Force logout.
function logout() { if (isset($_SESSION)) { unset($_SESSION['uid']); unset($_SESSION['ip']); unset($_SESSION['username']); unset($_SESSION['privateonly']); }
setcookie('shaarli_staySignedIn', FALSE, 0, WEB_PATH);
}

// ------------------------------------------------------------------------------------------
// Brute force protection system
// Several consecutive failed logins will ban the IP address for 30 minutes.
if (!is_file($GLOBALS['config']['IPBANS_FILENAME'])) file_put_contents($GLOBALS['config']['IPBANS_FILENAME'], "<?php\n\$GLOBALS['IPBANS']=".var_export(array('FAILURES'=>array(),'BANS'=>array()),true).";\n?>");
include $GLOBALS['config']['IPBANS_FILENAME'];
// Signal a failed login. Will ban the IP if too many failures:
function ban_loginFailed()
{
    $ip=$_SERVER["REMOTE_ADDR"]; $gb=$GLOBALS['IPBANS'];
    if (!isset($gb['FAILURES'][$ip])) $gb['FAILURES'][$ip]=0;
    $gb['FAILURES'][$ip]++;
    if ($gb['FAILURES'][$ip]>($GLOBALS['config']['BAN_AFTER']-1))
    {
        $gb['BANS'][$ip]=time()+$GLOBALS['config']['BAN_DURATION'];
        logm('IP address banned from login');
    }
    $GLOBALS['IPBANS'] = $gb;
    file_put_contents($GLOBALS['config']['IPBANS_FILENAME'], "<?php\n\$GLOBALS['IPBANS']=".var_export($gb,true).";\n?>");
}

// Signals a successful login. Resets failed login counter.
function ban_loginOk()
{
    $ip=$_SERVER["REMOTE_ADDR"];
    $gb=$GLOBALS['IPBANS'];
    unset($gb['FAILURES'][$ip]);
    unset($gb['BANS'][$ip]);
    $GLOBALS['IPBANS'] = $gb;
    file_put_contents($GLOBALS['config']['IPBANS_FILENAME'], "<?php\n\$GLOBALS['IPBANS']=".var_export($gb,true).";\n?>");
}

// Checks if the user CAN login. If 'true', the user can try to login.
function ban_canLogin()
{
    $ip=$_SERVER["REMOTE_ADDR"]; $gb=$GLOBALS['IPBANS'];
    if (isset($gb['BANS'][$ip]))
    {
        // User is banned. Check if the ban has expired:
        if ($gb['BANS'][$ip]<=time())
        {   // Ban expired, user can try to login again.
            logm('Ban lifted.');
            unset($gb['FAILURES'][$ip]); unset($gb['BANS'][$ip]);
            file_put_contents($GLOBALS['config']['IPBANS_FILENAME'], "<?php\n\$GLOBALS['IPBANS']=".var_export($gb,true).";\n?>");
            return true; // Ban has expired, user can login.
        }
        return false; // User is banned.
    }
    return true; // User is not banned.
}

// ------------------------------------------------------------------------------------------
// Misc utility functions:

// Returns the server URL (including port and http/https), without path.
// eg. "http://myserver.com:8080"
// You can append $_SERVER['SCRIPT_NAME'] to get the current script URL.
function serverUrl()
{
    $https = (!empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS'])=='on')) || $_SERVER["SERVER_PORT"]=='443'; // HTTPS detection.
    $serverport = ($_SERVER["SERVER_PORT"]=='80' || ($https && $_SERVER["SERVER_PORT"]=='443') ? '' : ':'.$_SERVER["SERVER_PORT"]);
    return 'http'.($https?'s':'').'://'.$_SERVER['HTTP_HOST'].$serverport;
}

// Returns the absolute URL of current script, without the query.
// (eg. http://sebsauvage.net/links/)
function indexUrl()
{
    $scriptname = $_SERVER["SCRIPT_NAME"];
    // If the script is named 'index.php', we remove it (for better looking URLs,
    // eg. http://mysite.com/shaarli/?abcde instead of http://mysite.com/shaarli/index.php?abcde)
    if (endswith($scriptname,'index.php')) $scriptname = substr($scriptname,0,strlen($scriptname)-9);
    return serverUrl() . $scriptname;
}

// Returns the absolute URL of current script, WITH the query.
// (eg. http://sebsauvage.net/links/?toto=titi&spamspamspam=humbug)
function pageUrl()
{
    return indexUrl().(!empty($_SERVER["QUERY_STRING"]) ? '?'.$_SERVER["QUERY_STRING"] : '');
}

// Convert post_max_size/upload_max_filesize (eg.'16M') parameters to bytes.
function return_bytes($val)
{
    $val = trim($val); $last=strtolower($val[strlen($val)-1]);
    switch($last)
    {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

// Try to determine max file size for uploads (POST).
// Returns an integer (in bytes)
function getMaxFileSize()
{
    $size1 = return_bytes(ini_get('post_max_size'));
    $size2 = return_bytes(ini_get('upload_max_filesize'));
    // Return the smaller of two:
    $maxsize = min($size1,$size2);
    // FIXME: Then convert back to readable notations ? (eg. 2M instead of 2000000)
    return $maxsize;
}

// Tells if a string start with a substring or not.
function startsWith($haystack,$needle,$case=true)
{
    if($case){return (strcmp(substr($haystack, 0, strlen($needle)),$needle)===0);}
    return (strcasecmp(substr($haystack, 0, strlen($needle)),$needle)===0);
}

// Tells if a string ends with a substring or not.
function endsWith($haystack,$needle,$case=true)
{
    if($case){return (strcmp(substr($haystack, strlen($haystack) - strlen($needle)),$needle)===0);}
    return (strcasecmp(substr($haystack, strlen($haystack) - strlen($needle)),$needle)===0);
}

/*  Converts a linkdate time (YYYYMMDD_HHMMSS) of an article to a timestamp (Unix epoch)
    (used to build the ADD_DATE attribute in Netscape-bookmarks file)
    PS: I could have used strptime(), but it does not exist on Windows. I'm too kind. */
function linkdate2timestamp($linkdate)
{
    $Y=$M=$D=$h=$m=$s=0;
    $r = sscanf($linkdate,'%4d%2d%2d_%2d%2d%2d',$Y,$M,$D,$h,$m,$s);
    return mktime($h,$m,$s,$M,$D,$Y);
}

/*  Converts a linkdate time (YYYYMMDD_HHMMSS) of an article to a RFC822 date.
    (used to build the pubDate attribute in RSS feed.)  */
function linkdate2rfc822($linkdate)
{
    return date('r',linkdate2timestamp($linkdate)); // 'r' is for RFC822 date format.
}

/*  Converts a linkdate time (YYYYMMDD_HHMMSS) of an article to a ISO 8601 date.
    (used to build the updated tags in ATOM feed.)  */
function linkdate2iso8601($linkdate)
{
    return date('c',linkdate2timestamp($linkdate)); // 'c' is for ISO 8601 date format.
}

/*  Converts a linkdate time (YYYYMMDD_HHMMSS) of an article to a localized date format.
    (used to display link date on screen)
    The date format is automatically chosen according to locale/languages sniffed from browser headers (see autoLocale()). */
function linkdate2locale($linkdate)
{
    return utf8_encode(strftime('%c',linkdate2timestamp($linkdate))); // %c is for automatic date format according to locale.
    // Note that if you use a local which is not installed on your webserver,
    // the date will not be displayed in the chosen locale, but probably in US notation.
}

// Parse HTTP response headers and return an associative array.
function http_parse_headers_shaarli( $headers )
{
    $res=array();
    foreach($headers as $header)
    {
        $i = strpos($header,': ');
        if ($i!==false)
        {
            $key=substr($header,0,$i);
            $value=substr($header,$i+2,strlen($header)-$i-2);
            $res[$key]=$value;
        }
    }
    return $res;
}

/* GET an URL.
   Input: $url : url to get (http://...)
          $timeout : Network timeout (will wait this many seconds for an anwser before giving up).
   Output: An array.  [0] = HTTP status message (eg. "HTTP/1.1 200 OK") or error message
                      [1] = associative array containing HTTP response headers (eg. echo getHTTP($url)[1]['Content-Type'])
                      [2] = data
    Example: list($httpstatus,$headers,$data) = getHTTP('http://sebauvage.net/');
             if (strpos($httpstatus,'200 OK')!==false)
                 echo 'Data type: '.htmlspecialchars($headers['Content-Type']);
             else
                 echo 'There was an error: '.htmlspecialchars($httpstatus)
*/
function getHTTP($url,$timeout=30)
{
    try
    {
        $options = array('http'=>array('method'=>'GET','timeout' => $timeout, 'user_agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:23.0) Gecko/20100101 Firefox/23.0')); // Force network timeout
        $context = stream_context_create($options);
        $data=file_get_contents($url,false,$context,-1, 4000000); // We download at most 4 Mb from source.
        if (!$data) { return array('HTTP Error',array(),''); }
        $httpStatus=$http_response_header[0]; // eg. "HTTP/1.1 200 OK"
        $responseHeaders=http_parse_headers_shaarli($http_response_header);
        return array($httpStatus,$responseHeaders,$data);
    }
    catch (Exception $e)  // getHTTP *can* fail silentely (we don't care if the title cannot be fetched)
    {
        return array($e->getMessage(),'','');
    }
}

// Extract title from an HTML document.
// (Returns an empty string if not found.)
function html_extract_title($html)
{
  return preg_match('!<title>(.*?)</title>!is', $html, $matches) ? trim(str_replace("\n",' ', $matches[1])) : '' ;
}

// ------------------------------------------------------------------------------------------
// Token management for XSRF protection
// Token should be used in any form which acts on data (create,update,delete,import...).
if (!isset($_SESSION['tokens'])) $_SESSION['tokens']=array();  // Token are attached to the session.

// Returns a token.
function getToken()
{
    $rnd = sha1(uniqid('',true).'_'.mt_rand().$GLOBALS['salt']);  // We generate a random string.
    $_SESSION['tokens'][$rnd]=1;  // Store it on the server side.
    return $rnd;
}

// Tells if a token is ok. Using this function will destroy the token.
// true=token is ok.
function tokenOk($token)
{
    if (isset($_SESSION['tokens'][$token]))
    {
        unset($_SESSION['tokens'][$token]); // Token is used: destroy it.
        return true; // Token is ok.
    }
    return false; // Wrong token, or already used.
}

// ------------------------------------------------------------------------------------------
/* This class is in charge of building the final page.
   (This is basically a wrapper around RainTPL which pre-fills some fields.)
   p = new pageBuilder;
   p.assign('myfield','myvalue');
   p.renderPage('mytemplate');

*/
class pageBuilder
{
    private $tpl; // RainTPL template

    function __construct()
    {
        $this->tpl=false;
    }

    private function initialize()
    {
        $this->tpl = new RainTPL;
        $this->tpl->assign('newversion',checkUpdate());
        $this->tpl->assign('feedurl',htmlspecialchars(indexUrl()));
        $searchcrits=''; // Search criteria
        if (!empty($_GET['searchtags'])) $searchcrits.='&searchtags='.urlencode($_GET['searchtags']);
        elseif (!empty($_GET['searchterm'])) $searchcrits.='&searchterm='.urlencode($_GET['searchterm']);
        $this->tpl->assign('searchcrits',$searchcrits);
        $this->tpl->assign('source',indexUrl());
        $this->tpl->assign('version',shaarli_version);
        $this->tpl->assign('scripturl',indexUrl());
        $this->tpl->assign('pagetitle','Shaarli');
        $this->tpl->assign('privateonly',!empty($_SESSION['privateonly'])); // Show only private links ?
        if (!empty($GLOBALS['title'])) $this->tpl->assign('pagetitle',$GLOBALS['title']);
        if (!empty($GLOBALS['pagetitle'])) $this->tpl->assign('pagetitle',$GLOBALS['pagetitle']);
        $this->tpl->assign('shaarlititle',empty($GLOBALS['title']) ? 'Shaarli': $GLOBALS['title'] );
        return;
    }

    // The following assign() method is basically the same as RainTPL (except that it's lazy)
    public function assign($what,$where)
    {
        if ($this->tpl===false) $this->initialize(); // Lazy initialization
        $this->tpl->assign($what,$where);
    }

    // Render a specific page (using a template).
    // eg. pb.renderPage('picwall')
    public function renderPage($page)
    {
        if ($this->tpl===false) $this->initialize(); // Lazy initialization
        $this->tpl->draw($page);
    }
}

// ------------------------------------------------------------------------------------------
/* Data storage for links.
   This object behaves like an associative array.
   Example:
      $mylinks = new linkdb();
      echo $mylinks['20110826_161819']['title'];
      foreach($mylinks as $link)
         echo $link['title'].' at url '.$link['url'].' ; description:'.$link['description'];

   Available keys:
       title : Title of the link
       url : URL of the link. Can be absolute or relative. Relative URLs are permalinks (eg.'?m-ukcw')
       description : description of the entry
       private : Is this link private ? 0=no, other value=yes
       linkdate : date of the creation of this entry, in the form YYYYMMDD_HHMMSS (eg.'20110914_192317')
       tags : tags attached to this entry (separated by spaces)

   We implement 3 interfaces:
     - ArrayAccess so that this object behaves like an associative array.
     - Iterator so that this object can be used in foreach() loops.
     - Countable interface so that we can do a count() on this object.
*/
class linkdb implements Iterator, Countable, ArrayAccess
{
    private $links; // List of links (associative array. Key=linkdate (eg. "20110823_124546"), value= associative array (keys:title,description...)
    private $urls;  // List of all recorded URLs (key=url, value=linkdate) for fast reserve search (url-->linkdate)
    private $keys;  // List of linkdate keys (for the Iterator interface implementation)
    private $position; // Position in the $this->keys array. (for the Iterator interface implementation.)
    private $loggedin; // Is the used logged in ? (used to filter private links)

    // Constructor:
    function __construct($isLoggedIn)
    // Input : $isLoggedIn : is the used logged in ?
    {
        $this->loggedin = $isLoggedIn;
        $this->checkdb(); // Make sure data file exists.
        $this->readdb();  // Then read it.
    }

    // ---- Countable interface implementation
    public function count() { return count($this->links); }

    // ---- ArrayAccess interface implementation
    public function offsetSet($offset, $value)
    {
        if (!$this->loggedin) die('You are not authorized to add a link.');
        if (empty($value['linkdate']) || empty($value['url'])) die('Internal Error: A link should always have a linkdate and url.');
        if (empty($offset)) die('You must specify a key.');
        $this->links[$offset] = $value;
        $this->urls[$value['url']]=$offset;
    }
    public function offsetExists($offset) { return array_key_exists($offset,$this->links); }
    public function offsetUnset($offset)
    {
        if (!$this->loggedin) die('You are not authorized to delete a link.');
        $url = $this->links[$offset]['url']; unset($this->urls[$url]);
        unset($this->links[$offset]);
    }
    public function offsetGet($offset) { return isset($this->links[$offset]) ? $this->links[$offset] : null; }

    // ---- Iterator interface implementation
    function rewind() { $this->keys=array_keys($this->links); rsort($this->keys); $this->position=0; } // Start over for iteration, ordered by date (latest first).
    function key() { return $this->keys[$this->position]; } // current key
    function current() { return $this->links[$this->keys[$this->position]]; } // current value
    function next() { ++$this->position; } // go to next item
    function valid() { return isset($this->keys[$this->position]); }    // Check if current position is valid.

    // ---- Misc methods
    private function checkdb() // Check if db directory and file exists.
    {
        if (!file_exists($GLOBALS['config']['DATASTORE'])) // Create a dummy database for example.
        {
             $this->links = array();
             $link = array('title'=>'Shaarli - sebsauvage.net','url'=>'http://sebsauvage.net/wiki/doku.php?id=php:shaarli','description'=>'Welcome to Shaarli ! This is a bookmark. To edit or delete me, you must first login.','private'=>0,'linkdate'=>'20110914_190000','tags'=>'opensource software');
             $this->links[$link['linkdate']] = $link;
             $link = array('title'=>'My secret stuff... - Pastebin.com','url'=>'http://sebsauvage.net/paste/?8434b27936c09649#bR7XsXhoTiLcqCpQbmOpBi3rq2zzQUC5hBI7ZT1O3x8=','description'=>'SShhhh!!  I\'m a private link only YOU can see. You can delete me too.','private'=>1,'linkdate'=>'20110914_074522','tags'=>'secretstuff');
             $this->links[$link['linkdate']] = $link;
             file_put_contents($GLOBALS['config']['DATASTORE'], PHPPREFIX.base64_encode(gzdeflate(serialize($this->links))).PHPSUFFIX); // Write database to disk
        }
    }

    // Read database from disk to memory
    private function readdb()
    {
        // Read data
        $this->links=(file_exists($GLOBALS['config']['DATASTORE']) ? unserialize(gzinflate(base64_decode(substr(file_get_contents($GLOBALS['config']['DATASTORE']),strlen(PHPPREFIX),-strlen(PHPSUFFIX))))) : array() );
        // Note that gzinflate is faster than gzuncompress. See: http://www.php.net/manual/en/function.gzdeflate.php#96439

        // If user is not logged in, filter private links.
        if (!$this->loggedin)
        {
            $toremove=array();
            foreach($this->links as $link) { if ($link['private']!=0) $toremove[]=$link['linkdate']; }
            foreach($toremove as $linkdate) { unset($this->links[$linkdate]); }
        }

        // Keep the list of the mapping URLs-->linkdate up-to-date.
        $this->urls=array();
        foreach($this->links as $link) { $this->urls[$link['url']]=$link['linkdate']; }
    }

    // Save database from memory to disk.
    public function savedb()
    {
        if (!$this->loggedin) die('You are not authorized to change the database.');
        file_put_contents($GLOBALS['config']['DATASTORE'], PHPPREFIX.base64_encode(gzdeflate(serialize($this->links))).PHPSUFFIX);
        invalidateCaches();
    }

    // Returns the link for a given URL (if it exists). false it does not exist.
    public function getLinkFromUrl($url)
    {
        if (isset($this->urls[$url])) return $this->links[$this->urls[$url]];
        return false;
    }

    // Case insentitive search among links (in url, title and description). Returns filtered list of links.
    // eg. print_r($mydb->filterFulltext('hollandais'));
    public function filterFulltext($searchterms)
    {
        // FIXME: explode(' ',$searchterms) and perform a AND search.
        // FIXME: accept double-quotes to search for a string "as is" ?
        $filtered=array();
        $s = strtolower($searchterms);
        foreach($this->links as $l)
        {
            $found=   (strpos(strtolower($l['title']),$s)!==false)
                   || (strpos(strtolower($l['description']),$s)!==false)
                   || (strpos(strtolower($l['url']),$s)!==false)
                   || (strpos(strtolower($l['tags']),$s)!==false);
            if ($found) $filtered[$l['linkdate']] = $l;
        }
        krsort($filtered);
        return $filtered;
    }

    // Filter by tag.
    // You can specify one or more tags (tags can be separated by space or comma).
    // eg. print_r($mydb->filterTags('linux programming'));
    public function filterTags($tags,$casesensitive=false)
    {
        $t = str_replace(',',' ',($casesensitive?$tags:strtolower($tags)));
        $searchtags=explode(' ',$t);
        $filtered=array();
        foreach($this->links as $l)
        {
            $linktags = explode(' ',($casesensitive?$l['tags']:strtolower($l['tags'])));
            if (count(array_intersect($linktags,$searchtags)) == count($searchtags))
                $filtered[$l['linkdate']] = $l;
        }
        krsort($filtered);
        return $filtered;
    }

    // Filter by day. Day must be in the form 'YYYYMMDD' (eg. '20120125')
    // Sort order is: older articles first.
    // eg. print_r($mydb->filterDay('20120125'));
    public function filterDay($day)
    {
        $filtered=array();
        foreach($this->links as $l)
        {
            if (startsWith($l['linkdate'],$day)) $filtered[$l['linkdate']] = $l;
        }
        ksort($filtered);
        return $filtered;
    }
    // Filter by smallHash.
    // Only 1 article is returned.
    public function filterSmallHash($smallHash)
    {
        $filtered=array();
        foreach($this->links as $l)
        {
            if ($smallHash==smallHash($l['linkdate'])) // Yes, this is ugly and slow
            {
                $filtered[$l['linkdate']] = $l;
                return $filtered;
            }
        }
        return $filtered;
    }

    // Returns the list of all tags
    // Output: associative array key=tags, value=0
    public function allTags()
    {
        $tags=array();
        foreach($this->links as $link)
            foreach(explode(' ',$link['tags']) as $tag)
                if (!empty($tag)) $tags[$tag]=(empty($tags[$tag]) ? 1 : $tags[$tag]+1);
        arsort($tags); // Sort tags by usage (most used tag first)
        return $tags;
    }

    // Returns the list of days containing articles (oldest first)
    // Output: An array containing days (in format YYYYMMDD).
    public function days()
    {
        $linkdays=array();
        foreach(array_keys($this->links) as $day)
        {
            $linkdays[substr($day,0,8)]=0;
        }
        $linkdays=array_keys($linkdays);
        sort($linkdays);
        return $linkdays;
    }
}

// ------------------------------------------------------------------------------------------
// Ouput the last N links in RSS 2.0 format.
function showRSS()
{
    header('Content-Type: application/rss+xml; charset=utf-8');

    // $usepermalink : If true, use permalink instead of final link.
    // User just has to add 'permalink' in URL parameters. eg. http://mysite.com/shaarli/?do=rss&permalinks
    $usepermalinks = isset($_GET['permalinks']);

    // Cache system
    $query = $_SERVER["QUERY_STRING"];
    $cache = new pageCache(pageUrl(),startsWith($query,'do=rss') && !isLoggedIn());
    $cached = $cache->cachedVersion(); if (!empty($cached)) { echo $cached; exit; }

    // If cached was not found (or not usable), then read the database and build the response:
    $LINKSDB=new linkdb(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI']);  // Read links from database (and filter private links if used it not logged in).

    // Optionnaly filter the results:
    $linksToDisplay=array();
    if (!empty($_GET['searchterm'])) $linksToDisplay = $LINKSDB->filterFulltext($_GET['searchterm']);
    elseif (!empty($_GET['searchtags']))   $linksToDisplay = $LINKSDB->filterTags(trim($_GET['searchtags']));
    else $linksToDisplay = $LINKSDB;
    $nblinksToDisplay = 50;  // Number of links to display.
    if (!empty($_GET['nb']))  // In URL, you can specificy the number of links. Example: nb=200 or nb=all for all links.
    {
        $nblinksToDisplay = $_GET['nb']=='all' ? count($linksToDisplay) : max($_GET['nb']+0,1) ;
    }

    $pageaddr=htmlspecialchars(indexUrl());
    echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">';
    echo '<channel><title>'.htmlspecialchars($GLOBALS['title']).'</title><link>'.$pageaddr.'</link>';
    echo '<description>Shared links</description><language>en-en</language><copyright>'.$pageaddr.'</copyright>'."\n\n";
    if (!empty($GLOBALS['config']['PUBSUBHUB_URL']))
    {
        echo '<!-- PubSubHubbub Discovery -->';
        echo '<link rel="hub" href="'.htmlspecialchars($GLOBALS['config']['PUBSUBHUB_URL']).'" xmlns="http://www.w3.org/2005/Atom" />';
        echo '<link rel="self" href="'.htmlspecialchars($pageaddr).'?do=rss" xmlns="http://www.w3.org/2005/Atom" />';
        echo '<!-- End Of PubSubHubbub Discovery -->';
    }
    $i=0;
    $keys=array(); foreach($linksToDisplay as $key=>$value) { $keys[]=$key; }  // No, I can't use array_keys().
    while ($i<$nblinksToDisplay && $i<count($keys))
    {
        $link = $linksToDisplay[$keys[$i]];
        $guid = $pageaddr.'?'.smallHash($link['linkdate']);
        $rfc822date = linkdate2rfc822($link['linkdate']);
        $absurl = htmlspecialchars($link['url']);
        if (startsWith($absurl,'?')) $absurl=$pageaddr.$absurl;  // make permalink URL absolute
        if ($usepermalinks===true)
            echo '<item><title>'.htmlspecialchars($link['title']).'</title><guid isPermaLink="false">'.$guid.'</guid><link>'.$guid.'</link>';
        else
            echo '<item><title>'.htmlspecialchars($link['title']).'</title><guid isPermaLink="false">'.$guid.'</guid><link>'.$absurl.'</link>';
        if (!$GLOBALS['config']['HIDE_TIMESTAMPS'] || isLoggedIn()) echo '<pubDate>'.htmlspecialchars($rfc822date)."</pubDate>\n";
        if ($link['tags']!='') // Adding tags to each RSS entry (as mentioned in RSS specification)
        {
            foreach(explode(' ',$link['tags']) as $tag) { echo '<category domain="'.htmlspecialchars($pageaddr).'">'.htmlspecialchars($tag).'</category>'."\n"; }
        }

        // Add permalink in description
        $descriptionlink = '(<a href="'.$guid.'">Permalink</a>)';
        // If user wants permalinks first, put the final link in description
        if ($usepermalinks===true) $descriptionlink = '(<a href="'.$absurl.'">Link</a>)';
        if (strlen($link['description'])>0) $descriptionlink = '<br>'.$descriptionlink;
        echo '<description><![CDATA['.nl2br(keepMultipleSpaces(text2clickable(htmlspecialchars($link['description'])))).$descriptionlink.']]></description>'."\n</item>\n";
        $i++;
    }
    echo '</channel></rss><!-- Cached version of '.htmlspecialchars(pageUrl()).' -->';

    $cache->cache(ob_get_contents());
    ob_end_flush();
    exit;
}

// ------------------------------------------------------------------------------------------
// Ouput the last N links in ATOM format.
function showATOM()
{
    header('Content-Type: application/atom+xml; charset=utf-8');

    // $usepermalink : If true, use permalink instead of final link.
    // User just has to add 'permalink' in URL parameters. eg. http://mysite.com/shaarli/?do=atom&permalinks
    $usepermalinks = isset($_GET['permalinks']);

    // Cache system
    $query = $_SERVER["QUERY_STRING"];
    $cache = new pageCache(pageUrl(),startsWith($query,'do=atom') && !isLoggedIn());
    $cached = $cache->cachedVersion(); if (!empty($cached)) { echo $cached; exit; }
    // If cached was not found (or not usable), then read the database and build the response:

    $LINKSDB=new linkdb(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI']);  // Read links from database (and filter private links if used it not logged in).


    // Optionnaly filter the results:
    $linksToDisplay=array();
    if (!empty($_GET['searchterm'])) $linksToDisplay = $LINKSDB->filterFulltext($_GET['searchterm']);
    elseif (!empty($_GET['searchtags']))   $linksToDisplay = $LINKSDB->filterTags(trim($_GET['searchtags']));
    else $linksToDisplay = $LINKSDB;
    $nblinksToDisplay = 50;  // Number of links to display.
    if (!empty($_GET['nb']))  // In URL, you can specificy the number of links. Example: nb=200 or nb=all for all links.
    {
        $nblinksToDisplay = $_GET['nb']=='all' ? count($linksToDisplay) : max($_GET['nb']+0,1) ;
    }

    $pageaddr=htmlspecialchars(indexUrl());
    $latestDate = '';
    $entries='';
    $i=0;
    $keys=array(); foreach($linksToDisplay as $key=>$value) { $keys[]=$key; }  // No, I can't use array_keys().
    while ($i<$nblinksToDisplay && $i<count($keys))
    {
        $link = $linksToDisplay[$keys[$i]];
        $guid = $pageaddr.'?'.smallHash($link['linkdate']);
        $iso8601date = linkdate2iso8601($link['linkdate']);
        $latestDate = max($latestDate,$iso8601date);
        $absurl = htmlspecialchars($link['url']);
        if (startsWith($absurl,'?')) $absurl=$pageaddr.$absurl;  // make permalink URL absolute
        $entries.='<entry><title>'.htmlspecialchars($link['title']).'</title>';
        if ($usepermalinks===true)
            $entries.='<link href="'.$guid.'" /><id>'.$guid.'</id>';
        else
            $entries.='<link href="'.$absurl.'" /><id>'.$guid.'</id>';
        if (!$GLOBALS['config']['HIDE_TIMESTAMPS'] || isLoggedIn()) $entries.='<updated>'.htmlspecialchars($iso8601date).'</updated>';

        // Add permalink in description
        $descriptionlink = htmlspecialchars('(<a href="'.$guid.'">Permalink</a>)');
        // If user wants permalinks first, put the final link in description
        if ($usepermalinks===true) $descriptionlink = htmlspecialchars('(<a href="'.$absurl.'">Link</a>)');
        if (strlen($link['description'])>0) $descriptionlink = '&lt;br&gt;'.$descriptionlink;

        $entries.='<content type="html">'.htmlspecialchars(nl2br(keepMultipleSpaces(text2clickable(htmlspecialchars($link['description']))))).$descriptionlink."</content>\n";
        if ($link['tags']!='') // Adding tags to each ATOM entry (as mentioned in ATOM specification)
        {
            foreach(explode(' ',$link['tags']) as $tag)
                { $entries.='<category scheme="'.htmlspecialchars($pageaddr,ENT_QUOTES).'" term="'.htmlspecialchars($tag,ENT_QUOTES).'" />'."\n"; }
        }
        $entries.="</entry>\n";
        $i++;
    }
    $feed='<?xml version="1.0" encoding="UTF-8"?><feed xmlns="http://www.w3.org/2005/Atom">';
    $feed.='<title>'.htmlspecialchars($GLOBALS['title']).'</title>';
    if (!$GLOBALS['config']['HIDE_TIMESTAMPS'] || isLoggedIn()) $feed.='<updated>'.htmlspecialchars($latestDate).'</updated>';
    $feed.='<link rel="self" href="'.htmlspecialchars(serverUrl().$_SERVER["REQUEST_URI"]).'" />';
    if (!empty($GLOBALS['config']['PUBSUBHUB_URL']))
    {
        $feed.='<!-- PubSubHubbub Discovery -->';
        $feed.='<link rel="hub" href="'.htmlspecialchars($GLOBALS['config']['PUBSUBHUB_URL']).'" />';
        $feed.='<!-- End Of PubSubHubbub Discovery -->';
    }
    $feed.='<author><name>'.htmlspecialchars($pageaddr).'</name><uri>'.htmlspecialchars($pageaddr).'</uri></author>';
    $feed.='<id>'.htmlspecialchars($pageaddr).'</id>'."\n\n"; // Yes, I know I should use a real IRI (RFC3987), but the site URL will do.
    $feed.=$entries;
    $feed.='</feed><!-- Cached version of '.htmlspecialchars(pageUrl()).' -->';
    echo $feed;

    $cache->cache(ob_get_contents());
    ob_end_flush();
    exit;
}

// ------------------------------------------------------------------------------------------
// Daily RSS feed: 1 RSS entry per day giving all the links on that day.
// Gives the last 7 days (which have links).
// This RSS feed cannot be filtered.
function showDailyRSS()
{
    // Cache system
    $query = $_SERVER["QUERY_STRING"];
    $cache = new pageCache(pageUrl(),startsWith($query,'do=dailyrss') && !isLoggedIn());
    $cached = $cache->cachedVersion(); if (!empty($cached)) { echo $cached; exit; }
    // If cached was not found (or not usable), then read the database and build the response:
    $LINKSDB=new linkdb(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI']);  // Read links from database (and filter private links if used it not logged in).

    /* Some Shaarlies may have very few links, so we need to look
       back in time (rsort()) until we have enough days ($nb_of_days).
    */
    $linkdates=array(); foreach($LINKSDB as $linkdate=>$value) { $linkdates[]=$linkdate; }
    rsort($linkdates);
    $nb_of_days=7; // We take 7 days.
    $today=Date('Ymd');
    $days=array();
    foreach($linkdates as $linkdate)
    {
        $day=substr($linkdate,0,8); // Extract day (without time)
        if (strcmp($day,$today)<0)
        {
            if (empty($days[$day])) $days[$day]=array();
            $days[$day][]=$linkdate;
        }
        if (count($days)>$nb_of_days) break; // Have we collected enough days ?
    }

    // Build the RSS feed.
    header('Content-Type: application/rss+xml; charset=utf-8');
    $pageaddr=htmlspecialchars(indexUrl());
    echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0">';
    echo '<channel><title>Daily - '.htmlspecialchars($GLOBALS['title']).'</title><link>'.$pageaddr.'</link>';
    echo '<description>Daily shared links</description><language>en-en</language><copyright>'.$pageaddr.'</copyright>'."\n";

    foreach($days as $day=>$linkdates) // For each day.
    {
        $daydate = utf8_encode(strftime('%A %d, %B %Y',linkdate2timestamp($day.'_000000'))); // Full text date
        $rfc822date = linkdate2rfc822($day.'_000000');
        $absurl=htmlspecialchars(indexUrl().'?do=daily&day='.$day);  // Absolute URL of the corresponding "Daily" page.
        echo '<item><title>'.htmlspecialchars($GLOBALS['title'].' - '.$daydate).'</title><guid>'.$absurl.'</guid><link>'.$absurl.'</link>';
        echo '<pubDate>'.htmlspecialchars($rfc822date)."</pubDate>";

        // Build the HTML body of this RSS entry.
        $html='';
        $href='';
        $links=array();
        // We pre-format some fields for proper output.
        foreach($linkdates as $linkdate)
        {
            $l = $LINKSDB[$linkdate];
            $l['formatedDescription']=nl2br(keepMultipleSpaces(text2clickable(htmlspecialchars($l['description']))));
            $l['thumbnail'] = thumbnail($l['url']);
            $l['localdate']=linkdate2locale($l['linkdate']);
            if (startsWith($l['url'],'?')) $l['url']=indexUrl().$l['url'];  // make permalink URL absolute
            $links[$linkdate]=$l;
        }
        // Then build the HTML for this day:
        $tpl = new RainTPL;
        $tpl->assign('links',$links);
        $html = $tpl->draw('dailyrss',$return_string=true);
        echo "\n";
        echo '<description><![CDATA['.$html.']]></description>'."\n</item>\n\n";

    }
    echo '</channel></rss><!-- Cached version of '.htmlspecialchars(pageUrl()).' -->';

    $cache->cache(ob_get_contents());
    ob_end_flush();
    exit;
}

// "Daily" page.
function showDaily()
{
    $LINKSDB=new linkdb(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI']);  // Read links from database (and filter private links if used it not logged in).


    $day=Date('Ymd',strtotime('-1 day')); // Yesterday, in format YYYYMMDD.
    if (isset($_GET['day'])) $day=$_GET['day'];

    $days = $LINKSDB->days();
    $i = array_search($day,$days);
    if ($i==false) { $i=count($days)-1; $day=$days[$i]; }
    $previousday='';
    $nextday='';
    if ($i!==false)
    {
        if ($i>1) $previousday=$days[$i-1];
        if ($i<count($days)-1) $nextday=$days[$i+1];
    }

    $linksToDisplay=$LINKSDB->filterDay($day);
    // We pre-format some fields for proper output.
    foreach($linksToDisplay as $key=>$link)
    {
        $taglist = explode(' ',$link['tags']);
        uasort($taglist, 'strcasecmp');
        $linksToDisplay[$key]['taglist']=$taglist;
        $linksToDisplay[$key]['formatedDescription']=nl2br(keepMultipleSpaces(text2clickable(htmlspecialchars($link['description']))));
        $linksToDisplay[$key]['thumbnail'] = thumbnail($link['url']);
    }

    /* We need to spread the articles on 3 columns.
       I did not want to use a javascript lib like http://masonry.desandro.com/
       so I manually spread entries with a simple method: I roughly evaluate the
       height of a div according to title and description length.
    */
    $columns=array(array(),array(),array()); // Entries to display, for each column.
    $fill=array(0,0,0);  // Rough estimate of columns fill.
    foreach($linksToDisplay as $key=>$link)
    {
        // Roughly estimate length of entry (by counting characters)
        // Title: 30 chars = 1 line. 1 line is 30 pixels height.
        // Description: 836 characters gives roughly 342 pixel height.
        // This is not perfect, but it's usually ok.
        $length=strlen($link['title'])+(342*strlen($link['description']))/836;
        if ($link['thumbnail']) $length +=100; // 1 thumbnails roughly takes 100 pixels height.
        // Then put in column which is the less filled:
        $smallest=min($fill); // find smallest value in array.
        $index=array_search($smallest,$fill); // find index of this smallest value.
        array_push($columns[$index],$link); // Put entry in this column.
        $fill[$index]+=$length;
    }
    $PAGE = new pageBuilder;
    $PAGE->assign('linksToDisplay',$linksToDisplay);
    $PAGE->assign('linkcount',count($LINKSDB));
    $PAGE->assign('col1',$columns[0]);
    $PAGE->assign('col1',$columns[0]);
    $PAGE->assign('col2',$columns[1]);
    $PAGE->assign('col3',$columns[2]);
    $PAGE->assign('day',utf8_encode(strftime('%A %d, %B %Y',linkdate2timestamp($day.'_000000'))));
    $PAGE->assign('previousday',$previousday);
    $PAGE->assign('nextday',$nextday);
    $PAGE->renderPage('daily');
    exit;
}


// -----------------------------------------------------------------------------------------------
// Process the import file form.
function importFile()
{
    if (!(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI'])) { die('Not allowed.'); }
    $LINKSDB=new linkdb(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI']);  // Read links from database (and filter private links if used it not logged in).
    $filename=$_FILES['filetoupload']['name'];
    $filesize=$_FILES['filetoupload']['size'];
    $data=file_get_contents($_FILES['filetoupload']['tmp_name']);
    $private = (empty($_POST['private']) ? 0 : 1); // Should the links be imported as private ?
    $overwrite = !empty($_POST['overwrite']) ; // Should the imported links overwrite existing ones ?
    $import_count=0;

    // Sniff file type:
    $type='unknown';
    if (startsWith($data,'<!DOCTYPE NETSCAPE-Bookmark-file-1>')) $type='netscape'; // Netscape bookmark file (aka Firefox).

    // Then import the bookmarks.
    if ($type=='netscape')
    {
        // This is a standard Netscape-style bookmark file.
        // This format is supported by all browsers (except IE, of course), also delicious, diigo and others.
        foreach(explode('<DT>',$data) as $html) // explode is very fast
        {
            $link = array('linkdate'=>'','title'=>'','url'=>'','description'=>'','tags'=>'','private'=>0);
            $d = explode('<DD>',$html);
            if (startswith($d[0],'<A '))
            {
                $link['description'] = (isset($d[1]) ? html_entity_decode(trim($d[1]),ENT_QUOTES,'UTF-8') : '');  // Get description (optional)
                preg_match('!<A .*?>(.*?)</A>!i',$d[0],$matches); $link['title'] = (isset($matches[1]) ? trim($matches[1]) : '');  // Get title
                $link['title'] = html_entity_decode($link['title'],ENT_QUOTES,'UTF-8');
                preg_match_all('! ([A-Z_]+)=\"(.*?)"!i',$html,$matches,PREG_SET_ORDER);  // Get all other attributes
                $raw_add_date=0;
                foreach($matches as $m)
                {
                    $attr=$m[1]; $value=$m[2];
                    if ($attr=='HREF') $link['url']=html_entity_decode($value,ENT_QUOTES,'UTF-8');
                    elseif ($attr=='ADD_DATE')
                    {
                        $raw_add_date=intval($value);
                        if ($raw_add_date>30000000000) $raw_add_date/=1000;	//If larger than year 2920, then was likely stored in milliseconds instead of seconds
                    }
                    elseif ($attr=='PRIVATE') $link['private']=($value=='0'?0:1);
                    elseif ($attr=='TAGS') $link['tags']=html_entity_decode(str_replace(',',' ',$value),ENT_QUOTES,'UTF-8');
                }
                if ($link['url']!='')
                {
                    if ($private==1) $link['private']=1;
                    $dblink = $LINKSDB->getLinkFromUrl($link['url']); // See if the link is already in database.
                    if ($dblink==false)
                    {  // Link not in database, let's import it...
                       if (empty($raw_add_date)) $raw_add_date=time(); // In case of shitty bookmark file with no ADD_DATE

                       // Make sure date/time is not already used by another link.
                       // (Some bookmark files have several different links with the same ADD_DATE)
                       // We increment date by 1 second until we find a date which is not used in db.
                       // (so that links that have the same date/time are more or less kept grouped by date, but do not conflict.)
                       while (!empty($LINKSDB[date('Ymd_His',$raw_add_date)])) { $raw_add_date++; }// Yes, I know it's ugly.
                       $link['linkdate']=date('Ymd_His',$raw_add_date);
                       $LINKSDB[$link['linkdate']] = $link;
                       $import_count++;
                    }
                    else // link already present in database.
                    {
                        if ($overwrite)
                        {   // If overwrite is required, we import link data, except date/time.
                            $link['linkdate']=$dblink['linkdate'];
                            $LINKSDB[$link['linkdate']] = $link;
                            $import_count++;
                        }
                    }

                }
            }
        }
        $LINKSDB->savedb();

        echo '<script language="JavaScript">alert("File '.json_encode($filename).' ('.$filesize.' bytes) was successfully processed: '.$import_count.' links imported.");document.location=\'?\';</script>';
    }
    else
    {
        echo '<script language="JavaScript">alert("File '.json_encode($filename).' ('.$filesize.' bytes) has an unknown file format. Nothing was imported.");document.location=\'?\';</script>';
    }
}


// Compute the thumbnail for a link.
//
// with a link to the original URL.
// Understands various services (youtube.com...)
// Input: $url = url for which the thumbnail must be found.
//        $href = if provided, this URL will be followed instead of $url
// Returns an associative array with thumbnail attributes (src,href,width,height,style,alt)
// Some of them may be missing.
// Return an empty array if no thumbnail available.
function computeThumbnail($url,$href=false)
{
    if (!$GLOBALS['config']['ENABLE_THUMBNAILS']) return array();
    if ($href==false) $href=$url;

    // For most hosts, the URL of the thumbnail can be easily deduced from the URL of the link.
    // (eg. http://www.youtube.com/watch?v=spVypYk4kto --->  http://img.youtube.com/vi/spVypYk4kto/default.jpg )
    //                                     ^^^^^^^^^^^                                 ^^^^^^^^^^^
    $domain = parse_url($url,PHP_URL_HOST);
    if ($domain=='youtube.com' || $domain=='www.youtube.com')
    {
        parse_str(parse_url($url,PHP_URL_QUERY), $params); // Extract video ID and get thumbnail
        if (!empty($params['v'])) return array('src'=>'http://img.youtube.com/vi/'.$params['v'].'/default.jpg',
                                               'href'=>$href,'width'=>'120','height'=>'90','alt'=>'YouTube thumbnail');
    }
    if ($domain=='youtu.be') // Youtube short links
    {
        $path = parse_url($url,PHP_URL_PATH);
        return array('src'=>'http://img.youtube.com/vi'.$path.'/default.jpg',
                     'href'=>$href,'width'=>'120','height'=>'90','alt'=>'YouTube thumbnail');
    }
    if ($domain=='pix.toile-libre.org') // pix.toile-libre.org image hosting
    {
        parse_str(parse_url($url,PHP_URL_QUERY), $params); // Extract image filename.
        if (!empty($params) && !empty($params['img'])) return array('src'=>'http://pix.toile-libre.org/upload/thumb/'.urlencode($params['img']),
                                                                    'href'=>$href,'style'=>'max-width:120px; max-height:150px','alt'=>'pix.toile-libre.org thumbnail');
    }

    if ($domain=='imgur.com')
    {
        $path = parse_url($url,PHP_URL_PATH);
        if (startsWith($path,'/a/')) return array(); // Thumbnails for albums are not available.
        if (startsWith($path,'/r/')) return array('src'=>'http://i.imgur.com/'.basename($path).'s.jpg',
                                                  'href'=>$href,'width'=>'90','height'=>'90','alt'=>'imgur.com thumbnail');
        if (startsWith($path,'/gallery/')) return array('src'=>'http://i.imgur.com'.substr($path,8).'s.jpg',
                                                        'href'=>$href,'width'=>'90','height'=>'90','alt'=>'imgur.com thumbnail');

        if (substr_count($path,'/')==1) return array('src'=>'http://i.imgur.com/'.substr($path,1).'s.jpg',
                                                     'href'=>$href,'width'=>'90','height'=>'90','alt'=>'imgur.com thumbnail');
    }
    if ($domain=='i.imgur.com')
    {
        $pi = pathinfo(parse_url($url,PHP_URL_PATH));
        if (!empty($pi['filename'])) return array('src'=>'http://i.imgur.com/'.$pi['filename'].'s.jpg',
                                                  'href'=>$href,'width'=>'90','height'=>'90','alt'=>'imgur.com thumbnail');
    }
    if ($domain=='dailymotion.com' || $domain=='www.dailymotion.com')
    {
        if (strpos($url,'dailymotion.com/video/')!==false)
        {
            $thumburl=str_replace('dailymotion.com/video/','dailymotion.com/thumbnail/video/',$url);
            return array('src'=>$thumburl,
                         'href'=>$href,'width'=>'120','style'=>'height:auto;','alt'=>'DailyMotion thumbnail');
        }
    }
    if (endsWith($domain,'.imageshack.us'))
    {
        $ext=strtolower(pathinfo($url,PATHINFO_EXTENSION));
        if ($ext=='jpg' || $ext=='jpeg' || $ext=='png' || $ext=='gif')
        {
            $thumburl = substr($url,0,strlen($url)-strlen($ext)).'th.'.$ext;
            return array('src'=>$thumburl,
                         'href'=>$href,'width'=>'120','style'=>'height:auto;','alt'=>'imageshack.us thumbnail');
        }
    }

    // Some other hosts are SLOW AS HELL and usually require an extra HTTP request to get the thumbnail URL.
    // So we deport the thumbnail generation in order not to slow down page generation
    // (and we also cache the thumbnail)

    if (!$GLOBALS['config']['ENABLE_LOCALCACHE']) return array(); // If local cache is disabled, no thumbnails for services which require the use a local cache.

    if ($domain=='flickr.com' || endsWith($domain,'.flickr.com')
        || $domain=='vimeo.com'
        || $domain=='ted.com' || endsWith($domain,'.ted.com')
        || $domain=='xkcd.com' || endsWith($domain,'.xkcd.com')
    )
    {
        if ($domain=='vimeo.com')
        {   // Make sure this vimeo url points to a video (/xxx... where xxx is numeric)
            $path = parse_url($url,PHP_URL_PATH);
            if (!preg_match('!/\d+.+?!',$path)) return array(); // This is not a single video URL.
        }
        if ($domain=='xkcd.com' || endsWith($domain,'.xkcd.com'))
        {   // Make sure this url points to a single comic (/xxx... where xxx is numeric)
            $path = parse_url($url,PHP_URL_PATH);
            if (!preg_match('!/\d+.+?!',$path)) return array();
        }
        if ($domain=='ted.com' || endsWith($domain,'.ted.com'))
        {   // Make sure this TED url points to a video (/talks/...)
            $path = parse_url($url,PHP_URL_PATH);
            if ("/talks/" !== substr($path,0,7)) return array(); // This is not a single video URL.
        }
        $sign = hash_hmac('sha256', $url, $GLOBALS['salt']); // We use the salt to sign data (it's random, secret, and specific to each installation)
        return array('src'=>indexUrl().'?do=genthumbnail&hmac='.htmlspecialchars($sign).'&url='.urlencode($url),
                     'href'=>$href,'width'=>'120','style'=>'height:auto;','alt'=>'thumbnail');
    }

    // For all other, we try to make a thumbnail of links ending with .jpg/jpeg/png/gif
    // Technically speaking, we should download ALL links and check their Content-Type to see if they are images.
    // But using the extension will do.
    $ext=strtolower(pathinfo($url,PATHINFO_EXTENSION));
    if ($ext=='jpg' || $ext=='jpeg' || $ext=='png' || $ext=='gif')
    {
        $sign = hash_hmac('sha256', $url, $GLOBALS['salt']); // We use the salt to sign data (it's random, secret, and specific to each installation)
        return array('src'=>indexUrl().'?do=genthumbnail&hmac='.htmlspecialchars($sign).'&url='.urlencode($url),
                     'href'=>$href,'width'=>'120','style'=>'height:auto;','alt'=>'thumbnail');
    }


    return array(); // No thumbnail.

}


// Returns the HTML code to display a thumbnail for a link
// with a link to the original URL.
// Understands various services (youtube.com...)
// Input: $url = url for which the thumbnail must be found.
//        $href = if provided, this URL will be followed instead of $url
// Returns '' if no thumbnail available.
function thumbnail($url,$href=false)
{
    $t = computeThumbnail($url,$href);
    if (count($t)==0) return ''; // Empty array = no thumbnail for this URL.

    $html='<a href="'.htmlspecialchars($t['href']).'"><img src="'.htmlspecialchars($t['src']).'"';
    if (!empty($t['width']))  $html.=' width="'.htmlspecialchars($t['width']).'"';
    if (!empty($t['height'])) $html.=' height="'.htmlspecialchars($t['height']).'"';
    if (!empty($t['style']))  $html.=' style="'.htmlspecialchars($t['style']).'"';
    if (!empty($t['alt']))    $html.=' alt="'.htmlspecialchars($t['alt']).'"';
    $html.='></a>';
    return $html;
}


// Returns the HTML code to display a thumbnail for a link
// for the picture wall (using lazy image loading)
// Understands various services (youtube.com...)
// Input: $url = url for which the thumbnail must be found.
//        $href = if provided, this URL will be followed instead of $url
// Returns '' if no thumbnail available.
function lazyThumbnail($url,$href=false)
{
    $t = computeThumbnail($url,$href);
    if (count($t)==0) return ''; // Empty array = no thumbnail for this URL.

    $html='<a href="'.htmlspecialchars($t['href']).'">';

    // Lazy image (only loaded by javascript when in the viewport).
    if (!empty($GLOBALS['disablejquery'])) // (except if jQuery is disabled)
        $html.='<img class="lazyimage" src="'.htmlspecialchars($t['src']).'"';
    else
        $html.='<img class="lazyimage" src="#" data-original="'.htmlspecialchars($t['src']).'"';

    if (!empty($t['width']))  $html.=' width="'.htmlspecialchars($t['width']).'"';
    if (!empty($t['height'])) $html.=' height="'.htmlspecialchars($t['height']).'"';
    if (!empty($t['style']))  $html.=' style="'.htmlspecialchars($t['style']).'"';
    if (!empty($t['alt']))    $html.=' alt="'.htmlspecialchars($t['alt']).'"';
    $html.='>';

    // No-javascript fallback.
    $html.='<noscript><img src="'.htmlspecialchars($t['src']).'"';
    if (!empty($t['width']))  $html.=' width="'.htmlspecialchars($t['width']).'"';
    if (!empty($t['height'])) $html.=' height="'.htmlspecialchars($t['height']).'"';
    if (!empty($t['style']))  $html.=' style="'.htmlspecialchars($t['style']).'"';
    if (!empty($t['alt']))    $html.=' alt="'.htmlspecialchars($t['alt']).'"';
    $html.='></noscript></a>';

    return $html;
}


// -----------------------------------------------------------------------------------------------
// Installation
// This function should NEVER be called if the file data/config.php exists.
function install()
{
    // On free.fr host, make sure the /sessions directory exists, otherwise login will not work.
    if (endsWith($_SERVER['HTTP_HOST'],'.free.fr') && !is_dir($_SERVER['DOCUMENT_ROOT'].'/sessions')) mkdir($_SERVER['DOCUMENT_ROOT'].'/sessions',0705);


    // This part makes sure sessions works correctly.
    // (Because on some hosts, session.save_path may not be set correctly,
    // or we may not have write access to it.)
    if (isset($_GET['test_session']) && ( !isset($_SESSION) || !isset($_SESSION['session_tested']) || $_SESSION['session_tested']!='Working'))
    {   // Step 2: Check if data in session is correct.
        echo '<pre>Sessions do not seem to work correctly on your server.<br>';
        echo 'Make sure the variable session.save_path is set correctly in your php config, and that you have write access to it.<br>';
        echo 'It currently points to '.session_save_path().'<br><br><a href="?">Click to try again.</a></pre>';
        die;
    }
    if (!isset($_SESSION['session_tested']))
    {   // Step 1 : Try to store data in session and reload page.
        $_SESSION['session_tested'] = 'Working';  // Try to set a variable in session.
        header('Location: '.indexUrl().'?test_session');  // Redirect to check stored data.
    }
    if (isset($_GET['test_session']))
    {   // Step 3: Sessions are ok. Remove test parameter from URL.
        header('Location: '.indexUrl());
    }


    if (!empty($_POST['setlogin']) && !empty($_POST['setpassword']))
    {
        $tz = 'UTC';
        if (!empty($_POST['continent']) && !empty($_POST['city']))
            if (isTZvalid($_POST['continent'],$_POST['city']))
                $tz = $_POST['continent'].'/'.$_POST['city'];
        $GLOBALS['timezone'] = $tz;
        // Everything is ok, let's create config file.
        $GLOBALS['login'] = $_POST['setlogin'];
        $GLOBALS['salt'] = sha1(uniqid('',true).'_'.mt_rand()); // Salt renders rainbow-tables attacks useless.
        $GLOBALS['hash'] = sha1($_POST['setpassword'].$GLOBALS['login'].$GLOBALS['salt']);
        $GLOBALS['title'] = (empty($_POST['title']) ? 'Shared links on '.htmlspecialchars(indexUrl()) : $_POST['title'] );
        writeConfig();
        echo '<script language="JavaScript">alert("Shaarli is now configured. Please enter your login/password and start shaaring your links !");document.location=\'?do=login\';</script>';
        exit;
    }

    // Display config form:
    list($timezone_form,$timezone_js) = templateTZform();
    $timezone_html=''; if ($timezone_form!='') $timezone_html='
        <div class="form-group"><label for="continent" class="col-sm-4 control-label">Timezone
        '.$timezone_form;

    $PAGE = new pageBuilder;
    $PAGE->assign('timezone_html',$timezone_html);
    $PAGE->assign('timezone_js',$timezone_js);
    $PAGE->renderPage('install');
    exit;
}

// Generates the timezone selection form and javascript.
// Input: (optional) current timezone (can be 'UTC/UTC'). It will be pre-selected.
// Output: array(html,js)
// Example: list($htmlform,$js) = templateTZform('Europe/Paris');  // Europe/Paris pre-selected.
// Returns array('','') if server does not support timezones list. (eg. php 5.1 on free.fr)
function templateTZform($ptz=false)
{
    if (function_exists('timezone_identifiers_list')) // because of old php version (5.1) which can be found on free.fr
    {
        // Try to split the provided timezone.
        if ($ptz==false) { $l=timezone_identifiers_list(); $ptz=$l[0]; }
        $spos=strpos($ptz,'/'); $pcontinent=substr($ptz,0,$spos); $pcity=substr($ptz,$spos+1);

        // Display config form:
        $timezone_form = '';
        $timezone_js = '';
        // The list is in the forme "Europe/Paris", "America/Argentina/Buenos_Aires"...
        // We split the list in continents/cities.
        $continents = array();
        $cities = array();
        foreach(timezone_identifiers_list() as $tz)
        {
            if ($tz=='UTC') $tz='UTC/UTC';
            $spos = strpos($tz,'/');
            if ($spos!==false)
            {
                $continent=substr($tz,0,$spos); $city=substr($tz,$spos+1);
                $continents[$continent]=1;
                if (!isset($cities[$continent])) $cities[$continent]='';
                $cities[$continent].='<option value="'.$city.'"'.($pcity==$city?'selected':'').'>'.$city.'</option>';
            }
        }
        $continents_html = '';
        $continents = array_keys($continents);
        foreach($continents as $continent)
            $continents_html.='<option  value="'.$continent.'"'.($pcontinent==$continent?'selected':'').'>'.$continent.'</option>';
        $cities_html = $cities[$pcontinent];
        $timezone_form = "Continent</label><div class=\"col-sm-8\"> <select class=\"form-control\" name=\"continent\" id=\"continent\" onChange=\"onChangecontinent();\">${continents_html}</select></div></div>";
        $timezone_form .= "<div class=\"form-group\"><label for=\"city\" class=\"col-sm-4 control-label\">City</label><div class=\"col-sm-8\"><select  class=\"form-control\" name=\"city\" id=\"city\">${cities[$pcontinent]}</select></div></div>";
        $timezone_js = "<script language=\"JavaScript\">";
        $timezone_js .= "function onChangecontinent(){document.getElementById(\"city\").innerHTML = citiescontinent[document.getElementById(\"continent\").value];}";
        $timezone_js .= "var citiescontinent = ".json_encode($cities).";" ;
        $timezone_js .= "</script>" ;
        return array($timezone_form,$timezone_js);
    }
    return array('','');
}

// Tells if a timezone is valid or not.
// If not valid, returns false.
// If system does not support timezone list, returns false.
function isTZvalid($continent,$city)
{
    $tz = $continent.'/'.$city;
    if (function_exists('timezone_identifiers_list')) // because of old php version (5.1) which can be found on free.fr
    {
        if (in_array($tz, timezone_identifiers_list())) // it's a valid timezone ?
                    return true;
    }
    return false;
}
if (!function_exists('json_encode')) {
    function json_encode($data) {
        switch ($type = gettype($data)) {
            case 'NULL':
                return 'null';
            case 'boolean':
                return ($data ? 'true' : 'false');
            case 'integer':
            case 'double':
            case 'float':
                return $data;
            case 'string':
                return '"' . addslashes($data) . '"';
            case 'object':
                $data = get_object_vars($data);
            case 'array':
                $output_index_count = 0;
                $output_indexed = array();
                $output_associative = array();
                foreach ($data as $key => $value) {
                    $output_indexed[] = json_encode($value);
                    $output_associative[] = json_encode($key) . ':' . json_encode($value);
                    if ($output_index_count !== NULL && $output_index_count++ !== $key) {
                        $output_index_count = NULL;
                    }
                }
                if ($output_index_count !== NULL) {
                    return '[' . implode(',', $output_indexed) . ']';
                } else {
                    return '{' . implode(',', $output_associative) . '}';
                }
            default:
                return ''; // Not supported
        }
    }
}

// Webservices (for use with jQuery/jQueryUI)
// eg.  index.php?ws=tags&term=minecr
function processWS()
{
    if (empty($_GET['ws']) || empty($_GET['term'])) return;
    $term = $_GET['term'];
    $LINKSDB=new linkdb(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI']);  // Read links from database (and filter private links if used it not logged in).
    header('Content-Type: application/json; charset=utf-8');

    // Search in tags (case insentitive, cumulative search)
    if ($_GET['ws']=='tags')
    {
        $tags=explode(' ',str_replace(',',' ',$term)); $last = array_pop($tags); // Get the last term ("a b c d" ==> "a b c", "d")
        $addtags=''; if ($tags) $addtags=implode(' ',$tags).' '; // We will pre-pend previous tags
        $suggested=array();
        /* To speed up things, we store list of tags in session */
        if (empty($_SESSION['tags'])) $_SESSION['tags'] = $LINKSDB->allTags();
        foreach($_SESSION['tags'] as $key=>$value)
        {
            if (startsWith($key,$last,$case=false) && !in_array($key,$tags)) $suggested[$addtags.$key.' ']=0;
        }
        echo json_encode(array_keys($suggested));
        exit;
    }

    // Search a single tag (case sentitive, single tag search)
    if ($_GET['ws']=='singletag')
    {
        /* To speed up things, we store list of tags in session */
        if (empty($_SESSION['tags'])) $_SESSION['tags'] = $LINKSDB->allTags();
        foreach($_SESSION['tags'] as $key=>$value)
        {
            if (startsWith($key,$term,$case=true)) $suggested[$key]=0;
        }
        echo json_encode(array_keys($suggested));
        exit;
    }
}

// Re-write configuration file according to globals.
// Requires some $GLOBALS to be set (login,hash,salt,title).
// If the config file cannot be saved, an error message is dislayed and the user is redirected to "Tools" menu.
// (otherwise, the function simply returns.)
function writeConfig()
{
    if (is_file($GLOBALS['config']['CONFIG_FILE']) && !isLoggedIn()) die('You are not authorized to alter config.'); // Only logged in user can alter config.
    $config='<?php $GLOBALS[\'login\']='.var_export($GLOBALS['login'],true).'; $GLOBALS[\'hash\']='.var_export($GLOBALS['hash'],true).'; $GLOBALS[\'salt\']='.var_export($GLOBALS['salt'],true).'; ';
    $config .='$GLOBALS[\'timezone\']='.var_export($GLOBALS['timezone'],true).'; date_default_timezone_set('.var_export($GLOBALS['timezone'],true).'); $GLOBALS[\'title\']='.var_export($GLOBALS['title'],true).';';
    $config .= '$GLOBALS[\'redirector\']='.var_export($GLOBALS['redirector'],true).'; ';
    $config .= '$GLOBALS[\'disablesessionprotection\']='.var_export($GLOBALS['disablesessionprotection'],true).'; ';
    $config .= '$GLOBALS[\'disablejquery\']='.var_export($GLOBALS['disablejquery'],true).'; ';
    $config .= '$GLOBALS[\'privateLinkByDefault\']='.var_export($GLOBALS['privateLinkByDefault'],true).'; ';
    $config .= ' ?>';
    if (!file_put_contents($GLOBALS['config']['CONFIG_FILE'],$config) || strcmp(file_get_contents($GLOBALS['config']['CONFIG_FILE']),$config)!=0)
    {
        echo '<script language="JavaScript">alert("Shaarli could not create the config file. Please make sure Shaarli has the right to write in the folder is it installed in.");document.location=\'?\';</script>';
        exit;
    }
}

/* Because some f*cking services like Flickr require an extra HTTP request to get the thumbnail URL,
   I have deported the thumbnail URL code generation here, otherwise this would slow down page generation.
   The following function takes the URL a link (eg. a flickr page) and return the proper thumbnail.
   This function is called by passing the url:
   http://mywebsite.com/shaarli/?do=genthumbnail&hmac=[HMAC]&url=[URL]
   [URL] is the URL of the link (eg. a flickr page)
   [HMAC] is the signature for the [URL] (so that these URL cannot be forged).
   The function below will fetch the image from the webservice and store it in the cache.
*/
function genThumbnail()
{
    // Make sure the parameters in the URL were generated by us.
    $sign = hash_hmac('sha256', $_GET['url'], $GLOBALS['salt']);
    if ($sign!=$_GET['hmac']) die('Naughty boy !');

    // Let's see if we don't already have the image for this URL in the cache.
    $thumbname=hash('sha1',$_GET['url']).'.jpg';
    if (is_file($GLOBALS['config']['CACHEDIR'].'/'.$thumbname))
    {   // We have the thumbnail, just serve it:
        header('Content-Type: image/jpeg');
        echo file_get_contents($GLOBALS['config']['CACHEDIR'].'/'.$thumbname);
        return;
    }
    // We may also serve a blank image (if service did not respond)
    $blankname=hash('sha1',$_GET['url']).'.gif';
    if (is_file($GLOBALS['config']['CACHEDIR'].'/'.$blankname))
    {
        header('Content-Type: image/gif');
        echo file_get_contents($GLOBALS['config']['CACHEDIR'].'/'.$blankname);
        return;
    }

    // Otherwise, generate the thumbnail.
    $url = $_GET['url'];
    $domain = parse_url($url,PHP_URL_HOST);

    if ($domain=='flickr.com' || endsWith($domain,'.flickr.com'))
    {
        // Crude replacement to handle new Flickr domain policy (They prefer www. now)
        $url = str_replace('http://flickr.com/','http://www.flickr.com/',$url);

        // Is this a link to an image, or to a flickr page ?
        $imageurl='';
        if (endswith(parse_url($url,PHP_URL_PATH),'.jpg'))
        {  // This is a direct link to an image. eg. http://farm1.staticflickr.com/5/5921913_ac83ed27bd_o.jpg
            preg_match('!(http://farm\d+\.staticflickr\.com/\d+/\d+_\w+_)\w.jpg!',$url,$matches);
            if (!empty($matches[1])) $imageurl=$matches[1].'m.jpg';
        }
        else // this is a flickr page (html)
        {
            list($httpstatus,$headers,$data) = getHTTP($url,20); // Get the flickr html page.
            if (strpos($httpstatus,'200 OK')!==false)
            {
                // Flickr now nicely provides the URL of the thumbnail in each flickr page.
                preg_match('!<link rel=\"image_src\" href=\"(.+?)\"!',$data,$matches);
                if (!empty($matches[1])) $imageurl=$matches[1];

                // In albums (and some other pages), the link rel="image_src" is not provided,
                // but flickr provides:
                // <meta property="og:image" content="http://farm4.staticflickr.com/3398/3239339068_25d13535ff_z.jpg" />
                if ($imageurl=='')
                {
                    preg_match('!<meta property=\"og:image\" content=\"(.+?)\"!',$data,$matches);
                    if (!empty($matches[1])) $imageurl=$matches[1];
                }
            }
        }

        if ($imageurl!='')
        {   // Let's download the image.
            list($httpstatus,$headers,$data) = getHTTP($imageurl,10); // Image is 240x120, so 10 seconds to download should be enough.
            if (strpos($httpstatus,'200 OK')!==false)
            {
                file_put_contents($GLOBALS['config']['CACHEDIR'].'/'.$thumbname,$data); // Save image to cache.
                header('Content-Type: image/jpeg');
                echo $data;
                return;
            }
        }
    }

    elseif ($domain=='vimeo.com' )
    {
        // This is more complex: we have to perform a HTTP request, then parse the result.
        // Maybe we should deport this to javascript ? Example: http://stackoverflow.com/questions/1361149/get-img-thumbnails-from-vimeo/4285098#4285098
        $vid = substr(parse_url($url,PHP_URL_PATH),1);
        list($httpstatus,$headers,$data) = getHTTP('http://vimeo.com/api/v2/video/'.htmlspecialchars($vid).'.php',5);
        if (strpos($httpstatus,'200 OK')!==false)
        {
            $t = unserialize($data);
            $imageurl = $t[0]['thumbnail_medium'];
            // Then we download the image and serve it to our client.
            list($httpstatus,$headers,$data) = getHTTP($imageurl,10);
            if (strpos($httpstatus,'200 OK')!==false)
            {
                file_put_contents($GLOBALS['config']['CACHEDIR'].'/'.$thumbname,$data); // Save image to cache.
                header('Content-Type: image/jpeg');
                echo $data;
                return;
            }
        }
    }

    elseif ($domain=='ted.com' || endsWith($domain,'.ted.com'))
    {
        // The thumbnail for TED talks is located in the <link rel="image_src" [...]> tag on that page
        // http://www.ted.com/talks/mikko_hypponen_fighting_viruses_defending_the_net.html
        // <link rel="image_src" href="http://images.ted.com/images/ted/28bced335898ba54d4441809c5b1112ffaf36781_389x292.jpg" />
        list($httpstatus,$headers,$data) = getHTTP($url,5);
        if (strpos($httpstatus,'200 OK')!==false)
        {
            // Extract the link to the thumbnail
            preg_match('!link rel="image_src" href="(http://images.ted.com/images/ted/.+_\d+x\d+\.jpg)"!',$data,$matches);
            if (!empty($matches[1]))
            {   // Let's download the image.
                $imageurl=$matches[1];
                list($httpstatus,$headers,$data) = getHTTP($imageurl,20); // No control on image size, so wait long enough.
                if (strpos($httpstatus,'200 OK')!==false)
                {
                    $filepath=$GLOBALS['config']['CACHEDIR'].'/'.$thumbname;
                    file_put_contents($filepath,$data); // Save image to cache.
                    if (resizeImage($filepath))
                    {
                        header('Content-Type: image/jpeg');
                        echo file_get_contents($filepath);
                        return;
                    }
                }
            }
        }
    }

    elseif ($domain=='xkcd.com' || endsWith($domain,'.xkcd.com'))
    {
        // There is no thumbnail available for xkcd comics, so download the whole image and resize it.
        // http://xkcd.com/327/
        // <img src="http://imgs.xkcd.com/comics/exploits_of_a_mom.png" title="<BLABLA>" alt="<BLABLA>" />
        list($httpstatus,$headers,$data) = getHTTP($url,5);
        if (strpos($httpstatus,'200 OK')!==false)
        {
            // Extract the link to the thumbnail
            preg_match('!<img src="(http://imgs.xkcd.com/comics/.*)" title="[^s]!',$data,$matches);
            if (!empty($matches[1]))
            {   // Let's download the image.
                $imageurl=$matches[1];
                list($httpstatus,$headers,$data) = getHTTP($imageurl,20); // No control on image size, so wait long enough.
                if (strpos($httpstatus,'200 OK')!==false)
                {
                    $filepath=$GLOBALS['config']['CACHEDIR'].'/'.$thumbname;
                    file_put_contents($filepath,$data); // Save image to cache.
                    if (resizeImage($filepath))
                    {
                        header('Content-Type: image/jpeg');
                        echo file_get_contents($filepath);
                        return;
                    }
                }
            }
        }
    }

    else
    {
        // For all other domains, we try to download the image and make a thumbnail.
        list($httpstatus,$headers,$data) = getHTTP($url,30);  // We allow 30 seconds max to download (and downloads are limited to 4 Mb)
        if (strpos($httpstatus,'200 OK')!==false)
        {
            $filepath=$GLOBALS['config']['CACHEDIR'].'/'.$thumbname;
            file_put_contents($filepath,$data); // Save image to cache.
            if (resizeImage($filepath))
            {
                header('Content-Type: image/jpeg');
                echo file_get_contents($filepath);
                return;
            }
        }
    }


    // Otherwise, return an empty image (8x8 transparent gif)
    $blankgif = base64_decode('R0lGODlhCAAIAIAAAP///////yH5BAEKAAEALAAAAAAIAAgAAAIHjI+py+1dAAA7');
    file_put_contents($GLOBALS['config']['CACHEDIR'].'/'.$blankname,$blankgif); // Also put something in cache so that this URL is not requested twice.
    header('Content-Type: image/gif');
    echo $blankgif;
}

// Make a thumbnail of the image (to width: 120 pixels)
// Returns true if success, false otherwise.
function resizeImage($filepath)
{
    if (!function_exists('imagecreatefromjpeg')) return false; // GD not present: no thumbnail possible.

    // Trick: some stupid people rename GIF as JPEG... or else.
    // So we really try to open each image type whatever the extension is.
    $header=file_get_contents($filepath,false,NULL,0,256); // Read first 256 bytes and try to sniff file type.
    $im=false;
    $i=strpos($header,'GIF8'); if (($i!==false) && ($i==0)) $im = imagecreatefromgif($filepath); // Well this is crude, but it should be enough.
    $i=strpos($header,'PNG'); if (($i!==false) && ($i==1)) $im = imagecreatefrompng($filepath);
    $i=strpos($header,'JFIF'); if ($i!==false) $im = imagecreatefromjpeg($filepath);
    if (!$im) return false;  // Unable to open image (corrupted or not an image)
    $w = imagesx($im);
    $h = imagesy($im);
    $ystart = 0; $yheight=$h;
    if ($h>$w) { $ystart= ($h/2)-($w/2); $yheight=$w/2; }
    $nw = 120;   // Desired width
    $nh = min(floor(($h*$nw)/$w),120); // Compute new width/height, but maximum 120 pixels height.
    // Resize image:
    $im2 = imagecreatetruecolor($nw,$nh);
    imagecopyresampled($im2, $im, 0, 0, 0, $ystart, $nw, $nh, $w, $yheight);
    imageinterlace($im2,true); // For progressive JPEG.
    $tempname=$filepath.'_TEMP.jpg';
    imagejpeg($im2, $tempname, 90);
    imagedestroy($im);
    imagedestroy($im2);
    unlink($filepath);
    rename($tempname,$filepath);  // Overwrite original picture with thumbnail.
    return true;
}

// Invalidate caches when the database is changed or the user logs out.
// (eg. tags cache).
function invalidateCaches()
{
    unset($_SESSION['tags']);  // Purge cache attached to session.
    pageCache::purgeCache();   // Purge page cache shared by sessions.
}










///////////////////////////////////////////////////////////////////////////////////////
// Bronco added this
///////////////////////////////////////////////////////////////////////////////////////


function highLightSearchTerms($string,$searchterm){
    if (is_array($searchterm)){$searchterm=implode('', $searchterm); }
    $repl='<em class="highlight_term">'.$searchterm.'</em>';
    $replreg='\\1<em class="highlight_term">\\2</em>';
    $rule='(?<!href="|src=")(.+)('.$searchterm.')';

    return mb_eregi_replace($rule,$replreg,parse($string));
}

#
#
# Parsedown
# http://parsedown.org
#
# (c) Emanuil Rusev
# http://erusev.com
#

#This version is not the original: it's a class to function conversion by Bronco (http:www.warriordudimanche.net)
#Thanks a lot to Emanuil Rusev for this script ! It saved me lots of time ! ;-)

    global $escape_sequence_map;
    function parse($text)
    {
        # removes UTF-8 BOM and marker characters
        $text = preg_replace('{^\xEF\xBB\xBF|\x1A}', '', $text);

        # removes \r characters
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        # replaces tabs with spaces
        $text = str_replace("\t", '    ', $text);

        # encodes escape sequences
        if (strpos($text, '\\') !== FALSE)
        {
            $escape_sequences = array('\\\\', '\`', '\*', '\_', '\{', '\}', '\[', '\]', '\(', '\)', '\>', '\#', '\+', '\-', '\.', '\!');
            foreach ($escape_sequences as $index => $escape_sequence)
            {
                if (strpos($text, $escape_sequence) !== FALSE)
                {
                    $code = "\x1A".'\\'.$index.';';
                    $text = str_replace($escape_sequence, $code, $text);
                    $escape_sequence_map[$code] = $escape_sequence;
                }
            }
        }

        # ~

        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        $text = trim($text, "\n");
        $lines = explode("\n", $text);
        $text = parse_block_elements($lines);

        # decodes escape sequences
        if (!empty($escape_sequence_map)){
            foreach ($escape_sequence_map as $code => $escape_sequence)
            {
                $text = str_replace($code, $escape_sequence[1], $text);
            }
        }

        $text = rtrim($text, "\n");
        return $text;
    }

    function parse_block_elements(array $lines, $context = '')
    {
        $elements = array();
        $element = array('type' => '',);

        foreach ($lines as $line)
        {
            # markup (open)
            if ($element['type'] === 'markup' and ! isset($element['closed']))
            {
                if (preg_match('{<'.$element['subtype'].'>$}', $line)) # opening tag
                {
                    $element['depth']++;
                }

                if (preg_match('{</'.$element['subtype'].'>$}', $line)) # closing tag
                {
                    $element['depth'] > 0
                        ? $element['depth']--
                        : $element['closed'] = true;
                }
                $element['text'] .= "\n".$line;
                continue;
            }

            # *

            if ($line === '')
            {
                $element['interrupted'] = true;
                continue;
            }

            # blockquote (existing)
            if ($element['type'] === 'blockquote' and ! isset($element['interrupted']))
            {
                $line = preg_replace('/^[ ]*>[ ]?/', '', $line);
                $element['lines'] []= $line;
                continue;
            }

            # list (existing)
            if ($element['type'] === 'li')
            {
                if (preg_match('/^([ ]{0,3})(\d+[.]|[*+-])[ ](.*)/', $line, $matches))
                {
                    if ($element['indentation'] !== $matches[1])
                    {
                        $element['lines'] []= $line;
                    }
                    else
                    {
                        unset($element['last']);
                        $elements []= $element;
                        $element = array(
                            'type' => 'li',
                            'indentation' => $matches[1],
                            'last' => true,
                            'lines' => array(
                                preg_replace('/^[ ]{0,4}/', '', $matches[3]),
                            ),
                        );
                    }
                    continue;
                }

                if (isset($element['interrupted']))
                {
                    if ($line[0] === ' ')
                    {
                        $element['lines'] []= '';
                        $line = preg_replace('/^[ ]{0,4}/', '', $line);
                        $element['lines'] []= $line;
                        continue;
                    }
                }
                else
                {
                    $line = preg_replace('/^[ ]{0,4}/', '', $line);
                    $element['lines'] []= $line;
                    continue;
                }
            }

            # paragraph
            if ($line[0] >= 'a' or $line[0] >= 'A' and $line[0] <= 'Z')
            {
                goto paragraph;
            }

            # code block
            if ($line[0] === ' ' and preg_match('/^[ ]{4}(.*)/', $line, $matches))
            {
                if (trim($line) === ''){continue;}
                if ($element['type'] === 'code')
                {
                    if (isset($element['interrupted']))
                    {
                        $element['text'] .= "\n";
                        unset ($element['interrupted']);
                    }
                    $element['text'] .= "\n".$matches[1];
                }
                else
                {
                    $elements []= $element;
                    $element = array(
                        'type' => 'code',
                        'text' => $matches[1],
                    );
                }
                continue;
            }

            # setext heading (---)

            if ($line[0] === '-' and $element['type'] === 'p' and ! isset($element['interrupted']) and preg_match('/^[-]+[ ]*$/', $line))
            {
                $element['type'] = 'h.';
                $element['level'] = 2;
                continue;
            }

            # atx heading (#)

            if ($line[0] === '#' and preg_match('/^(#{1,6})[ ]*(.+?)[ ]*#*$/', $line, $matches))
            {
                $elements []= $element;
                $level = strlen($matches[1]);
                $element = array(
                    'type' => 'h.',
                    'text' => $matches[2],
                    'level' => $level,
                );
                continue;
            }

            # setext heading (===)
            if ($line[0] === '=' and $element['type'] === 'p' and ! isset($element['interrupted']) and preg_match('/^[=]+[ ]*$/', $line))
            {
                $element['type'] = 'h.';
                $element['level'] = 1;
                continue;
            }

            $deindented_line = $line[0] !== ' ' ? $line : ltrim($line);
            if ($deindented_line === ''){continue;}

            # reference
            if ($deindented_line[0] === '[' and preg_match('/^\[(.+?)\]:[ ]*([^ ]+)/', $deindented_line, $matches))
            {
                $label = strtolower($matches[1]);
                $url = trim($matches[2], '<>');
                $reference_map[$label] = $url;
                continue;
            }

            # blockquote
            if ($deindented_line[0] === '>' and preg_match('/^>[ ]?(.*)/', $deindented_line, $matches))
            {
                if ($element['type'] === 'blockquote')
                {
                    if (isset($element['interrupted']))
                    {
                        $element['lines'] []= '';
                        unset($element['interrupted']);
                    }
                    $element['lines'] []= $matches[1];
                }
                else
                {
                    $elements []= $element;
                    $element = array(
                        'type' => 'blockquote',
                        'lines' => array(
                            $matches[1],
                        ),
                    );
                }
                continue;
            }

            # markup

            if ($deindented_line[0] === '<')
            {
                # self-closing tag
                if (preg_match('{^<.+?/>$}', $deindented_line))
                {
                    $elements []= $element;
                    $element = array(
                        'type' => '',
                        'text' => $deindented_line,
                    );
                    continue;
                }

                # opening tag
                if (preg_match('{^<(\w+)(?:[ ].*?)?>}', $deindented_line, $matches))
                {
                    $elements []= $element;
                    $element = array(
                        'type' => 'markup',
                        'subtype' => strtolower($matches[1]),
                        'text' => $deindented_line,
                        'depth' => 0,
                    );
                    preg_match('{</'.$matches[1].'>\s*$}', $deindented_line) and $element['closed'] = true;
                    continue;
                }
            }

            # horizontal rule
            if (preg_match('/^([-*_])([ ]{0,2}\1){2,}[ ]*$/', $deindented_line))
            {
                $elements []= $element;
                $element = array('type' => 'hr',);
                continue;
            }

            # list item
            if (preg_match('/^([ ]*)(\d+[.]|[*+-])[ ](.*)/', $line, $matches))
            {
                $elements []= $element;
                $element = array(
                    'type' => 'li',
                    'ordered' => isset($matches[2][1]),
                    'indentation' => $matches[1],
                    'last' => true,
                    'lines' => array(
                        preg_replace('/^[ ]{0,4}/', '', $matches[3]),
                    ),
                );
                continue;
            }

            paragraph:
            if ($element['type'] === 'p')
            {
                if (isset($element['interrupted']))
                {
                    $elements []= $element;
                    $element['text'] = $line;
                    unset($element['interrupted']);
                }
                else {$element['text'] .= "\n".$line;}
            }
            else
            {
                $elements []= $element;
                $element = array(
                    'type' => 'p',
                    'text' => $line,
                );
            }
        }

        $elements []= $element;
        array_shift($elements);


        $markup = '';
        foreach ($elements as $index => $element)
        {
            switch ($element['type'])
            {
                case 'p':
                    $text = parse_span_elements($element['text']);
                    $text = preg_replace('/[ ]{2}\n/', '<br />'."\n", $text);
                    if ($context === 'li' and $index === 0)
                    {
                        if (isset($element['interrupted'])) {   $markup .= "\n".'<p>'.$text.'</p>'."\n";}
                        else {$markup .= $text;}
                    }
                    else{$markup .= '<p>'.$text.'</p>'."\n";}
                    break;

                case 'blockquote':
                    $text =parse_block_elements($element['lines']);
                    $markup .= '<blockquote>'."\n".$text.'</blockquote>'."\n";
                    break;

                case 'code':
                    $text = htmlentities($element['text'], ENT_NOQUOTES);
                    strpos($text, "\x1A\\") !== FALSE and $text = strtr($text, $escape_sequence_map);
                    $markup .= '<pre><code>'.$text.'</code></pre>'."\n";
                    break;

                case 'h.':
                    $text = parse_span_elements($element['text']);
                    $markup .= '<h'.$element['level'].'>'.$text.'</h'.$element['level'].'>'."\n";
                    break;

                case 'hr':
                    $markup .= '<hr />'."\n";
                    break;

                case 'li':
                    if (isset($element['ordered'])) # first
                    {
                        $list_type = $element['ordered'] ? 'ol' : 'ul';
                        $markup .= '<'.$list_type.'>'."\n";
                    }
                    if (isset($element['interrupted']) and ! isset($element['last']))
                    {
                        $element['lines'] []= '';
                    }
                    $text = parse_block_elements($element['lines'], 'li');
                    $markup .= '<li>'.$text.'</li>'."\n";
                    isset($element['last']) and $markup .= '</'.$list_type.'>'."\n";
                    break;

                default:
                    $markup .= $element['text']."\n";
            }
        }
        return $markup;
    }

    function parse_span_elements($text)
    {
        $map = array();
        $index = 0;

        # code span
        if (strpos($text, '`') !== FALSE and preg_match_all('/`(.+?)`/', $text, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $matches)
            {
                $element_text = $matches[1];
                $element_text = htmlentities($element_text, ENT_NOQUOTES);

                # decodes escape sequences
                $escape_sequence_map
                    and strpos($element_text, "\x1A") !== FALSE
                    and $element_text = strtr($element_text, $escape_sequence_map);

                # composes element
                $element = '<code>'.$element_text.'</code>';

                # encodes element
                $code = "\x1A".'$'.$index;
                $text = str_replace($matches[0], $code, $text);
                $map[$code] = $element;
                $index ++;
            }
        }

        # inline link or image

        if (strpos($text, '](') !== FALSE and preg_match_all('/(!?)(\[((?:[^\[\]]|(?2))*)\])\((.*?)\)/', $text, $matches, PREG_SET_ORDER)) # inline
        {
            foreach ($matches as $matches)
            {
                $url = $matches[4];
                strpos($url, '&') !== FALSE and $url = preg_replace('/&(?!#?\w+;)/', '&amp;', $url);
                if ($matches[1]) # image
                {
                    $element = '<img alt="'.$matches[3].'" src="'.$url.'">';
                }
                else
                {
                    $element_text =parse_span_elements($matches[3]);
                    $element = '<a href="'.$url.'">'.$element_text.'</a>';
                }


                $code = "\x1A".'$'.$index;
                $text = str_replace($matches[0], $code, $text);
                $map[$code] = $element;
                $index ++;
            }
        }

        # reference link or image
        if (strpos($text, '[') !== FALSE and preg_match_all('/(!?)\[(.+?)\](?:\n?[ ]?\[(.*?)\])?/ms', $text, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $matches)
            {
                $link_definition = isset($matches[3]) && $matches[3]
                    ? $matches[3]
                    : $matches[2]; # implicit

                $link_definition = strtolower($link_definition);
                if (isset($reference_map[$link_definition]))
                {
                    $url = $reference_map[$link_definition];
                    strpos($url, '&') !== FALSE and $url = preg_replace('/&(?!#?\w+;)/', '&amp;', $url);
                    if ($matches[1]) # image
                    {
                        $element = '<img alt="'.$matches[2].'" src="'.$url.'">';
                    }
                    else # anchor
                    {
                        $element_text = parse_span_elements($matches[2]);
                        $element = '<a href="'.$url.'">'.$element_text.'</a>';
                    }


                    $code = "\x1A".'$'.$index;
                    $text = str_replace($matches[0], $code, $text);
                    $map[$code] = $element;
                    $index ++;
                }
            }
        }

        # automatic link

        if (strpos($text, '<') !== FALSE and preg_match_all('/<((https?|ftp|dict):[^\^\s]+?)>/i', $text, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $matches)
            {
                $url = $matches[1];
                strpos($url, '&') !== FALSE and $url = preg_replace('/&(?!#?\w+;)/', '&amp;', $url);
                $element = '<a href=":href">:text</a>';
                $element = str_replace(':text', $url, $element);
                $element = str_replace(':href', $url, $element);

                $code = "\x1A".'$'.$index;
                $text = str_replace($matches[0], $code, $text);
                $map[$code] = $element;
                $index ++;
            }
        }


        strpos($text, '&') !== FALSE and $text = preg_replace('/&(?!#?\w+;)/', '&amp;', $text);
        strpos($text, '<') !== FALSE and $text = preg_replace('/<(?!\/?\w.*?>)/', '&lt;', $text);

        if (strpos($text, '_') !== FALSE)
        {
            $text = preg_replace('/__(?=\S)(.+?)(?<=\S)__(?!_)/s', '<strong>$1</strong>', $text);
            $text = preg_replace('/_(?=\S)(.+?)(?<=\S)_/s', '<em>$1</em>', $text);
        }

        if (strpos($text, '*') !== FALSE)
        {
            $text = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*(?!\*)/s', '<strong>$1</strong>', $text);
            $text = preg_replace('/\*(?=\S)(.+?)(?<=\S)\*/s', '<em>$1</em>', $text);
        }

        $text = strtr($text, $map);
        return $text;

    }


function aff($a,$stop=true){echo 'Arret dans le fichier '.__FILE__.'<pre>';var_dump($a);echo '</pre>';if ($stop){exit();}}

function reduceTitle($string)
{
    $len = 50;
    if (strlen($string) > $len)
        return substr($string, 0, $len).'...';
    else
        return $string;

}

?>
