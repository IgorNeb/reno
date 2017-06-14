<?php

/** 
 * Авторизация пользователя административного интерфейса 
 * @package Pilot 
 * @subpackage CMS 
 * @author Rudenko Ilya <rudenko@delta-x.com.ua> 
 * @copyright Delta-X, ltd. 2008 
 */

/**
 * Флаг авторизации
 */ 
$_SESSION['auth_user_mode'] = 'login';

/**
 * Основные данные
 */
$login 	  = trim(globalVar($_REQUEST['login'], ''));
$passwd   = trim(globalVar($_REQUEST['passwd'], ''));
$remember = globalVar($_REQUEST['remember'], 0);

/**
 * Флаг, что определяет - запрос был сделан со стороны сайта или со стороны административной зоны
 */
$source = globalVar($_REQUEST['source'], 'site');

$_RESULT["javascript"] = ($source == 'site') ? "" : "
    $('.hintField1').hide();
    $('.hintField2').hide();
";

$form_shake = "$('.form').effect( 'shake',{times:4}, 1000 );";

/**
 * Проверяем правильность переданных данных
 */
if (!preg_match(VALID_EMAIL, $login)) {
    if ( AJAX && $source == 'site') {
            $_RESULT["auth_login_error"]   = cmsMessage::get('MSG_USER_LOGIN_ERROR_EMAIL');
            exit;
    } elseif ( AJAX ) {
        $_RESULT["hintLogin"]   = cms_message('CMS', 'Неверно введен e-mail.');
        $_RESULT["javascript"] .= $form_shake . " $('.hintField1').show(); ";
        exit;
    }
    else Action::onError(cms_message('CMS', 'Неверно введен e-mail.'));
} 


/**
 * Если пользователь уже залогинился, то удаляем существующую сессию
 */
if (isset($_SESSION['auth']['id']) && !empty($_SESSION['auth']['id'])){
	unset($_SESSION['auth']);
}


/**
 * Проверяем CAPTCHA, если к нам пришел хакер
 */
//if (Auth::isHacker() && !Captcha::check(globalVar($_REQUEST['captcha_uid'], ''), globalVar($_REQUEST['captcha_value'], ''))) {
//    if ( AJAX ) {
//        $_RESULT["hintCaptcha"] = cms_message('CMS', 'Неверно введен e-mail.');
//        exit;
//    }
//    else Action::onError(cms_message('CMS', 'Неправильно введено число на картинке'));
//}


/**
 * Входим в систему
 */
$user = $DB->query_row("
	SELECT id, passwd, confirmed
	FROM auth_user 
	WHERE login='$login' or email='$login'
");


$user_id = (!empty($user['id'])) ? $user['id'] : 0;


/**
 * Пользователь не найден
 */
if (empty($user_id)) {
	Auth::logLogin(0, time(), $login, $passwd);
    if ( AJAX && $source == 'site') {
        $_RESULT["auth_login_error"]   = cmsMessage::get('MSG_USER_LOGIN_ERROR');
        exit;
    } elseif ( AJAX ) {
        $_RESULT["hintLogin"]   = cms_message('CMS', 'Пользователя с указанным e-mail не существует.');
        $_RESULT["javascript"] .= $form_shake . " $('.hintField1').show(); ";
        exit;
    }
	else Action::onError(cms_message('CMS', 'Пользователя с указанным e-mail не существует.')); 
}
 
/**
 * Пользователь найден, но не верно указан пароль
 */
if($user['passwd'] != md5($passwd)){
	Auth::logLogin(0, time(), $login, $passwd);
        if ( AJAX && $source == 'site') {
            $_RESULT["auth_login_error"]   = cmsMessage::get('MSG_USER_LOGIN_ERROR');
            exit;
        } elseif ( AJAX ) {
            $_RESULT["hintPass"]    = cms_message('CMS', 'Неправильно указан логин или пароль пользователя');
            $_RESULT["javascript"] .= $form_shake . " $('.hintField2').show(); ";
            exit;
        }
	else Action::onError(cms_message('CMS', 'Неправильно указан логин или пароль пользователя' ));
}


if(AUTH_CONFIRM_ACCOUNT && $user['confirmed'] == 0){
    if ( AJAX && $source == 'site') {
            $_RESULT["auth_login_error"]   = cmsMessage::get('MSG_USER_NO_CONFIRMED');
            exit;
    } elseif ( AJAX ) {
            $_RESULT["hintLogin"]    = cmsMessage::get('MSG_USER_NO_CONFIRMED');
            $_RESULT["javascript"] .= $form_shake . " $('.hintField1').show(); ";
            exit;
    }
    else Action::onError(cmsMessage::get('MSG_USER_NO_CONFIRMED'));
}

/**
 * Попытка авторизации
 */
$logged_in = Auth::login($user['id'], $remember, null);
if ( (!$logged_in) || ( is_array($logged_in) && count($logged_in) ) ) {
	Auth::logLogin(0, time(), $login, $passwd);
	Action::onError(cms_message('CMS', 'Доступ с IP заблокирован или Ваш аккаунт отключен администратором'));
}  
if ( AJAX && $source == 'site') {
    $_RESULT['javascript'] = "window.location.href='/user/works/'";
    exit();
}
Action::setOk(cms_message('User', "Поздравляем, Вы успешно авторизировались"),"/admin/");

?>