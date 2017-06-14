<?php
/** 
 * Парсер ошибок
 * @package DeltaCMS
 * @subpackage CMS
 * @author Miha Barin 
 * @copyright c-format
 * @cron ~/30 * * * * 
 */

// загрузка конфигураций для задача крона
chdir(dirname(__FILE__));
require_once('../../crontab.inc.php');

/**
* Функция разбора контента ошибки на структуризированый массив данных
* @param string $content
* @return array
*/
function parseError($content)
{
	global $authors;
	
	$result = array();
	preg_match("/Date:[\s]+(.*)URL:/", $content, $date);
	preg_match("/URL:[\s]+(.*)IP:/", $content, $url);
	preg_match("/IP:[\s]+(.*)File:/", $content, $ip);
	preg_match("/File:[\s]+(.*)Mtime:/", $content, $file);
	preg_match("/Mtime:[\s]+(.*)Line:/", $content, $mtime);
	preg_match("/Line:[\s]+(.*)Type:/", $content, $line);
	preg_match("/Type:[\s]+(.*)Refferer:/", $content, $type);
	preg_match("/Refferer:[\s]+(.*)UserAgent:/", $content, $refferer);
	preg_match("/UserAgent:[\s]+(.*)Message:/", $content, $user_agent);
	
	$result['date'] 	  = addslashes(trim($date[1], '<br/>'));
	$result['url'] 		  = addslashes(trim($url[1], '<br/>'));
	$result['ip'] 		  = addslashes(trim($ip[1], '<br/>'));
	$result['file'] 	  = addslashes(trim($file[1], '<br/>'));
	$result['mtime'] 	  = addslashes(trim($mtime[1], '<br/>'));
	$result['line'] 	  = addslashes(trim($line[1], '<br/>'));
	$result['type'] 	  = addslashes(trim($type[1], '<br/>'));
	$result['refferer']   = addslashes(trim($refferer[1], '<br/>'));    
	$result['user_agent'] = addslashes(trim($user_agent[1], '<br/>'));
	
	// обрабатываем многострочные параметры
	$proc_pos = strpos($content, "Process");
	$mess_pos = strpos($content, "Message");
	$mess_len = strlen($content) - (strlen(substr($content, 0, $mess_pos)) + strlen("Message:") + strlen(substr($content, $proc_pos)));
	
	$result['message'] 	= addslashes(trim(substr($content, $mess_pos+strlen("Message:"), $mess_len)));
	$result['process'] 	= addslashes(trim(substr($content, $proc_pos+strlen("Process:"))));
	
	$file = $result['file'];
	
	// Игнорируем файлы, которые были изменены с момента ошибки
	if (is_file(SITE_ROOT.$file)) {
		$stat = stat(SITE_ROOT.$file);
		if ($stat['mtime'] > $result['mtime']) {
			echo "[i] Skip error because file was modified since last error (".date('Y-m-d H:i:s', $stat['mtime'])." > ".date('Y-m-d H:i:s', $result['mtime']).")\n";
			return array();
		}
	}
	
	// вытягиваем автора файла
	if(!isset($authors[$file])){
		$file_content = @file_get_contents(SITE_ROOT . $file);       
		$authors[$file] = (!empty($file_content) && preg_match('/@author\s+(.+)$/imsU', $file_content, $author)) ? $author[1] : 'unknown';
	} 
	 
	$result['author'] = (!empty($authors[$file])) ? $authors[$file] : "unknown";
	return $result;
}
  

/****************************************************************************************/
/*                                     SCRIPT START                                     */
/****************************************************************************************/


//$oldfilename = LOGS_ROOT."error.log"; 
//$filename    = LOGS_ROOT."error.".uniqid().".php";

$oldfilename = LOGS_ROOT."error.log"; 
$filename    = LOGS_ROOT."error.log";


if(!file_exists($oldfilename)){
	echo "[i] Done" . NL;
	exit; 	
}

//rename($oldfilename, $filename);
$fp = @fopen($filename, 'r');
 
if(!$fp){
	echo "[i] Done" . NL;
	exit; 		
}

$authors = array();
$insert  = array();
$numline = 0;
$content = "";

while ($line = fgets($fp)) {
	$line = trim($line); 
	//echo "[i] parsing $numline --$line-- \n";
	$numline++;
	
	// если строка пустая - берем следующую
	if($line == ""){
		//echo "[i] Skip empty line" . NL;
		continue;
	}
	
	// если встретили начало записи об ошибке - обнуляем предыдущую запись
	if(strpos($line, "[BEGIN]") === 0){
		//echo "[i] Error message start" . NL;
		$content = '';
		continue;
	}
	
	// если встретили конец записи об ошибке - отправляем запись в функцию-парсер
	if(strpos($line, "[END]") === 0){
		//echo "[i] Error message end" . NL;
		
		$record = parseError($content);
		if(empty($record)) {
            continue;
        }
		
		$insert[] 	= "('$record[date]', '$record[url]', '$record[ip]', '$record[file]', from_unixtime('$record[mtime]'), '$record[author]', '$record[line]', '$record[type]', '$record[refferer]', '$record[user_agent]', '$record[message]', '$record[process]', 1)";
	
		// если накопилось больше ста записей - сохраняем их в БД
		if (count($insert) > 100) {
			
	    	$query = "
	    		INSERT INTO cms_log_error (date, url, ip, file, mtime, author, line, type, refferer, user_agent, message, process, count) 
	    		VALUES ".implode(",", $insert)." 
	    		ON DUPLICATE KEY UPDATE  
					refferer=VALUES(refferer),
					user_agent=VALUES(user_agent),
					count = count + 1     
	    	";
	    	$DB->insert($query);
	    	$insert = array();
    	}
    	
    	continue;
	}	
	
	$content .= $line . NL;	
}

fclose($fp);  
unlink($filename);

// сохраняем в БД оставшиеся данные
if(!empty($insert)){
	$query = "
		INSERT INTO cms_log_error (date, url, ip, file, mtime, author, line, type, refferer, user_agent, message, process, count)    
		VALUES ".implode(",", $insert)." 
		ON DUPLICATE KEY UPDATE 
			refferer = VALUES(refferer),
			user_agent = VALUES(user_agent),
		 	count = count + 1
	";
	$DB->insert($query);
}

// Удаляем сообщения, которые происходили до изменения файла
$query = "select file from cms_log_error group by file";
$data = $DB->query($query);
reset($data);
while (list(,$row) = each($data)) {
	if (!is_file(SITE_ROOT.$row['file'])) continue;
	
	$stat = stat(SITE_ROOT.$row['file']);
	$DB->delete("delete from cms_log_error where file='$row[file]' and mtime < from_unixtime('$stat[mtime]')");
	echo "[i] Delete $row[file] - $DB->affected_rows rows" . NL;
}


echo "[i] Done" . NL;


?>
