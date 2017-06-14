<?php

/**
 * Зациклинность редиректов
 * @package Pilot
 * @subpackage Seo
 * @author Naumenko A.
 * @copyright c-format, 2014
 */

$url = globalVar($_GET['url'], '');
$_return_path = globalVar($_GET['_return_path'], '/admin/');

if(empty($url) ){
    Action::onError("Ошибка: не все данные указаны");
    exit;
}

$query = "SELECT 
                   tb_redirect.id,
                   tb_redirect.url_old,
                   tb_redirect.url_new
            FROM `site_structure_redirect` as tb_redirect            
            WHERE tb_redirect.url_old='$url' or tb_redirect.url_new='$url'
    ";
$data = $DB->query_row($query);
//x($data);

if(count($data)==0){
    Action::onError("Не найдено совпадений");
    exit;
}

$next_link = $DB->query_row("select `id`, `url_new` from `site_structure_redirect` WHERE `url_old`='{$data['url_new']}' ");

if(count($next_link)){   
    $DB->delete("DELETE FROM `site_structure_redirect` WHERE id='{$next_link['id']}' ");
    $query = "
            UPDATE `site_structure_redirect` SET `url_new` = '{$next_link['url_new']}' 
            WHERE `id`='{$data['id']}'        
                 
    ";
   $DB->query($query);  
 }
 else{
     $next_link = $DB->query_row("select `id`, `url_new` from `site_structure_redirect` WHERE `url_new`='{$data['url_new']}' ");
     if(count($next_link)){
         $query = "
            UPDATE `site_structure_redirect` SET `url_new` = '{$data['url_new']}' 
            WHERE `id`='{$next_link['id']}'        
                 
            ";
         $DB->query($query);
         $DB->delete("DELETE FROM `site_structure_redirect` WHERE id='{$data['id']}' ");
     }
     else{

        Action::onError("Не найдено совпадений");
        exit;

     }
 }
 
Action::onError("Обновлено");
exit;
