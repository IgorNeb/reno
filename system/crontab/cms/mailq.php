<?php
/** 
 * Рассылка писем, стоящих в очереди 
 * @package DeltaCMS
 * @subpackage CMS 
 * @author Eugen Golubenko
 * @copyright c-format
 * @сrontab ~/5 * * * * 
 */ 

chdir(dirname(__FILE__));
require_once('../../crontab.inc.php');

$message = $DB->fetch_column("
	SELECT id, recipient 
	FROM cms_mail_queue 
	WHERE delivery = 'wait' AND DATE(create_dtime) = current_date()
	ORDER BY id DESC
", 'id', 'recipient');

$counter = 0;
echo "[i] Start Mailq. ".count($message)." new messages found." . NL; 

reset($message);
while (list($message_id, $recipient) = each($message)) {
	$result = @Sendmail::delivery($message_id);
	$counter++; 
	
	if (empty($result)) {
		echo "[i] $counter\t Message (ID:$message_id) to $recipient : failed!!!" . NL;
	} else {
		echo "[i] $counter\t Message (ID:$message_id) to $result[recipient] : $result[delivery] ($result[status_message])" . NL;
	} 
} 
?>