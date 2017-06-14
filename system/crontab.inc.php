<?php
/**
* Обработка всех файлов кронтаба
* @package DelatCMS
* @subpackage SDK
* @version 3.0
* @author Naumenko A.
* @copyright c-format, 2016
*/

ini_set('memory_limit', '512M');

// Определяем интерфейс
define('CMS_INTERFACE', 'ADMIN');

// Конфигурационный файл
require_once('config.inc.php');

//символ перевода строки
define('NL', !empty( $_SERVER['HTTP_USER_AGENT'] ) ? '<br/>' : "\n");

// Блокировка паралельного запуска скрипта
Shell::collision_catcher();
