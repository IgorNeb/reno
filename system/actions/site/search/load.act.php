<?php

/* 
 * Поиск
 */

$page    = globalVar($_REQUEST['page'], 0);
$text = globalVar($_REQUEST['param'], '');

if (empty($page) || empty($text)) {
    exit();
}

$lang_url = ( LANGUAGE_CURRENT !== LANGUAGE_SITE_DEFAULT ) ? '/' . LANGUAGE_CURRENT : '';

$data = Search::searchSite($text, 0, SEARCH_COUNT_ROWS, $page);
$total = $DB->result("SELECT found_rows()");

$Template = new Template("search/list");
reset($data);
while (list(, $row) = each($data)) {
    $row['url'] = $lang_url . $row['url'];
	$Template->iterate('/search_result/', null, $row);
}      
$_RESULT['modal_form'] = $Template->display();

if (SEARCH_COUNT_ROWS * $page < $total) {
    $page++;
    
} else {
    $page = 0;
}

$_RESULT['javascript'] = "loadMoreDataResult({$page});";