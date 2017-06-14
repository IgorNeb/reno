<?php

/* 
 * Отправка пароля повторно
 */
$email = globalVar($_REQUEST['email'], '');

if (!preg_match(VALID_EMAIL, $email)) {	
	$_RESULT['reminder__error'] = cmsMessage::get("MSG_USER_ERROR_EMAIL");
    $_RESULT['javascript'] = "$('#reminder .remind_email').parent().addClass('error');$('#reminder .btn-form').show();";
    exit();
}

$query = "SELECT id FROM auth_user WHERE email='$email'";
$id = $DB->result($query);

if (empty($id)) {
    $_RESULT['reminder__error'] = cmsMessage::get("MSG_USER_REMINDER_ERROR");
    $_RESULT['javascript'] = "$('#reminder .btn-form').show();";
    exit();
}

$user_password = gen_password(8);
$DB->update("UPDATE auth_user SET passwd = '".md5($user_password)."' WHERE id='$id'");

require_once(ACTIONS_ROOT.'site/user/send_code.act.php');
  
$_RESULT['reminder'] = "<h3>Вам відправлено лист з новим паролем</h3>";
exit();