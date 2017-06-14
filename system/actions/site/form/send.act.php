<?php
/**
 * Отправка данных с формы на e-mail
 * @package Pilot
 * @subpackage Form
 */
$form_name    = globalVar($_REQUEST['form_name'], ''); //название формы form_test как в админке
$current_path = globalVar($_REQUEST['current_path'], ''); // /comanda/?
$form_data    = globalVar($_REQUEST['form'], array()); // данный полученый из формы.
x($_REQUEST);

$Form = new FormLight($form_name);
//x($_REQUEST);
//обработка данных формы
$data = $Form->checkFields($form_data);
//$_RESULT["js_test"] = Renault::sendToCRM($form_data);exit;
//x(Renault::sendToCRM($form_data));die;
if (!$data) {   // если есть шибки - этот блок
    //ошибка заполнения
    $_RESULT['javascript'] = "";
    foreach ($Form->getError() as $key => $error) {
        $_RESULT[ $form_name.'_error'] = $error;
        $_RESULT['javascript'] .= "$('#form_".$form_name." .form_section.".$key."').addClass('error');";
    }
    $_RESULT['javascript'] .= "$('.btn-form, #".$form_name."_error').show();";
    $_RESULT['javascript'] .= "scrollToElem('#form_".$form_name."');";
    exit;
}

//ошибок ввода нету? - этот блок
$Template = new Template( 'form/send' );
$Template->set('current_path', $current_path);
//передача в письмо данных
foreach ($data as $key => $row) {
    if ($row['type'] != 'file') {
        $Template->iterate('/row/', null, array('title' => $row['title'], 'value' => nl2br($row['value'])));
    }    
}
//отправка письма админам
$Form->sendmail($Template->display(), true);

// Отправка автоответа
if (isset($data['email'])) { 
    $Form->sendAutoreply($data['email']['value']);     
}

// сохранение базовых данных 
$insID = $Form->saveFormParam($data);
//передаем данные в LMT CRM

//Renault::sendToCRM($form_data, $insID, 0);
//end передаем данные в LMT CRM

//сообщение пользователю
if (!empty($Form->info['result_text'])) {    
	$_RESULT['wrap'.$form_name] = $Form->resultText($form_data);
    
} elseif (!empty($Form->info['destination_url'])) {
	$_RESULT['javascript'] = "document.location.href='{$Form->info['destination_url']}'";
    
} else {
	$_RESULT['form_'.$form_name] = '';
}

exit;
?>