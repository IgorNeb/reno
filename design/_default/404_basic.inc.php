<?php
/** 
 * Обработчик шаблона для сайта, используемый по умолчанию.
 * Данный шаблон используется как "скелет" для нового дизайна сайта.
 * require_once этого щаблона - запрещено!
 * 
 * @package Pilot
 * @subpackage Site
 * @author Naumenko A.
 * @copyright c-format, 2017
 */ 

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Expires: -1");
header("Last-Modified: " . gmdate("D, d M Y H:i:s")." GMT");
header('Cache-Control: no-store, no-cache, must-revalidate');
header("Cache-Control: post-check=0,pre-check=0", false);
header("Cache-Control: max-age=0", false);
header('Pragma: no-cache');

if (!defined('DESIGN_URL')) {
    define('DESIGN_URL', '/design/renault/');
} 
/* Путь к странице */
//$TmplDesign->set("breacrumbs",   $Site->getTemplatePath());
//$TmplDesign->set("isMain",       $isMainPage);
$TmplDesign->set("structure_id", $Site->structure_id);

$langs = $Site->getMenuLanguage();
$TmplDesign->set("count_language", count($langs)); //ссылки на языковые версии
$TmplDesign->iterateArray("/language/", null, $langs); //ссылки на языковые версии
$TmplDesign->iterateArray("/socbutton/", null, $Site->getSocButton());



