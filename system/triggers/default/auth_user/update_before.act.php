<?php
/**
 * После внесения администратором изменений в настройки пользователя отключаем
 * его из системы, для того, что б он залогинился заново
 * когда пользователь сам меняет свои параметры, то делать этого не надо, так как
 * пользователь не может изменить свои привилегии
 */
if ($this->NEW != $this->OLD) {
	$DB->delete("delete from auth_online where user_id='".$this->NEW['id']."'");
	
	// Послать пользователю уведомление, если администратор проверил его аккаунт
//	if ($this->OLD['checked'] != 1 && $this->NEW['checked'] == 1) {
//		$TmplMail = new TemplateDB('cms_mail_template', 'user', 'checked_notify', LANGUAGE_SITE_DEFAULT);
//		$Sendmail = new Sendmail(CMS_MAIL_ID, cms_message('CMS', 'Ваш аккаунт был проверен администратором'), $TmplMail->display());
//		$Sendmail->send($this->NEW['email']);
//	}
}

$this->NEW['login'] = $this->NEW['email'];
if ($this->OLD['group_id'] != 5 && $this->NEW['group_id'] == 5) {
    $this->NEW['group_id'] = 31;
}
require($this->triggers_root . "insert_after.act.php");


?>