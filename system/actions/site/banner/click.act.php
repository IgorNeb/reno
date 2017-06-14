<?php
/**
* Обработчик кликов по баннерам
*
* @package DaltaCMS
* @subpackage Banner
* @version 3.0
* @copyright Copyright 2016, c-format
*/
$banner_id = globalVar($_REQUEST['id'], 0);

// Сохраняем статистику
$fp = fopen(LOGS_ROOT.'banner_click.log', 'a');
flock($fp, LOCK_EX);
fwrite($fp, date('Y-m-d H:i:s')."\t".HTTP_IP."\t".HTTP_LOCAL_IP."\t".$banner_id."\t" . Auth::getUserId() . "\n");
flock($fp, LOCK_UN);
fclose($fp);
exit;
