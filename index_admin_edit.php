<?php
/**
* Основная страница
* @package Pilot
* @subpackage CMS
* @version 3.0
* @author Rudenko Ilya <rudenko@delta-x.com.ua>
* @copyright Delta-X, 2004
*/

/**
 * Определяем интерфейс для поддержки интернационализации
 * @ignore 
 */
define('CMS_INTERFACE', 'ADMIN');

/**
 * Конфигурация
 */
require_once('./system/config.inc.php');

// Аунтификация при  работе с запароленными разделами
new Auth(true);

/**
 * Типизируем переменные
 */
$id = globalVar($_GET['id'], '');
$table_id = globalVar($_GET['_table_id'], '');
$return_path = globalVar($_GET['_return_path'], '/Admin/');
$copy = globalVar($_GET['_copy'], false);

// Если для таблицы указан не её id, а имя, то опредляем id
if (!is_numeric($table_id)) {
	$query = "select id from cms_table where name='$table_id'";
	$table_id = $DB->result($query);
}

if (empty($table_id)) {
	// Не указан id таблицы, которую необходимо редактировать.
	Action::setError(cms_message('CMS', 'Не указан id таблицы, которую необходимо редактировать.'));
	header("Location: $return_path");
	exit;
}

// Определяем права пользователя на редактирование данной таблицы
if (Auth::updateTable($table_id)) {
	$TmplDesign = new Template(SITE_ROOT.'design/cms/table_update');
} elseif (Auth::selectTable($table_id)) {
	$TmplDesign = new Template(SITE_ROOT.'design/cms/table_select');
} else {
	trigger_error(cms_message('CMS', 'У Вас нет прав на редактирование таблицы "%s"', $table_id), E_USER_ERROR);
	exit;
}

/**
 * Высвечиваем сообщения о выполненной работе
 */
if (isset($_SESSION['ActionReturn']['log']) && !empty($_SESSION['ActionReturn']['log']) && is_array($_SESSION['ActionReturn']['log'])) {
	$TmplDesign->set('show_responce_log', true);
	reset($_SESSION['ActionReturn']['log']);
	while(list(, $message) = each($_SESSION['ActionReturn']['log'])) {
		$TmplDesign->iterate('/responce_log/', null, array('text' => $message));
	}
	
	unset($_SESSION['ActionReturn']['log']);
}
/**
 * Вывод сообщения об успешном выполнении
 */
if (isset($_SESSION['ActionReturn']['ok']) && !empty($_SESSION['ActionReturn']['ok'])) {
	reset($_SESSION['ActionReturn']['ok']);
	while (list(, $message) = each($_SESSION['ActionReturn']['ok'])) {
		$TmplDesign->iterate('/error/', null, array('message' => $message, 'type' => 'ok', 'title' => 'Ответ системы'));
	}
}
/**
 * Вывод сообщения об ошибке
 */
if (isset($_SESSION['ActionReturn']['error']) && !empty($_SESSION['ActionReturn']['error'])) {
	reset($_SESSION['ActionReturn']['error']);
	while (list(, $message) = each($_SESSION['ActionReturn']['error'])) {                
		$TmplDesign->iterate('/error/', null, array('message' => $message, 'type' => 'error', 'title' => 'Ошибка'));
	}
}
/**
 * Выводим сообщение типа Alert
 */
if (isset($_SESSION['ActionReturn']['alert']) && !empty($_SESSION['ActionReturn']['alert'])) {
	reset($_SESSION['ActionReturn']['alert']);
	while (list(, $message) = each($_SESSION['ActionReturn']['alert'])) {
		$TmplDesign->iterate('/error/', null, array('message' => $message, 'type' => 'alert', 'title' => 'Внимание!'));
	}
}

/**
 * Загружаем контент
 */
//ob_start();

$query = "
	select concat(tb_db.alias, '/', tb_table.name)
	from cms_table as tb_table 
	inner join cms_db as tb_db on tb_db.id=tb_table.db_id
	where tb_table.id='$table_id'
";
$filename = $DB->result($query);
if (is_file(CONTENT_ROOT."cms_table/$filename.inc.php")) {
	$content_file = CONTENT_ROOT."cms_table/$filename.inc.php";
} else {
	$content_file = CONTENT_ROOT."cms_table/edit.inc.php";
}

require_once($content_file);

/**
* Формирование HTML
*/
//$TmplDesign->set('content', ob_get_clean());
unset($_SESSION['ActionReturn']);
unset($_SESSION['ActionError']);
unset($_SESSION['cmsEditError']);

$TmplDesign->set('mktime', date(LANGUAGE_DATETIME));

$stat = '';
ob_start();
if (IS_DEVELOPER && DEBUG) {
	
//	$query = "SHOW PROFILES";
//	$data = $DB->query($query);
//	z($data);
	
	$counter = 0;
	do {
		$counter++;
		$sql = $DB->debug_show();
		if ($sql === false) {
			break;
		}
		$geshi = new GeSHi($sql, 'SQL');
		$geshi->set_header_type(GESHI_HEADER_DIV);
		$geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS); 
		$geshi->set_keyword_group_style(1, 'color: blue;', true); 
		$geshi->set_overall_style('color: blue;', true); 
		echo $geshi->parse_code(); 
	} while($counter < 100);
		
	$stat = ob_get_clean();
}
	
$dat = getrusage();
$utime_after = ($dat["ru_utime.tv_sec"] * 1000000 + $dat["ru_utime.tv_usec"]) / 1000000;
$stime_after = ($dat["ru_stime.tv_sec"] * 1000000 + $dat["ru_stime.tv_usec"]) / 1000000;

$stat .= '
<!-- 
';
if (isset($DB->statistic)) {
	$stat .= 'SQL: select:'.$DB->statistic['select'].'; ';
	$stat .= 'multi:'.$DB->statistic['multi'].'; ';
	$stat .= 'insert:'.$DB->statistic['insert'].'; ';
	$stat .= 'update:'.$DB->statistic['update'].'; ';
	$stat .= 'delete:'.$DB->statistic['delete'].'; ';
	$stat .= 'other:'.$DB->statistic['other'].'; ';
}
$stat .= '
Full time: '.round(getmicrotime() - TIME_TOTAL, 5).' sec
User time: '.round($utime_after - TIME_USER, 5).' sec
Sys  time: '.round($stime_after - TIME_SYSTEM, 5).' sec
-->';

$DB->close();

//echo mod_deflate($TmplDesign->display() .$stat);
exit; // установлено для того, что б блокировать вирусы, которые добавляют в конце файла iframe
?>