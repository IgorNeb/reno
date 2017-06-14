<?php
/**
* Подтверждение регистрации пользователя
* @package DeltaCMS
* @subpackage User
* @version 3.0
*/

$code = globalVar($_GET['code'], '');

if (!preg_match('/^[a-z0-9]{32}$/', $code)) {
	Action::onError(cmsMessage::get('MSG_USER_CONFIRMCODE_WRONG'));
}

$query = "
	SELECT *
	FROM auth_user
	WHERE confirmation_code = '$code'
";
$user = $DB->query_row($query);
if ($DB->rows == 0) {
	
	// Неправильный код подтверждения
	Action::onError(cmsMessage::get('MSG_USER_CONFIRMCODE_WRONG'));
	
} elseif ($user['confirmed']) {
	
	// Пользователь уже активировал аккаунт, и делает это еще раз
    Action::onError(cmsMessage::get('MSG_USER_CONFIRMED_ALREADY'));
	
} else {
	// активация аккаунта
	$query = "UPDATE auth_user SET confirmed=1 WHERE id='".$user['id']."'";
	$DB->update($query);
    
	// Разлогиниваем пользователя, что б перечиталась сессия
	if (Auth::isLoggedIn()) {
		$_SESSION['auth']['confirmed'] = 1;
		Action::setSuccess(cmsMessage::get('MSG_USER_CONFIRMED_DONE'));
	} else {
		Action::setSuccess(cmsMessage::get('MSG_USER_CONFIRMED_LOGINING'));
	}
	
}

?>