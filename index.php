<?php
// Shaarli 0.0.41 beta - Shaare your links...
// This version is a fork by Bronco (warriordudimanche.net) and not the original SebSauvage's version
//
// The personal, minimalist, super-fast, no-database delicious clone. By sebsauvage.net
// http://sebsauvage.net/wiki/doku.php?id=php:shaarli
// Licence: http://www.opensource.org/licenses/zlib-license.php
// Requires: php 5.1.x  (but autocomplete fields will only work if you have php 5.2.x)
// -----------------------------------------------------------------------------------------------
// NEVER TRUST IN PHP.INI
// Some hosts do not define a default timezone in php.ini,
// so we have to do this for avoid the strict standard error.
date_default_timezone_set('UTC');
// -----------------------------------------------------------------------------------------------
//error_reporting(-1); // See all errors (for debugging only)
// I splitted some shaarli's parts into separated files, structure is easier to understand for me (Bronco)
include('inc/configuration.php');
include('inc/functions.php');
include('inc/languages.php');
include('inc/initialisation.php');

// -----------------------------------------------------------------------------------------------
checkphpversion();
error_reporting(E_ALL^E_WARNING);  // See all error except warnings.
//error_reporting(-1); // See all errors (for debugging only)

// ------------------------------------------------------------------------------------------
// Process login form: Check if login/password is correct.
if (isset($_POST['login']))
{

    if (!ban_canLogin()) die('I said: NO. You are banned for the moment. Go away.');

    if (isset($_POST['password']) && tokenOk($_POST['token']) && (check_auth($_POST['login'], $_POST['password'])))
    {
        // Login/password is ok.
        ban_loginOk();
        // If user wants to keep the session cookie even after the browser closes:
        if (!empty($_POST['longlastingsession']))
        {
            setcookie('shaarli_staySignedIn', STAY_SIGNED_IN_TOKEN, time()+31536000, WEB_PATH);
            $_SESSION['longlastingsession']=31536000;  // (31536000 seconds = 1 year)
            $_SESSION['expires_on']=time()+$_SESSION['longlastingsession'];  // Set session expiration on server-side.

            $cookiedir = ''; if(dirname($_SERVER['SCRIPT_NAME'])!='/') $cookiedir=dirname($_SERVER["SCRIPT_NAME"]).'/';
            session_set_cookie_params($_SESSION['longlastingsession'],$cookiedir,$_SERVER['HTTP_HOST']); // Set session cookie expiration on client side
            // Note: Never forget the trailing slash on the cookie path !
            session_regenerate_id(true);  // Send cookie with new expiration date to browser.
        }
        else // Standard session expiration (=when browser closes)
        {
            $cookiedir = ''; if(dirname($_SERVER['SCRIPT_NAME'])!='/') $cookiedir=dirname($_SERVER["SCRIPT_NAME"]).'/';
            session_set_cookie_params(0,$cookiedir,$_SERVER['HTTP_HOST']); // 0 means "When browser closes"
            session_regenerate_id(true);
        }
        // Optional redirect after login:
        if (isset($_GET['post'])) { header('Location: ?post='.urlencode($_GET['post']).(!empty($_GET['title'])?'&title='.urlencode($_GET['title']):'').(!empty($_GET['source'])?'&source='.urlencode($_GET['source']):'')); exit; }
        if (isset($_POST['returnurl']))
        {
            if (endsWith($_POST['returnurl'],'?do=login')) { header('Location: ?'); exit; } // Prevent loops over login screen.
            header('Location: '.$_POST['returnurl']); exit;
        }
        header('Location: ?'); exit;
    }
    else
    {
        ban_loginFailed();
        $redir = '';
        $_SESSION['badlogin'] = e('wrong login or password', FALSE);
        if (isset($_GET['post']))
        {
            $redir = '&post='.urlencode($_GET['post']).(!empty($_GET['title'])?'&title='.urlencode($_GET['title']):'').(!empty($_GET['source'])?'&source='.urlencode($_GET['source']):'');
        }
        header('Location: ?do=login'.$redir);
        exit;
    }
}


// ------------------------------------------------------------------------------------------
// Render HTML page (according to URL parameters and user rights)

