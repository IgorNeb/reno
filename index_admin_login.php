<?php

/**
 * Admin login page
 * 
 * @package Pilot
 * @subpackage CMS
 * @version 6.0
 * @author Rudenko Ilya <rudenko@delta-x.ua>
 * @copyright Delta-X, 2008
 */


/**
 * Define the interface to support localization
 * @ignore 
 */
define('CMS_INTERFACE', 'SITE');


/**
 * Config
 */
require_once('system/config.inc.php');

/**
 * Access to admin console by IP
*/
if (AUTH_ADMIN_ALLOW_IP) {
    $ip = (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '';
    $allow_ip = dexplode(AUTH_ADMIN_ALLOW_IP);	
    if (!IP_ADMINS && !in_array($ip, $allow_ip)) {
        header( 'Location: /', true, 301 );
    }
}

/**
 * Connect to database
 */
$DB = DB::factory('default');

/**
 * Session start
 */
if (!isset($_SESSION)) {
	session_start();
}
 
/**
 * If user has already logged in - redirect him further
 */      
if (Auth::isLoggedIn()) {
	header("Location:/admin/");
	exit;
}

$amnesia = globalVar($_REQUEST['amnesia'], 0);

/**
 * If user clicked an "amnesia"
 */
if ($amnesia) {
	$TmplDesign = new Template(SITE_ROOT.'design/cms/amnesia');
	$TmplDesign->set('captcha_html', Captcha::createHtml('admin'));	
} else {
    // If he did not
	$TmplDesign = new Template(SITE_ROOT.'design/cms/login');
	$TmplDesign->set('login_form', Auth::displayLoginForm(true));
}

/**
 * Set source for OpenID widgets
 */
$_SESSION['oid_widget']['source'] = 'admin';

/**
 * Action messages output
 */
Action::displayStack();
  
/**
 * Clean action error session 
 */
if (isset($_SESSION)) {
	if (isset($_SESSION['ActionReturn'])) unset($_SESSION['ActionReturn']);
	if (isset($_SESSION['ActionError'])) unset($_SESSION['ActionError']);
}

/**
 * Output
 */
echo $TmplDesign->display();
exit; // established to stop viruses that used to be added at the end of file iframe

?>