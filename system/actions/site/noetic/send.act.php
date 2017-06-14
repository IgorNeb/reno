<?php

/* 
 * Интелектуальный подбор авто. Отправка данных
 * @package DeltaCMS
 * @subpackage Noetic
 * @author Nauemnko A.
 * @copyright (c) 2015, c-format
 */

$form_data = globalVar($_REQUEST['form'], array());

//Добавляем ссылки в админ панель на статистику пользователя
$session_id      = session_id(); 
$ids = $DB->fetch_column("SELECT id FROM `noetic_log_selection` WHERE session_id = '$session_id' ");
$text = '';
foreach($ids as $id){
    $link  = "http://".CMS_HOST."/admin/noetic/statistic/?user_id=".$id;
    $text .= "<a href='$link'>перейти</a><br/>";
}

$form_data['session_id'] = $text;
$_REQUEST['form'] = $form_data;

require_once( ACTIONS_ROOT .'site/form/send.act.php' );
