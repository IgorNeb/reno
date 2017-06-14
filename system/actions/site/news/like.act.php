<?php

/**
 * Like / Dislike новости
 * @package Pilot
 * @subpackage News
 */
$id         = globalVar($_REQUEST['id'], 0);
$active     = globalVar($_REQUEST['active'], 0);
$table_name = 'news_message';

$ip = HTTP_IP;
if (Auth::isAdmin()) {
    $ip = '192.168.1.'.rand(1, 99);
}

$query = "SELECT COUNT(*) FROM `site_vote` WHERE `object_id` = '$id' AND `table`= '$table_name' AND `ip`='$ip'";
$total = $DB->result($query);

if ($total > 0) {
    $text = cmsMessage::get("MSG_COMMENT_VOTE_ALREADY");
    $_RESULT['javascript'] = "likeResult($id, '$text', 0, 0);";           
    exit;
}

$DB->update("UPDATE news_message SET `likes_up` = `likes_up` + 1 WHERE id='{$id}' ");

$text = cmsMessage::get("MSG_COMMENT_VOTE_THANK");
$_RESULT['javascript'] = "likeResult($id, '$text', 1, $active);";  
exit;