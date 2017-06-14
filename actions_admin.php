<?php
/**
* Обработка действий по изменению данных на сайте
* @package DeltaCMS
* @subpackage CMS
* @version 3.0
* @author Rudenko Ilya <rudenko@delta-x.com.ua>
* @copyright Delta-X, 2004
*/
ignore_user_abort(true);
 
/**
* Set language interface
* @ignore 
*/
define('CMS_INTERFACE', 'ADMIN');


/**
* Configuration
*/

require_once('system/config.inc.php');


if (isset($GLOBALS['HTTP_RAW_POST_DATA']) || isset($GLOBALS['JsHttpRequest']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') ) {
	define('AJAX', 1);
	$JsHttpRequest = new JsHttpRequest("utf8");
} else {
	define('AJAX', 0);
}

ob_start();

// Соединяемся с БД
$DB = DB::factory('default');
// Аунтификация при  работе с запароленными разделами
new Auth(true);

/**
 * Define type of variables
 */
$table_id = globalVar($_REQUEST['_table_id'], 0);
$add_more = globalVar($_POST['_add_more'], 0);
$no_refresh = globalVar($_POST['no_refresh'], 0);

/**
 * Фиксируем в COOKIE значение переменной "не обновлять", а также
 * указываем скриту куда возвращаться
 */
setcookie('no_refresh', $no_refresh, time() + 86400 * 30, '/', CMS_HOST);
if ($no_refresh) {
	$_REQUEST['_return_type'] = 'close';
}

/**
 * Фиксируем в COOKIE значение переменной "добавить еще", а также
 * указываем скриту куда возвращаться.
 */
setcookie('add_more', $add_more, time() + 86400 * 30, '/', CMS_HOST);
if ($add_more) {
	$_REQUEST['_return_path'] = $_REQUEST['_error_path'];
	$_REQUEST['_return_type'] = 'self';
}

/**
 * Сохраняет подробный лог изменений в системе
 */
if (CMS_EXTENDED_LOG) {
	Action::saveEventLog('admin');
}

/**
 * Определяем тип события
 */
if (!isset($_REQUEST['_event']) || empty($_REQUEST['_event'])) {
	Action::onError(cms_message('CMS', 'Не указано действие, которое необходимо выполнять.'));
} elseif (is_array($_REQUEST['_event'])) {
	$keys = array_keys($_REQUEST['_event']);
	/**
	 * Не ставить сюда reset, так как есть события по умолчанию, которые находятся в hidden полях.
	 * Эти события вызываются через JavaScript посредством выполнения Form.submit(), в основном это
	 * событие фильтрации таблицы в которой есть выпадающий список с вариантами, ниже находятся кнопки 
	 * с событиями, при клике по которым передаётся два параметра. Поэтому для нас важен последний параметр,
	 * который передан (значение кнопки, а не значение по умолчанию).
	 */
	$event = end($keys); // не ставить сюда reset
	unset($keys);
} else {
	$event = $_REQUEST['_event'];
	$event = preg_replace('~/$~', '', $event);
}

// Check event file for existance
if (!is_file(ACTIONS_ROOT.'admin/'.$event.'.act.php')) {
    
	Action::onError(cms_message('CMS', 'Не найден обработчик события %s', $event));
	
// Checking user permissions
} elseif (!Auth::actionEvent($event)) {
	Action::onError(cms_message('CMS', 'Доступ к %s событию - запрещен.', $event));
	
// Executing script
} else {
	Action::saveLog('Executing event admin/'.$event.'.act.php');
	require_once(ACTIONS_ROOT.'admin/'.$event.'.act.php');
}

/**
 * Подводим итоги выполнения скрипта
 */
Action::finish();

exit; // установлено для того, что б блокировать вирусы, которые добавляют в конце файла iframe
?>