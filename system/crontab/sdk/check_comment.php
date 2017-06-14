<?php
/**
 * Проверяем правильность написания комментариев к коду
 * @package DeltaCMS
 * @subpackage SDK
 * @author Dima Markovskiy
 * @copyright Delta-X, ltd. 2010 
 */

// загрузка конфигураций для задача крона  
chdir(dirname(__FILE__));
require_once('../../crontab.inc.php');

$query = "CREATE TABLE IF NOT EXISTS `cms_php_comment` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`path` VARCHAR(250) NOT NULL,
	`description` TEXT NULL,
	`package` VARCHAR(250) NULL DEFAULT NULL,
	`subpackage` VARCHAR(250) NULL DEFAULT NULL,
	`author` VARCHAR(250) NULL DEFAULT NULL,
	`copyright` VARCHAR(250) NULL DEFAULT NULL,
	`crontab` VARCHAR(250) NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
    )
    COLLATE='utf8_general_ci'
    ENGINE=MyISAM
    AUTO_INCREMENT=1;";
$DB->query($query);

$query = "TRUNCATE TABLE cms_php_comment";
$DB->delete($query);

$directory = array(SITE_ROOT);

$notneed = array(
	UPLOADS_ROOT, 
	CACHE_ROOT, 
	CVS_ROOT, 
	SITE_ROOT.'tmp/', 
	SITE_ROOT.'system/libs/excel_classes/', 
	SITE_ROOT.'system/libs/mime/', 
	SITE_ROOT.'system/libs/db/',
	SITE_ROOT.'js/shared/',
	SITE_ROOT.'system/triggers/',
	SITE_ROOT.'content/',
	SITE_ROOT.'design/',
	SITE_ROOT.'extras/',
);

reset($directory);
while (list(,$dir) = each($directory)) {
	$dircontent = Filesystem::getDirContent($dir, true, true, true);
	reset($dircontent);
	while (list(, $item) = each($dircontent)) {
		if(is_dir($item) && !in_array($item, $notneed)) {
			array_push($directory, $item);
            
		} elseif (is_file($item) && file_exists($item) ) {
			$fileinfo = pathinfo($item);
			if(!isset($fileinfo['extension']) || $fileinfo['extension'] != 'php') {
                continue;
            }
            
			//echo "[i] work with file: $item" . NL;
			$info = array(
				'path' => Uploads::getURL($item),
				'description' => '',
				'package' => '',
				'subpackage' => '',
				'author' => '',
				'copyright' => '',
				'crontab' => '' 
			);
			
			$data = file_get_contents($item);
			/**
			 * Получаем содержимое файла
			 */
			preg_match('/^<\?php(.+)\*\//ismU', $data, $matches);
			if(!empty($matches[1])) {
				
				/**
				 * Собираем информацию
				 */
				preg_match('/^\/\*\*(.+)@/imsU', $matches[1], $desc);
				if(!empty($desc[1])) {
					$info['description'] = trim(preg_replace(array('/\*/', '/\n+/', '/\s+/'), array('', '', ' '), $desc[1]));
				}
				
				preg_match('/@package\s+(.+)$/imsU', $matches[1], $pack);
				if(!empty($pack[1])) {
					$info['package'] = $pack[1]; 
				}
				
				preg_match('/@subpackage\s+(.+)$/imsU', $matches[1], $subpack);
				if(!empty($subpack[1])) {
					$info['subpackage'] = $subpack[1];
				}
				
				preg_match('/@author\s+(.+)$/imsU', $matches[1], $author);
				if(!empty($author[1])) {
					$info['author'] = $author[1];
				}
				
				preg_match('/@copyright\s+(.+)$/imsU', $matches[1], $copy);
				if(!empty($copy[1])) {
					$info['copyright'] = $copy[1];
				}
				
				preg_match('/@crontab\s+(.+)$/imsU', $matches[1], $cron);
				if(!empty($cron[1])) {
					$info['cron'] = $cron[1];
				}
			}
            
            if (empty($info['author']) || empty($info['description']) || empty($info['copyright'])) {
               // echo 'Wrong file comments ' . $info['path'] . NL;
            }
			
			$query = "
				insert into cms_php_comment
				set
					path = '".trim($info['path'])."',
					description = '".$DB->escape($info['description'])."',
					package = '".trim($info['package'])."',
					subpackage = '".trim($info['subpackage'])."',
					author = '".$DB->escape($info['author'])."',
					copyright = '".trim($info['copyright'])."',
					crontab = '".$DB->escape($info['crontab'])."'
			";
			
			$DB->insert($query);
			
		} else {
			echo "[i] Skip dir $item" . NL;
		}
		
	}

}

?>