<?php

/** 
 * Загрузка формы
 * @package DeltaCMS
 * @subpackage Form
 */

$form_name  = globalVar($_REQUEST['form_name'], '');
#$master  = globalVar($_REQUEST['master'], '');

if (empty($form_name)) {
    exit();
}

$TmplForm = TemplateUDF::form(array('name'=> $form_name));
$_RESULT['modal_form'] = $TmplForm;

//$title = ($Form->show_title == 1) ? $Form->title : "";
$_RESULT['javascript'] = "var html_form = $('#modal_form').html();$('#modal_form').html('');";
$_RESULT['javascript'] .= "$.fn.custombox({ url: html_form, title: '',  overlay: true  });";
$_RESULT['javascript'] .= "formUpdate();";
