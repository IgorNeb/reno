<?php
/** 
 * Обновление информации о структуре БД
 * @package Pilot 
 * @subpackage CMS
 * @author Rudenko Ilya <rudenko@delta-x.com.ua> 
 * @copyright Delta-X, ltd. 2007
 */ 

$db_alias = globalVar($_REQUEST['db_alias'], '');
if (empty($db_alias)) {
    $db_alias = 'default';
} 
$db_id = $DB->result("SELECT id FROM cms_db WHERE alias = '$db_alias'");

$cmsDB = new cmsDB($db_id);
$cmsDB->updateDB();
$cmsDB->buildTableStatic();
$cmsDB->buildFieldStatic();
$cmsDB->checkAllTables();
?>