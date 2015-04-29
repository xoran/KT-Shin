<?php
// Private get commands: enhance here shaarli's capacities for the admin
// only included for the admin ^^
/*
if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=tools'))
    {
        // do something
        exit;// don't forget this line ;-)
    }

*/


    // -------- User clicked the "EDIT note" button on a link: Display link edit form.
    if (isset($_GET['editmynotebook_link']))
    {
        $link = $LINKSDB[$_GET['editmynotebook_link']];  // Read database
        if (!$link) { header('Location: ?'); exit; } // Link not found in database.
        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('link',$link);
        $PAGE->assign('link_is_new',false);
        if (strpos($link['tags'],'mynotebook')){ $PAGE->assign('mynotebook_tag','mynotebook');}else{ $PAGE->assign('mynotebook_tag','');}
        $PAGE->assign('notebook','');
        $PAGE->assign('token',getToken()); // XSRF protection.
        $PAGE->assign('http_referer',(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''));
        list($timezone_form,$timezone_js) = templateTZform($GLOBALS['timezone']);
        $PAGE->assign('timezone_js',$timezone_js);
        $PAGE->renderPage('addnote');
        exit;
    }
    // -------- User clicked the "EDIT" button on a link: Display link edit form.
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
        list($timezone_form,$timezone_js) = templateTZform($GLOBALS['timezone']);
        $PAGE->assign('timezone_js',$timezone_js);
        $PAGE->renderPage('editlink');
        exit;
    }
    // -------- User clicked the "EDIT" button on a note: Display note edit form.
    if (isset($_GET['edit_note']))
    {
        $link = $LINKSDB[$_GET['edit_note']];  // Read database
        if (!$link) { header('Location: ?'); exit; } // Link not found in database.
        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('link',$link);
        $PAGE->assign('link_is_new',false);
        $PAGE->assign('token',getToken()); // XSRF protection.
        $PAGE->assign('http_referer',(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''));
        $PAGE->renderPage('addnote');
        exit;
    }

    // -------- User wants to delete a tag from tag cloud
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=deltag'))
    {
        if (!tokenOk($_POST['token'])) die(e('Wrong token.',false));
        // Delete a tag:
        if (!empty($_POST))
        {
            $needle=array_flip($_POST);$needle=trim($needle['X']);
            $linksToAlter = $LINKSDB->filterTags($needle,true); // true for case-sensitive tag search.
            foreach($linksToAlter as $key=>$value)
            {
                $tags = explode(' ',trim($value['tags']));
                unset($tags[array_search($needle,$tags)]); // Remove tag.
                $value['tags']=trim(implode(' ',$tags));
                $LINKSDB[$key]=$value;
            }
            $LINKSDB->savedb(); // save to disk
            header('location: index.php?do=tagcloud');
            exit;
        }
    }
?>
