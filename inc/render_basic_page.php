<?php 
// Here is the basic rendering

    $PAGE = new pageBuilder;
    $PAGE->assign('linkcount',count($LINKSDB));
    // added by Bronco
    $PAGE->assign('query_string',$_SERVER["QUERY_STRING"]);
    if (isset($_GET['searchtags'])){
        $PAGE->assign('class',$_GET['searchtags']);
        $PAGE->assign('pagetitle',$_GET['searchtags']);
        if (strpos($_GET['searchtags'],'mynotebook')!==false){$PAGE->assign('notebook','mynotebook');}else{$PAGE->assign('notebook','');}
    }else{$PAGE->assign('class','home');$PAGE->assign('notebook','');}
    $PAGE->assign('nbpages',ceil(count($LINKSDB)/$GLOBALS['config']['LINKS_PER_PAGE']));
    // ---------------   
    buildLinkList($PAGE,$LINKSDB); // Compute list of links to display

     /*if (isset($_GET['searchterm'])){
        // pour l'instant je cherche comment faire pour highlighter les termes et donc modifier le tableau
        // Accès à $PAGE->links alors que tpl est private... fuck
        exit('pouf');highLightSearchTerms($linkarray,$searchterm);
    }*/

    
    if (isset($_GET['addnote'])){$PAGE->assign('edit_link','edit_note'); }else{$PAGE->assign('edit_link','edit_link');}
    $PAGE->renderPage('linklist');
    exit;
?>
