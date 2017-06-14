<?php
/** 
 * Загрузка новостей
 * @package DeltaCMS
 * @subpackage News
 */

$type_id = globalVar($_REQUEST['param'], '');
$page    = globalVar($_REQUEST['page'], 0);
$per_page    = globalVar($_REQUEST['per_page'], 0);

$type_id = (int)$type_id;

if (empty($per_page)) {
    $per_page = NEWS_PAGE_COUNT;
}

$News = new News();
$data = $News->getHeadlines($per_page, $type_id, true, null, $page);

if (count($data)) {
    
    $parents = $DB->fetch_column("SELECT parent FROM news_type_relation WHERE id='$type_id'");
    
    $Template = new Template('news/block');
    $Template->set('type', (in_array(205, $parents) ? 'events' : 'news'));
    $Template->iterateArray('/news/', null, $data);

    $_RESULT['modal_form'] = $Template->display();
    
    $already = $page * $per_page;
    
    if ($already < $News->total) {
        $page++;

    } else {
        $page = 0;
    }
} else {
    $page = 0;
}

$_RESULT['javascript'] = "loadMoreDataResult({$page});";