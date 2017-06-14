<?php
/**
* Сортировка галлереи
* @package Pilot
* @version 3.0
* @author Naumenko A.
* @copyright Copyright 2014, c-format, 2014
*/

$sort = globalVar($_REQUEST['priority_list'], array());
$start = globalVar($_REQUEST['_start_row'], 0); // номер ряда с которого начинается сортировка


if ( empty($sort) ) {
	Action::setError('Не все данные указаны');
	exit;
}
 
$query = "
	UPDATE `gallery_photo`
	SET priority=FIND_IN_SET(id, '".implode(",", $sort)."')+".$start." 
	WHERE id IN (0".implode(",", $sort).")
";
$DB->update($query);

//удаление данных с кеша
$Cache = new CacheSql('site_service_gallery');
$Cache->delete();
//$Cache = new CacheSql('shop_product');
//$Cache->delete();

Action::setOk("Сохранено");
exit;
?>