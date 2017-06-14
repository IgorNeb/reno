<?php
/**
 * @package DeltaCMS
 * @subpackage Site
 * @author Naumenko A.
 * @copyright (c) 2017, c-format
 */
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Expires: -1");
header("Last-Modified: " . gmdate("D, d M Y H:i:s")." GMT");
header('Cache-Control: no-store, no-cache, must-revalidate');
header("Cache-Control: post-check=0,pre-check=0", false);
header("Cache-Control: max-age=0", false);
header('Pragma: no-cache');


define('DESIGN_URL', '/design/renault/');

$isMainPage = $Site->isMainPage();
    
/* Путь к странице */
//$TmplDesign->set("breacrumbs",   $Site->getTemplatePath());
$TmplDesign->set("breacrumbs",   '');
//$TmplDesign->set("headline",   '');
$TmplDesign->set("isMain",       $isMainPage);
$TmplDesign->set("structure_id", $Site->structure_id);

//$langs = $Site->getMenuLanguage();

//соц сети
$TmplDesign->iterateArray("/socbutton/", null, $Site->getSocButton());
     
//верхнего меню
$topMenu = $Site->getMenu(-1, 'top_menu', 1);
$TmplDesign->iterateArray('/topMenu/', null, $topMenu);

//for ajax
$_SESSION['is_mobile'] = (int)IS_MOBILE;
