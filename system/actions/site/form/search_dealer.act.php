<?php

/* 
 * Поиск по дилерам
 */
$text    = globalVar($_REQUEST['text'], '');

if (strlen($text) / 2 < 2) {
    exit();
}

$query = "  select 
                    tb_dealer.id,                    
                    tb_dealer.name_".LANGUAGE_CURRENT." as name,                                     
                    tb_dealer.city_".LANGUAGE_CURRENT." as city
                from site_dealer tb_dealer
                where tb_dealer.active=1
                    AND (tb_dealer.name_".LANGUAGE_CURRENT." like '%$text%' OR tb_dealer.city_".LANGUAGE_CURRENT." like '%$text%')
                order by tb_dealer.priority
        ";

$dealer = $DB->query($query);
if (empty($dealer)) {
    exit();
}

foreach ($dealer as $i => $row) {
    $dealer[$i]['dealer_name'] = $row['name'] . (!empty($row['city']) ? ' - ' . $row['city'] : '');
}
$Template = new Template('site/dealer_search');
$Template->iterateArray('/dealer/', null, $dealer);

$_RESULT['autocomplete_dealer'] = $Template->display();
$_RESULT['javascript'] = "$('#autocomplete_dealer').addClass('is-active');";