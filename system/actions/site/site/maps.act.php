<?php

/*
 * Маркеры на карте & подмена селекта
 * @package Pilot 
 * @subpackage Site
 * @author Naumenko A.
 * @copyright c-format, 2017
 */

$city_s = globalVar($_REQUEST['city'], 0);
$type_s = globalVar($_REQUEST['type'], '');

$_RESULT['javascript'] = '';
$query = "
            select 
                    tb_store.id,
                    tb_store.city_id,
                    tb_store.name_" . LANGUAGE_CURRENT . " as name,                 
                    tb_store.description_" . LANGUAGE_CURRENT . " as description,
                    tb_store.phone,
                    tb_store.latlng,
                    tb_store.type,
                    tb_store.address_" . LANGUAGE_CURRENT . " as address,
                    tb_store.time_" . LANGUAGE_CURRENT . " as time
            from site_store tb_store
            where tb_store.active=1 and tb_store.type <> 'office'
                  " . where_clause('tb_store.type', $type_s) . "  
                  " . where_clause('tb_store.city_id', $city_s) . "  
            order by tb_store.priority desc
    ";
$store = $DB->query($query, 'id');

foreach ($store as $id => $row) {
    $_RESULT['javascript'] .= "add_marker('$row[latlng]', '$row[id]', '$row[address]', '$row[phone]', '$row[time]');";
}
if (isset($id) && !empty($id)) {
    $_RESULT['javascript'] .= "map.setCenter( markersArray[" . $id . "].getPosition() );map.setZoom(5);";
}

$selector = ".store__item_" . implode(', .store__item_', array_keys($store));
$_RESULT['javascript'] .= "showStore('".$selector."');";


$Template = new Template('site/maps_select');
/* типы магазинов */
$query = "
            select DISTINCT(tb_store.type)
            from site_store tb_store
            where tb_store.active=1 and tb_store.type <> 'office'
            " . where_clause('tb_store.city_id', $city_s) . "  
            order by tb_store.priority
    ";
$types = $DB->fetch_column($query);
foreach ($types as $type) {
    $Template->iterate("/types/", null, array(
        'type' => $type,
        'selected' => ($type == $type_s),
        'name' => cmsMessage::get('MSG_STORE_TYPE_' . strtoupper($type))));
}
//города
$query = "
            select 
                tb_store.city_id as id, 
                if (tb_store.city_id = '$city_s', 1, 0) as selected,
                tb_city.name_" . LANGUAGE_CURRENT . " as name
            from site_store tb_store
            inner join shop_delivery_city as tb_city on tb_city.id=tb_store.city_id
            where tb_store.active=1 and tb_store.type <> 'office' 
                  " . where_clause('tb_store.type', $type_s) . "  
            group by tb_store.city_id
            order by tb_store.priority
    ";
$city = $DB->query($query);
$Template->iterateArray("/cities/", null, $city);

if (count($city) == 1 || !empty($city_s)) {
    $_RESULT['javascript'] .= "map.setZoom(9);";
}

$_RESULT['form_maps'] = $Template->display();

$_RESULT['javascript'] .= "mobileSelect('#form_maps select');";


