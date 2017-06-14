<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$dealer_id    = globalVar($_REQUEST['dealer_id'], '0');

if (empty($dealer_id)) {
    exit();
}

$query = "  select 
                    tb_dealer.*,
                    tb_dealer.name_".LANGUAGE_CURRENT." as name,                                     
                    tb_dealer.name_".LANGUAGE_CURRENT." as dealer_name,                                     
                    tb_dealer.city_".LANGUAGE_CURRENT." as city,
                    tb_dealer.time_".LANGUAGE_CURRENT." as time,
                    tb_dealer.address_".LANGUAGE_CURRENT." as address    
                from site_dealer tb_dealer
                where tb_dealer.active=1 AND tb_dealer.id='$dealer_id'
                order by tb_dealer.priority
        ";
$dealer = $DB->query_row($query);
if (empty($dealer)) {
    exit();
}

if (!empty($dealer['city'])) {
    $dealer['dealer_name'] .= ' - ' . $dealer['city'];
}

if (!empty($dealer['time'])) {
    $dealer['time'] = nl2br($dealer['time']);
}

$Template = new Template('site/dealer');
$Template->set($dealer);
$_RESULT['map_dealer_result'] = $Template->display();