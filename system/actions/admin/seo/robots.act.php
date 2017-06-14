<?php

/**
 * Обновление файла robots.txt
 * 
 * @package DeltaCMS
 * @subpackage Seo
 * @author Naumenko A.
 * @copyright c-format, 2016
 */

$text = globalVar($_REQUEST['text'], '');
 
$filename = SITE_ROOT . 'robots.txt';
    
if (!file_exists($filename) || !is_writable($filename)) {
    Action::onError('Не возможно сохранить файл');
}


$fp = fopen($filename, 'w');

fwrite($fp, trim($text));

fclose($fp);

 Action::setOk('Файл успешно сохранен');