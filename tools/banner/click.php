﻿<?php
/**
* Обработчик кликов по баннерам
*
* @package Pilot
* @subpackage Banner
* @version 3.0
* @author Eugen Golubenko <eugen@delta-x.com.ua>
* @copyright Copyright 2006, Delta-X ltd.
*/

/**
* Определяем интерфейс для поддержки интернационализации
* @ignore
*/
define('CMS_INTERFACE', 'SITE');

/**
* Конфигурационный файл
*/
require_once('../../system/config.inc.php');

$DB = DB::factory('default');

$banner_id = globalVar($_GET['id'], 0);
$lang = globalVar($_GET['_language'], '');
$lang_url = ( empty($lang) ) ? '' : '/'.$lang;

// Сохраняем статистику
$fp = fopen(LOGS_ROOT.'banner_click.log', 'a');
flock($fp, LOCK_EX);
fwrite($fp, date('Y-m-d H:i:s')."\t".HTTP_IP."\t".HTTP_LOCAL_IP."\t".$banner_id."\t".Auth::getUserId()."\n");
flock($fp, LOCK_UN);
fclose($fp);

// Определяем ссылку с баннера
$url   = $DB->result( "SELECT REPLACE(link, 'http://" . CMS_HOST . "/', '/' ) as link FROM banner_banner WHERE id = '$banner_id'" );
if(empty($url)){
	header("Location: http://" . CMS_HOST . "/");
	exit;
}

$ishttp = strpos($url, 'http');    
if ( $ishttp === false) {       
   header("Location: ". $lang_url . $url );
}
else { 
    header("Location: ". $url);
}

exit;
?>