function renderPage()
{
    $LINKSDB=new linkdb(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI']);  // Read links from database (and filter private links if used it not logged in).



    // -----------------------------------------------------------------------
    // Bronco: "added this to easily add new get capacities to shaarli"
    include('inc/public_get_commands.php');
    // -----------------------------------------------------------------------


    // -------- Display login form.
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=login'))
    {
        if ($GLOBALS['config']['OPEN_SHAARLI']) { header('Location: ?'); exit; }  // No need to login for open Shaarli
        $token=''; if (ban_canLogin()) $token=getToken(); // Do not waste token generation if not useful.
        $PAGE = new pageBuilder;
        $PAGE->assign('token',$token);
        if(isset($_SESSION['badlogin']))
            $PAGE->assign('msg', $_SESSION['badlogin']);
        else
            $PAGE->assign('msg', NULL);
        $PAGE->assign('returnurl',(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER']:''));
        $PAGE->renderPage('loginform');
        unset($_SESSION['badlogin']);
        exit;
    }
    // -------- User wants to logout.
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=logout'))
    {
        invalidateCaches();
        logout();
        header('Location: ?');
        exit;
    }

    // -------- Picture wall
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=picwall'))
    {
        // Optionnaly filter the results:
        $links=array();
        if (!empty($_GET['searchterm'])) $links = $LINKSDB->filterFulltext($_GET['searchterm']);
        elseif (!empty($_GET['searchtags']))   $links = $LINKSDB->filterTags(trim($_GET['searchtags']));
        else $links = $LINKSDB;
        $body='';
        $linksToDisplay=array();

        // Get only links which have a thumbnail.
        foreach($links as $link)
        {
            $permalink='?'.htmlspecialchars(smallhash($link['linkdate']),ENT_QUOTES);
            $thumb=lazyThumbnail($link['url'],$permalink);
            if ($thumb!='') // Only output links which have a thumbnail.
            {
                $link['thumbnail']=$thumb; // Thumbnail HTML code.
                $link['permalink']=$permalink;
                $linksToDisplay[]=$link; // Add to array.
            }
        }
        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('linksToDisplay',$linksToDisplay);
        $PAGE->renderPage('picwall');
        exit;
    }

    // -------- Tag cloud
    /* No longer used: see in inc/public_get_commands.php
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=tagcloud'))
    {
        $tags= $LINKSDB->allTags();
        // We sort tags alphabetically, then choose a font size according to count.
        // First, find max value.
        $maxcount=0; foreach($tags as $key=>$value) $maxcount=max($maxcount,$value);
        ksort($tags);
        $tagList=array();
        foreach($tags as $key=>$value)
        {
            $tagList[$key] = array('count'=>$value,'size'=>max(40*$value/$maxcount,8));
        }
        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('tags',$tagList);
        $PAGE->renderPage('tagcloud');
        exit;
    }*/

    // -------- User clicks on a tag in a link: The tag is added to the list of searched tags (searchtags=...)
    if (isset($_GET['addtag']))
    {
        // Get previous URL (http_referer) and add the tag to the searchtags parameters in query.
        if (empty($_SERVER['HTTP_REFERER'])) { header('Location: ?searchtags='.urlencode($_GET['addtag'])); exit; } // In case browser does not send HTTP_REFERER
        parse_str(parse_url($_SERVER['HTTP_REFERER'],PHP_URL_QUERY), $params);
        $params['searchtags'] = (empty($params['searchtags']) ?  trim($_GET['addtag']) : trim($params['searchtags']).' '.trim($_GET['addtag']));
        unset($params['page']); // We also remove page (keeping the same page has no sense, since the results are different)
        header('Location: ?'.http_build_query($params));
        exit;
    }

    // -------- User clicks on a tag in result count: Remove the tag from the list of searched tags (searchtags=...)
    if (isset($_GET['removetag']))
    {
        // Get previous URL (http_referer) and remove the tag from the searchtags parameters in query.
        if (empty($_SERVER['HTTP_REFERER'])) { header('Location: ?'); exit; } // In case browser does not send HTTP_REFERER
        parse_str(parse_url($_SERVER['HTTP_REFERER'],PHP_URL_QUERY), $params);
        if (isset($params['searchtags']))
        {
            $tags = explode(' ',$params['searchtags']);
            $tags=array_diff($tags, array($_GET['removetag'])); // Remove value from array $tags.
            if (count($tags)==0) unset($params['searchtags']); else $params['searchtags'] = implode(' ',$tags);
            unset($params['page']); // We also remove page (keeping the same page has no sense, since the results are different)
        }
        header('Location: ?'.http_build_query($params));
        exit;
    }

    // -------- User wants to change the number of links per page (linksperpage=...)
    if (isset($_GET['linksperpage']))
    {
        if (is_numeric($_GET['linksperpage'])) { $_SESSION['LINKS_PER_PAGE']=abs(intval($_GET['linksperpage'])); }
        // Make sure the referer is from Shaarli itself.
        $referer = '?';
        if (!empty($_SERVER['HTTP_REFERER']) && strcmp(parse_url($_SERVER['HTTP_REFERER'],PHP_URL_HOST),$_SERVER['HTTP_HOST'])==0)
            $referer = $_SERVER['HTTP_REFERER'];
        header('Location: '.$referer);
        exit;
    }

    // -------- User wants to see only private links (toggle)
    if (isset($_GET['privateonly']))
    {
        if (empty($_SESSION['privateonly']))
        {
            $_SESSION['privateonly']=1; // See only private links
        }
        else
        {
            unset($_SESSION['privateonly']); // See all links
        }
        // Make sure the referer is from Shaarli itself.
        $referer = '?';
        if (!empty($_SERVER['HTTP_REFERER']) && strcmp(parse_url($_SERVER['HTTP_REFERER'],PHP_URL_HOST),$_SERVER['HTTP_HOST'])==0)
            $referer = $_SERVER['HTTP_REFERER'];
        header('Location: '.$referer);
        exit;
    }

    // -------- Handle other actions allowed for non-logged in users:
    if (!isLoggedIn())
    {
        // User tries to post new link but is not loggedin:
        // Show login screen, then redirect to ?post=...
        if (isset($_GET['post']))
        {
            header('Location: ?do=login&post='.urlencode($_GET['post']).(!empty($_GET['title'])?'&title='.urlencode($_GET['title']):'').(!empty($_GET['source'])?'&source='.urlencode($_GET['source']):'')); // Redirect to login page, then back to post link.
            exit;
        }

        //Bronco changed that
        include('inc/render_basic_page.php');

    }
    // -------- All other functions are reserved for the registered user:

    // -----------------------------------------------------------------------
    // Bronco: "added this to easily add new get capacities to shaarli"
    include('inc/private_get_commands.php');
    // -----------------------------------------------------------------------


    // -------- Display the Tools menu if requested (import/export/bookmarklet...)
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=tools'))
    {
        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('pageabsaddr',indexUrl());
        $PAGE->renderPage('tools');
        exit;
    }

    // -------- User wants to change his/her password.
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=changepasswd'))
    {
        if ($GLOBALS['config']['OPEN_SHAARLI']) die('You are not supposed to change a password on an Open Shaarli.');
        if (!empty($_POST['setpassword']) && !empty($_POST['oldpassword']))
        {
            if (!tokenOk($_POST['token'])) die(e('Wrong token.',false)); // Go away !

            // Make sure old password is correct.
            $oldhash = sha1($_POST['oldpassword'].$GLOBALS['login'].$GLOBALS['salt']);
            if ($oldhash!=$GLOBALS['hash']) { echo '<script language="JavaScript">alert("The old password is not correct.");document.location=\'?do=changepasswd\';</script>'; exit; }
            // Save new password
            $GLOBALS['salt'] = sha1(uniqid('',true).'_'.mt_rand()); // Salt renders rainbow-tables attacks useless.
            $GLOBALS['hash'] = sha1($_POST['setpassword'].$GLOBALS['login'].$GLOBALS['salt']);
            writeConfig();
            echo '<script language="JavaScript">alert("'.e('Your password has been changed',false).'.");document.location=\'?do=tools\';</script>';
            exit;
        }
        else // show the change password form.
        {
            $PAGE = new pageBuilder;
            $PAGE->assign('linkcount',count($LINKSDB));
            $PAGE->assign('token',getToken());
            $PAGE->renderPage('changepassword');
            exit;
        }
    }

    // -------- User wants to change configuration
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=configure'))
    {
        if (!empty($_POST['title']) )
        {
            if (!tokenOk($_POST['token'])) die(e('Wrong token.',false)); // Go away !
            $tz = 'UTC';
            if (!empty($_POST['continent']) && !empty($_POST['city']))
                if (isTZvalid($_POST['continent'],$_POST['city']))
                    $tz = $_POST['continent'].'/'.$_POST['city'];
            $GLOBALS['timezone'] = $tz;
            $GLOBALS['title']=$_POST['title'];
            $GLOBALS['redirector']=$_POST['redirector'];
            $GLOBALS['disablesessionprotection']=!empty($_POST['disablesessionprotection']);
            $GLOBALS['disablejquery']=!empty($_POST['disablejquery']);
            $GLOBALS['privateLinkByDefault']=!empty($_POST['privateLinkByDefault']);
            writeConfig();
            echo '<script language="JavaScript">alert("'.e('Configuration was saved',false).'.");document.location=\'?do=tools\';</script>';
            exit;
        }
        else // Show the configuration form.
        {
            $PAGE = new pageBuilder;
            $PAGE->assign('linkcount',count($LINKSDB));
            $PAGE->assign('token',getToken());
            $PAGE->assign('title',htmlspecialchars( empty($GLOBALS['title']) ? '' : $GLOBALS['title'] , ENT_QUOTES));
            $PAGE->assign('redirector',htmlspecialchars( empty($GLOBALS['redirector']) ? '' : $GLOBALS['redirector'] , ENT_QUOTES));
            list($timezone_form,$timezone_js) = templateTZform($GLOBALS['timezone']);
            $PAGE->assign('timezone_form',$timezone_form); // FIXME: put entire tz form generation in template ?
            $PAGE->assign('timezone_js',$timezone_js);
            $PAGE->renderPage('configure');
            exit;
        }
    }

    // -------- User wants to rename a tag or delete it
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=changetag'))
    {
        if (empty($_POST['fromtag']))
        {
            $PAGE = new pageBuilder;
            $PAGE->assign('linkcount',count($LINKSDB));
            $PAGE->assign('token',getToken());
            $PAGE->renderPage('changetag');
            exit;
        }
        if (!tokenOk($_POST['token'])) die(e('Wrong token.',false));

        // Delete a tag:
        if (!empty($_POST['deletetag']) && !empty($_POST['fromtag']))
        {
            $needle=trim($_POST['fromtag']);
            $linksToAlter = $LINKSDB->filterTags($needle,true); // true for case-sensitive tag search.
            foreach($linksToAlter as $key=>$value)
            {
                $tags = explode(' ',trim($value['tags']));
                unset($tags[array_search($needle,$tags)]); // Remove tag.
                $value['tags']=trim(implode(' ',$tags));
                $LINKSDB[$key]=$value;
            }
            $LINKSDB->savedb(); // save to disk
            echo '<script language="JavaScript">alert("'.e('Tag was removed from ',false).count($linksToAlter).e('links',false).'");document.location=\'?\';</script>';
            exit;
        }

        // Rename a tag:
        if (!empty($_POST['renametag']) && !empty($_POST['fromtag']) && !empty($_POST['totag']))
        {
            $needle=trim($_POST['fromtag']);
            $linksToAlter = $LINKSDB->filterTags($needle,true); // true for case-sensitive tag search.
            foreach($linksToAlter as $key=>$value)
            {
                $tags = explode(' ',trim($value['tags']));
                $tags[array_search($needle,$tags)] = trim($_POST['totag']); // Remplace tags value.
                $value['tags']=trim(implode(' ',$tags));
                $LINKSDB[$key]=$value;
            }
            $LINKSDB->savedb(); // save to disk
            echo '<script language="JavaScript">alert("'.e('Tag was renamed in ',false).count($linksToAlter).e('links',false).'.");document.location=\'?searchtags='.urlencode($_POST['totag']).'\';</script>';
            exit;
        }
    }

    // -------- User wants to add a link without using the bookmarklet: show form.
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=addlink'))
    {
        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->renderPage('addlink');
        exit;
    }



    // -----------------------------------------------------------------------
    // Bronco: "$_POST enhancements are in the file included below"
    include('inc/personal_post_actions.php');
    // -----------------------------------------------------------------------




    // -------- User clicked the "Save" button when editing a link: Save link to database.
    /* No longer used: see personal_post_actions.php

    if (isset($_POST['save_edit']))
    {
        if (!tokenOk($_POST['token'])) die(e('Wrong token.',false)); // Go away !
        $tags = trim(preg_replace('/\s\s+/',' ', $_POST['lf_tags'])); // Remove multiple spaces.
        $linkdate=$_POST['lf_linkdate'];
        $url = trim($_POST['lf_url']);
        if (!startsWith($url,'http:') && !startsWith($url,'https:') && !startsWith($url,'ftp:') && !startsWith($url,'magnet:') && !startsWith($url,'?'))
            $url = 'http://'.$url;
        $link = array('title'=>trim($_POST['lf_title']),'url'=>$url,'description'=>trim($_POST['lf_description']),'private'=>(isset($_POST['lf_private']) ? 1 : 0),
                      'linkdate'=>$linkdate,'tags'=>str_replace(',',' ',$tags));
        if ($link['title']=='') $link['title']=$link['url']; // If title is empty, use the URL as title.

        $LINKSDB[$linkdate] = $link;
        $LINKSDB->savedb(); // save to disk
        pubsubhub();

        // If we are called from the bookmarklet, we must close the popup:
        if (isset($_GET['source']) && $_GET['source']=='bookmarklet') { echo '<script language="JavaScript">self.close();</script>'; exit; }
        $returnurl = ( isset($_POST['returnurl']) ? $_POST['returnurl'] : '?' );
        $returnurl .= '#'.smallHash($linkdate);  // Scroll to the link which has been edited.
        header('Location: '.$returnurl); // After saving the link, redirect to the page the user was on.
        exit;
    }
    */

    // -------- User clicked the "Cancel" button when editing a link.
    if (isset($_POST['cancel_edit']))
    {
        // If we are called from the bookmarklet, we must close the popup;
        if (isset($_GET['source']) && $_GET['source']=='bookmarklet') { echo '<script language="JavaScript">self.close();</script>'; exit; }
        $returnurl = ( isset($_POST['returnurl']) ? $_POST['returnurl'] : '?' );
        $returnurl .= '#'.smallHash($_POST['lf_linkdate']);  // Scroll to the link which has been edited.
        header('Location: '.$returnurl); // After canceling, redirect to the page the user was on.
        exit;
    }

    // -------- User clicked the "Delete" button when editing a link : Delete link from database.
    if (isset($_POST['delete_link']))
    {
        if (!tokenOk($_POST['token'])) die(e('Wrong token.',false));
        // We do not need to ask for confirmation:
        // - confirmation is handled by javascript
        // - we are protected from XSRF by the token.
        $linkdate=$_POST['lf_linkdate'];
        unset($LINKSDB[$linkdate]);
        $LINKSDB->savedb(); // save to disk

        // If we are called from the bookmarklet, we must close the popup:
        if (isset($_GET['source']) && $_GET['source']=='bookmarklet') { echo '<script language="JavaScript">self.close();</script>'; exit; }
        $returnurl = ( isset($_POST['returnurl']) ? $_POST['returnurl'] : '?' );
        if ($returnurl=='?') { $returnurl = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '?'); }
        header('Location: '.$returnurl); // After deleting the link, redirect to the page the user was on.
        exit;
    }

    // -------- User clicked the "EDIT" button on a link: Display link edit form.
    /* THIS ONE IS NO LONGER USED, see the inc/private_get_commands.php file
    if (isset($_GET['edit_link']))
    {
        $link = $LINKSDB[$_GET['edit_link']];  // Read database
        if (!$link) { header('Location: ?'); exit; } // Link not found in database.
        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('link',$link);
        $PAGE->assign('link_is_new',false);
        $PAGE->assign('token',getToken()); // XSRF protection.
        $PAGE->assign('http_referer',(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''));
        $PAGE->renderPage('editlink');
        exit;
    }
*/
    // -------- User want to post a new link: Display link edit form.
    /* THIS ONE IS NO LONGER USED, see the inc/personal_post_actions.php file
    if (isset($_GET['post']))
    {
        $url=$_GET['post'];

        // We remove the annoying parameters added by FeedBurner and GoogleFeedProxy (?utm_source=...)
        $i=strpos($url,'&utm_source='); if ($i!==false) $url=substr($url,0,$i);
        $i=strpos($url,'?utm_source='); if ($i!==false) $url=substr($url,0,$i);
        $i=strpos($url,'#xtor=RSS-'); if ($i!==false) $url=substr($url,0,$i);

        $link_is_new = false;
        $link = $LINKSDB->getLinkFromUrl($url); // Check if URL is not already in database (in this case, we will edit the existing link)
        if (!$link)
        {
            $link_is_new = true;  // This is a new link
            $linkdate = strval(date('Ymd_His'));
            $title = (empty($_GET['title']) ? '' : $_GET['title'] ); // Get title if it was provided in URL (by the bookmarklet).
            $description = (empty($_GET['description']) ? '' : $_GET['description']); // Get description if it was provided in URL (by the bookmarklet). [Bronco added that]
            $tags = (empty($_GET['tags']) ? '' : $_GET['tags'] ); // Get tags if it was provided in URL
            $private = (!empty($_GET['private']) && $_GET['private'] === "1" ? 1 : 0); // Get private if it was provided in URL
            if (($url!='') && parse_url($url,PHP_URL_SCHEME)=='') $url = 'http://'.$url;
            // If this is an HTTP link, we try go get the page to extact the title (otherwise we will to straight to the edit form.)
            if (empty($title) && parse_url($url,PHP_URL_SCHEME)=='http')
            {
                list($status,$headers,$data) = getHTTP($url,4); // Short timeout to keep the application responsive.
                // FIXME: Decode charset according to specified in either 1) HTTP response headers or 2) <head> in html
                if (strpos($status,'200 OK')!==false)
 					 {
                        // Look for charset in html header.
 						preg_match('#<meta .*charset=.*>#Usi', $data, $meta);

 						// If found, extract encoding.
 						if (!empty($meta[0]))
 						{
 							// Get encoding specified in header.
 							preg_match('#charset="?(.*)"#si', $meta[0], $enc);
 							// If charset not found, use utf-8.
							$html_charset = (!empty($enc[1])) ? strtolower($enc[1]) : 'utf-8';
 						}
 						else { $html_charset = 'utf-8'; }

 						// Extract title
 						$title = html_extract_title($data);
 						if (!empty($title))
 						{
 							// Re-encode title in utf-8 if necessary.
 							$title = ($html_charset == 'iso-8859-1') ? utf8_encode($title) : $title;
 						}
 					}
            }
            if ($url=='') $url='?'.smallHash($linkdate); // In case of empty URL, this is just a text (with a link that point to itself)
            $link = array('linkdate'=>$linkdate,'title'=>$title,'url'=>$url,'description'=>$description,'tags'=>$tags,'private'=>$private);
        }

        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('link',$link);
        $PAGE->assign('link_is_new',$link_is_new);
        $PAGE->assign('token',getToken()); // XSRF protection.
        $PAGE->assign('http_referer',(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''));
        $PAGE->renderPage('editlink');
        exit;
    }
    */
    // -------- Export as Netscape Bookmarks HTML file.
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=export'))
    {
        if (empty($_GET['what']))
        {
            $PAGE = new pageBuilder;
            $PAGE->assign('linkcount',count($LINKSDB));
            $PAGE->renderPage('export');
            exit;
        }
        $exportWhat=$_GET['what'];
        if (!array_intersect(array('all','public','private'),array($exportWhat))) die('What are you trying to export ???');

        header('Content-Type: text/html; charset=utf-8');
        header('Content-disposition: attachment; filename=bookmarks_'.$exportWhat.'_'.strval(date('Ymd_His')).'.html');
        $currentdate=date('Y/m/d H:i:s');
        echo <<<HTML
<!DOCTYPE NETSCAPE-Bookmark-file-1>
<!-- This is an automatically generated file.
     It will be read and overwritten.
     DO NOT EDIT! -->
<!-- Shaarli {$exportWhat} bookmarks export on {$currentdate} -->
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
<TITLE>Bookmarks</TITLE>
<H1>Bookmarks</H1>
HTML;
        foreach($LINKSDB as $link)
        {
            if ($exportWhat=='all' ||
               ($exportWhat=='private' && $link['private']!=0) ||
               ($exportWhat=='public' && $link['private']==0))
            {
                echo '<DT><A HREF="'.htmlspecialchars($link['url']).'" ADD_DATE="'.linkdate2timestamp($link['linkdate']).'" PRIVATE="'.$link['private'].'"';
                if ($link['tags']!='') echo ' TAGS="'.htmlspecialchars(str_replace(' ',',',$link['tags'])).'"';
                echo '>'.htmlspecialchars($link['title'])."</A>\n";
                if ($link['description']!='') echo '<DD>'.htmlspecialchars($link['description'])."\n";
            }
        }
                exit;
    }

    // -------- User is uploading a file for import
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=upload'))
    {
        // If file is too big, some form field may be missing.
        if (!isset($_POST['token']) || (!isset($_FILES)) || (isset($_FILES['filetoupload']['size']) && $_FILES['filetoupload']['size']==0))
        {
            $returnurl = ( empty($_SERVER['HTTP_REFERER']) ? '?' : $_SERVER['HTTP_REFERER'] );
            echo '<script language="JavaScript">alert("'.e('The file you are trying to upload is probably bigger than what this webserver can accept',false).' ('.getMaxFileSize().' bytes). '.e('Please upload in smaller chunks',false).'.");document.location=\''.htmlspecialchars($returnurl).'\';</script>';
            exit;
        }
        if (!tokenOk($_POST['token'])) die(e('Wrong token.',false));
        importFile();
        exit;
    }

    // -------- Show upload/import dialog:
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=import'))
    {
        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('token',getToken());
        $PAGE->assign('maxfilesize',getMaxFileSize());
        $PAGE->renderPage('import');
        exit;
    }



    // -------- Otherwise, simply display search form and links:

    //Bronco changed that
    include('inc/render_basic_page.php');

}

// -----------------------------------------------------------------------------------------------
// Template for the list of links (<div id="linklist">)
// This function fills all the necessary fields in the $PAGE for the template 'linklist.html'
function buildLinkList($PAGE,$LINKSDB)
{
    // ---- Filter link database according to parameters
    $linksToDisplay=array();
    $search_type='';
    $search_crits='';
    if (isset($_GET['searchterm'])) // Fulltext search
    {
        $linksToDisplay = $LINKSDB->filterFulltext(trim($_GET['searchterm']));
        $search_crits=htmlspecialchars(trim($_GET['searchterm']));
        $search_type='fulltext';
    }
    elseif (isset($_GET['searchtags'])) // Search by tag
    {
        $linksToDisplay = $LINKSDB->filterTags(trim($_GET['searchtags']));
        $search_crits=explode(' ',trim($_GET['searchtags']));
        $search_type='tags';
    }
    elseif (isset($_SERVER['QUERY_STRING']) && preg_match('/[a-zA-Z0-9-_@]{6}(&.+?)?/',$_SERVER['QUERY_STRING'])) // Detect smallHashes in URL
    {
        $linksToDisplay = $LINKSDB->filterSmallHash(substr(trim($_SERVER["QUERY_STRING"], '/'),0,6));
        if (count($linksToDisplay)==0)
        {
            header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
            echo '<h1>404 '.e('Not found',false).'</h1>'.e('Oh crap. The link you are trying to reach does not exist or has been deleted.',false);
            echo '<br>'.e('You would mind',false).' <a href="?">'.e('clicking here',false).'</a> ?';
            exit;
        }
        $search_type='permalink';
    }
    else
        $linksToDisplay = $LINKSDB;  // otherwise, display without filtering.

    // Option: Show only private links
    if (!empty($_SESSION['privateonly']))
    {
        $tmp = array();
        foreach($linksToDisplay as $linkdate=>$link)
        {
            if ($link['private']!=0) $tmp[$linkdate]=$link;
        }
        $linksToDisplay=$tmp;
    }

    // ---- Handle paging.
    /* Can someone explain to me why you get the following error when using array_keys() on an object which implements the interface ArrayAccess ???
       "Warning: array_keys() expects parameter 1 to be array, object given in ... "
       If my class implements ArrayAccess, why won't array_keys() accept it ?  ( $keys=array_keys($linksToDisplay); )
    */
    $keys=array(); foreach($linksToDisplay as $key=>$value) { $keys[]=$key; } // Stupid and ugly. Thanks php.

    // If there is only a single link, we change on-the-fly the title of the page.
    if (count($linksToDisplay)==1) $GLOBALS['pagetitle'] = $linksToDisplay[$keys[0]]['title'].' - '.$GLOBALS['title'];

    // Select articles according to paging.
    $pagecount = ceil(count($keys)/$_SESSION['LINKS_PER_PAGE']);
    $pagecount = ($pagecount==0 ? 1 : $pagecount);
    $page=( empty($_GET['page']) ? 1 : intval($_GET['page']));
    $page = ( $page<1 ? 1 : $page );
    $page = ( $page>$pagecount ? $pagecount : $page );
    $i = ($page-1)*$_SESSION['LINKS_PER_PAGE']; // Start index.
    $end = $i+$_SESSION['LINKS_PER_PAGE'];
    $linkDisp=array(); // Links to display
    while ($i<$end && $i<count($keys))
    {
        $link = $linksToDisplay[$keys[$i]];
        $link['description']=nl2br(keepMultipleSpaces(text2clickable(htmlspecialchars($link['description']))));
        $title=$link['title'];
        $classLi =  $i%2!=0 ? '' : 'publicLinkHightLight';
        $link['class'] = ($link['private']==0 ? $classLi : 'private');
        $link['localdate']=linkdate2locale($link['linkdate']);
        $taglist = explode(' ',$link['tags']);
        uasort($taglist, 'strcasecmp');
        $link['taglist']=$taglist;
        $linkDisp[$keys[$i]] = $link;
        $i++;
    }

    // Compute paging navigation
    $searchterm= ( empty($_GET['searchterm']) ? '' : '&searchterm='.$_GET['searchterm'] );
    $searchtags= ( empty($_GET['searchtags']) ? '' : '&searchtags='.$_GET['searchtags'] );
    $paging='';
    $previous_page_url=''; if ($i!=count($keys)) $previous_page_url='?page='.($page+1).$searchterm.$searchtags;
    $next_page_url='';if ($page>1) $next_page_url='?page='.($page-1).$searchterm.$searchtags;

    $token = ''; if (isLoggedIn()) $token=getToken();

    // Fill all template fields.
    $PAGE->assign('linkcount',count($LINKSDB));
    $PAGE->assign('previous_page_url',$previous_page_url);
    $PAGE->assign('next_page_url',$next_page_url);
    $PAGE->assign('page_current',$page);
    $PAGE->assign('page_max',$pagecount);
    $PAGE->assign('result_count',count($linksToDisplay));
    $PAGE->assign('search_type',$search_type);
    $PAGE->assign('search_crits',$search_crits);
    $PAGE->assign('redirector',empty($GLOBALS['redirector']) ? '' : $GLOBALS['redirector']); // optional redirector URL
    $PAGE->assign('token',$token);
    $PAGE->assign('links',$linkDisp);
    return;
}


if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=genthumbnail')) { genThumbnail(); exit; }  // Thumbnail generation/cache does not need the link database.
if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=rss')) { showRSS(); exit; }
if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=atom')) { showATOM(); exit; }
if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=dailyrss')) { showDailyRSS(); exit; }
if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=daily')) { showDaily(); exit; }
if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'ws=')) { processWS(); exit; } // Webservices (for jQuery/jQueryUI)
if (!isset($_SESSION['LINKS_PER_PAGE'])) $_SESSION['LINKS_PER_PAGE']=$GLOBALS['config']['LINKS_PER_PAGE'];
renderPage();
?>
