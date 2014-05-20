<?php
// Add here your personal post actions 
/*
if (isset($_POST['something'])){
	// do something


	exit; // don't forget that ;-)
}



*/
	// Bronco post link version : 
	// uses a specific bookmark template to add some pictures from the posted url
	// also allow to use the addnote get param to set a specific bookmark version:
	//		- private status /auto tagged with "notebook" ... 
	//  	- can use url to add an illustration for this note (like a logo for example)

    // -------- User want to post a new link: Display link edit form.
    if (isset($_GET['post']))
    {
        $url=$_GET['post'];

        // We remove the annoying parameters added by FeedBurner and GoogleFeedProxy (?utm_source=...)
        $i=strpos($url,'&utm_source='); if ($i!==false) $url=substr($url,0,$i);
        $i=strpos($url,'?utm_source='); if ($i!==false) $url=substr($url,0,$i);
        $i=strpos($url,'#xtor=RSS-'); if ($i!==false) $url=substr($url,0,$i);

        $link_is_new = false;
        $renderpage='editlink';$pagetitle= $GLOBALS['title'];
        $link = $LINKSDB->getLinkFromUrl($url); // Check if URL is not already in database (in this case, we will edit the existing link)
        if (!$link)
        {
            $link_is_new = true;  // This is a new link
            $linkdate = strval(date('Ymd_His'));
            $title = (empty($_GET['title']) ? '' : $_GET['title'] ); // Get title if it was provided in URL (by the bookmarklet).
            $description = (empty($_GET['description']) ? '' : $_GET['description']); // Get description if it was provided in URL (by the bookmarklet). [Bronco added that]
            $tags = (empty($_GET['tags']) ? '' : $_GET['tags'] ); // Get tags if it was provided in URL
            
            $private = (!empty($_GET['private']) && $_GET['private'] === "1" ? 1 : 0); // Get private if it was provided in URL 
            
            // handles the addnote param
            if (!empty($_GET['addnote'])){$private=1;$url='';$renderpage='addnote';$pagetitle='My notebook';}

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
        if (isset($_GET['source'])){$PAGE->assign('bkm_source','source=bookmarklet');}else{$PAGE->assign('bkm_source','');}
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('link',$link);
        $PAGE->assign('pagetitle',$pagetitle);
        $PAGE->assign('link_is_new',$link_is_new);
        $PAGE->assign('token',getToken()); // XSRF protection.
        $PAGE->assign('http_referer',(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''));
        $PAGE->renderPage($renderpage);
        exit;
    }



    // -------- User clicked the "Save" button when editing a link: Save link to database.
    if (isset($_POST['save_edit']))
    { 
        if (!tokenOk($_POST['token'])) die(e('Wrong token.',false)); // Go away !
        $tags = trim(preg_replace('/\s\s+/',' ', $_POST['lf_tags'])); // Remove multiple spaces.
        $linkdate=$_POST['lf_linkdate'];
        $url = trim($_POST['lf_url']);
        $imgurl = trim($_POST['lf_imgurl']);
        if (!startsWith($url,'http:') && !startsWith($url,'https:') && !startsWith($url,'ftp:') && !startsWith($url,'magnet:') && !startsWith($url,'?'))
            $url = 'http://'.$url;
        $link = array('title'=>trim($_POST['lf_title']),'url'=>$url,'urlimg'=>$imgurl,'description'=>trim($_POST['lf_description']),'private'=>(isset($_POST['lf_private']) ? 1 : 0),
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
?>
