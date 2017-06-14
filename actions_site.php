<?php
/**
* Обработка действий по изменению данных на сайте пользователем
* @package Pilot
* @subpackage CMS
* @version 3.0
* @author Rudenko Ilya <rudenko@delta-x.com.ua>
* @copyright Delta-X, 2004
*/

ignore_user_abort(true);

/**
* Определяем интерфейс для поддержки интернационализации
* @ignore 
*/
define('CMS_INTERFACE', 'SITE');
/**
* Конфигурация системы
*/
require_once('system/config.inc.php');
if (isset($GLOBALS['HTTP_RAW_POST_DATA']) || isset($GLOBALS['JsHttpRequest']) || 
    ( isset($GLOBALS['_SERVER']['HTTP_X_REQUESTED_WITH']) && ($GLOBALS['_SERVER']['HTTP_X_REQUESTED_WITH'])=="XMLHttpRequest") ) {
	define('AJAX', 1);
	$JsHttpRequest = new JsHttpRequest("utf-8");
} else {
	define('AJAX', 0);
}

/**
 * Сохраняет подробный лог изменений в системе
 */
if (CMS_EXTENDED_LOG) {
	Action::saveEventLog('site'); 
}

$event = globalVar($_REQUEST['_event'], '');
$event = preg_replace('~/$~', '', $event);

if (!isset($_SESSION)) {
	session_start();
}

/**
* Чистим сессию от старых сообщений
*/
unset($_SESSION['ActionReturn']);
unset($_SESSION['ActionError']);

if (empty($event)) {
	Action::setError(cms_message('CMS', 'Не указано действие, которое необходимо выполнять.'));
	Action::onError();
} 

//if (!isset($_REQUEST['_language']) || empty($_REQUEST['_language'])) {
//	Action::setWarning(cms_message('CMS', 'Не передан обязательный параметр _language')); 
//}
if (is_file(ACTIONS_ROOT.'site/'.$event.'.act.php')) {
	require_once(ACTIONS_ROOT.'site/'.$event.'.act.php');
} else {
	Action::setError(cms_message('CMS', 'Не найден обработчик события %s', ACTIONS_ROOT.'site/'.$event.'.act.php'));
	Action::onError();
}

Action::finish();

exit; // установлено для того, что б блокировать вирусы, которые добавляют в конце файла iframe
?>