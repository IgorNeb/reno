<?php
/**
 * проверка header
 * 
 * @package DeltaCMS
 * @subpackage Shop
 * @copyright c-format, 2016
 * @cron каждые 30 минут
 */

// загрузка конфигураций для задача крона  
chdir(dirname(__FILE__));
require_once('../../crontab.inc.php');

$host = CMS_HOST;
$langURL = '';
$today = date("Y-m-d");

//checking pages status
$query = "
    SELECT
        id,
        IF(table_id = '2926', CONCAT(url, '.html'), CONCAT(url, '/')) as url
    FROM seo_structure
    WHERE (DAY(NOW()) <> DAY(check_time) OR p_status = 0 OR check_time IS NULL) AND url LIKE '$host%'
    ORDER BY check_time ASC
    LIMIT 100
";
$data = $DB->query($query);

if (empty($data)) {
    echo '[I] not links for checking ' . NL;
}
foreach ($data as $key => $row) {
    
    if (empty($row['url'])) {
        $DB->update("UPDATE seo_structure SET p_status = '0', check_time = '$today' WHERE id = '{$row['id']}'");
        continue;
    }
//    
//    $row['url'] = substr($row['url'], strpos($row['url'], '/', 1));
//    $row['url'] = trim($row['url'], '/');
//    
    $url = "https://" . $row["url"];
    
    $headers = get_headers($url);
    
    if (is_array($headers)) {
        foreach ($headers as $k => $v) {           
            if (substr_count($v, 'HTTP') > 0) {
                $response = explode(' ', $v);
                if (isset($response[1])) {
                    $DB->update("UPDATE seo_structure SET p_status = '" . $response[1] ."', check_time = '$today' WHERE id = '{$row['id']}'");
                }
                break;
            }
        }
    } else {
        echo '[E] error get headers ' . $url . NL;
    }
}
//end checking pages status
