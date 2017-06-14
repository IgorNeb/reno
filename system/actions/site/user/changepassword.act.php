<?php

/**
 * Изменяет пароль пользователя
 * @package Pilot
 * @subpackage User
 * @author Rudenko Ilya <rudenko@delta-x.com.ua>
 * @copyright Delta-X, ltd. 2005
 */

$user_id = Auth::isLoggedIn();

$passwd = trim(globalVar($_POST['passwd'], ''));
$new_passwd = trim(globalVar($_POST['new_passwd'], ''));
$new_passwd_confirm = trim(globalVar($_POST['new_passwd_confirm'], ''));

if (empty($new_passwd)) {
	// не указан пароль	
	Action::onError( cmsMessage::get("MSG_FORM_EMPTY_PASSWORD") );
}  elseif (!preg_match(VALID_PASSWD, $new_passwd)) { 
	// новый пароль содержит недопустимые символы	
	Action::onError( cmsMessage::get("MSG_FORM_ERROR_PASSWORD") );
} elseif ($new_passwd != $new_passwd_confirm) {
	// введенные пароли - не совпвадают
	Action::onError( cmsMessage::get("MSG_FORM_ERROR_PASSWORD_COMFIRM") );
}


$DB->query("LOCK TABLES auth_user WRITE");

/**
 * Проверяем правильность указания пароля
 */
$DB->query("SELECT id FROM auth_user WHERE id='$user_id' AND passwd='".md5($passwd)."'");
if ($DB->rows != 1) {
	// Неправильно указан пароль
	Action::onError( cmsMessage::get("MSG_FORM_ERROR_OLD_PASSWORD") );
}

/**
 * Обновление информации о пользователе
 */
$DB->update("UPDATE auth_user SET passwd='".md5($new_passwd)."' WHERE id='$user_id' ");
$DB->query("UNLOCK TABLES");

/**
 * Пароль успешно изменен
 */
Action::setSuccess( cmsMessage::get("MSG_FORM_PASSWORD_CHANGE") );

?>

