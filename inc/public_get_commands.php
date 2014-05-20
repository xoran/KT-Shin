<?php 
// Public get commands: enhance here shaarli's capacities 
/*
if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=tools'))
    {
        // do something
        exit;// don't forget this line ;-)
    }

*/


    // -------- Added by Bronco to load only the links in the infinitescroll option
    if (isset($_SERVER["QUERY_STRING"]) && endswith($_SERVER["QUERY_STRING"],'load=ajax'))
    {
        $PAGE = new pageBuilder;
        buildLinkList($PAGE,$LINKSDB); 
        /* prepare the current page link (to avoid double loading page)
        $current_page_url= ( empty($_GET['page']) ? '' : '&page='.$_GET['page'] );
        $ajax_load=( empty($_GET['load']) ? '' : '&load='.$_GET['ajax'] );
        $searchterm= ( empty($_GET['searchterm']) ? '' : '&searchterm='.$_GET['searchterm'] );
        $searchtags= ( empty($_GET['searchtags']) ? '' : '&searchtags='.$_GET['searchtags'] );
       
        $current_page_url.=$searchterm.$searchtags.$ajax_load;


        $PAGE->assign('current_page_url',$current_page_url));*/
        $PAGE->renderPage('linklist.looplinks');
        exit;
    }


    // -------- Tag cloud
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
            $tagList[$key] = array('count'=>$value,'class'=>'size'.ceil($value/5),'size'=>max(ceil(40*$value/$maxcount),8));
            if ($value==1) {$tagList[$key]['class'] = 'size0';}
        }
        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('token',getToken()); // XSRF protection for del buttons (used only if logged as admin)
        $PAGE->assign('tags',$tagList);
        $PAGE->renderPage('tagcloud');
        exit;
    }
?>
