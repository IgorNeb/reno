<?php

/** 
 * Novaposhta
 * 
 * @package DeltaCMS
 * @subpackage Shop
 * @version 2.0
 * @author Naumenko A.
 * @copyright c-format, 2015
 */

class Novaposhta {
    
    /*
     * Ключ для работы с API Novaposhta
     */
    public $key = NOVAPOSHTA_KEY; //552324d1811fd2a25f10c33c077c35ea
 
    /*
     * Ссылка на ресурс
     */
    private $_url = "https://api.novaposhta.ua/v2.0/json/";
    
    private $_error = array();
    
    public function __construct($key = '')
    {
        if (!empty($key)) {
            $this->key = $key;
        }
    }
    
    private function setError($message)
    {
        $this->_error[] = $message;
    }
    
    public function getError()
    {
        return $this->_error;
    }

    /**
     * Возращает города для новой почты
     *     Формат запроса
     *     {     "apiKey": "ваш ключ АРІ 2.0",
     *           "modelName": "Address",
     *           "calledMethod": "getCities",
     *           "methodProperties": {}   }
     *      Формат ответа
     *       {
     *           "Description": "Агрономічне",                                  ------ назва міста
     *           "DescriptionRu": "Агрономичное",
     *           "Ref": "ebc0eda9-93ec-11e3-b441-0050568002cf",                 ------ ідентифікатор міста
     *           "Area": "71508129-9b87-11de-822f-000c2965ae0e",                ------ ідентифікатор регіона
     *           "Conglomerates": null,
     *           "CityID": "890"
     *      }, 
     * 
     * @return boolean
     */
    public function updateCities()
    {
        global $DB;
        
        $result = $this->sendRequest("Address", "getCities" );
        
        if( isset($result->success) && $result->success ){                        
            $count = 0;
            //Обработка городов
            foreach ($result->data as $city){                
                if(empty($city->DescriptionRu)) {continue;}
                $DB->query("
                    INSERT INTO `shop_delivery_city`
                    SET name_ru  = '". addslashes($city->DescriptionRu). "',
                        ref  = '{$city->Ref}'
                    ON DUPLICATE KEY UPDATE name_ru = VALUES(name_ru)
                    "); 
                $count++;
            }
            
            echo "[i] ADD $count cities";
            
        }
        else{
            $this->setError($result->errors);            
        }
        return false;
       
    }
    /**
     * Возращает города для новой почты
     *     Формат запроса
     *     {     "apiKey": "ваш ключ АРІ 2.0",
     *           "modelName": "Address",
     *           "calledMethod": "getWarehouses",
     *           "methodProperties": { "CityRef": "8d5a980d-391c-11dd-90d9-001a92567626" }   }
     *      Формат ответа
     *       {
     *           "Description": "Відділення №1: вул. Червонопрапорна, 154",
     *           "DescriptionRu": "Отделение №1: ул. Краснознаменная, 154",
     *           "Phone": "(044) 323-02-22",
     *           "Ref": "1ec09d88-e1c2-11e3-8c4a-0050568002cf",                  ------ ідентифікатор відділення
     *           "Number": "1",                                                  ------ номер відділення
     *           "TotalMaxWeightAllowed": 0,                                     ------ максимальна вага
     *      }, 
     * 
     * @return boolean
     */
    public function updateDepartment()
    {
        global $DB;
        
        $cities = $DB->fetch_column("select id, ref from `shop_delivery_city` ");
        foreach( $cities as $city_id => $city_ref ){
            
            $properties = '"CityRef": "'.$city_ref.'"';
            $result = $this->sendRequest( "Address", "getWarehouses", $properties );

            if (isset($result->success) && $result->success) {                        
                $count = 0;
                //Обработка городов
                foreach ($result->data as $depart){                
                    if(empty($depart->DescriptionRu)) {continue;}
                    $DB->query("
                            INSERT INTO `shop_delivery_department`
                            SET adress_ru  = '". addslashes($depart->DescriptionRu). "',
                                phone  = '{$depart->Phone}',
                                number  = '{$depart->Number}',
                                weight  = '{$depart->TotalMaxWeightAllowed}',
                                ref  = '{$depart->Ref}',                                
                                city_id = '$city_id'
                            ON DUPLICATE KEY UPDATE adress_ru = VALUES(adress_ru),
                            phone = VALUES(phone), weight = VALUES(weight), number = VALUES(number)
                    "); 
                    $count++;
                }            
                echo "[i] ADD $count department for cityId " . $city_id ."<br/>";
            }
            else{
                $this->setError($result->errors);            
            }
        }
        return false;
    }
    
    /**
     *    Возращает приблизительную стоимость доставки
     *     Формат запроса      {
     *           "apiKey": " Ваш ключ АРІ 2.0",
     *           "modelName": "InternetDocument",
     *           "calledMethod": "getDocumentPrice",
     *           "methodProperties": {                    
     *                 "VolumeGeneral": "0.1",                                     ----- объем
     *             (*) "Weight": "50",                                             ----- вес
     *                 "ServiceType": "WarehouseWarehouse",                        ----- тип доставки (склад - склад)
     *                 "SeatsAmount": "1",                                         ----- к-ство мест
     *                 "Description": "абажур",                                    ----- описание товара
     *             (*) "Cost": "500",                                              ----- оглашаемая стоимость
     *                  "CargoType": "Cargo",                                      ----- тип груза 
     *             (*) "CitySender": "8d5a980d-391c-11dd-90d9-001a92567626",       ----- город отправителя
     *             (*) "CityRecipient": "8d5a980d-391c-11dd-90d9-001a92567626"     ----- город получателя
     *            }   
     *     }
     */
    public function deliveryCoast($volumn, $weigth, $cost, $recipient_city_id, $type = 'WarehouseWarehouse')
    {
        global $DB;
            
        $volumn = num2db($volumn);
        $weigth = num2db($weigth);
        $recipient = $DB->result("select ref from shop_delivery_city where id = '$recipient_city_id' ");
        
        $kievSender = "8d5a980d-391c-11dd-90d9-001a92567626";
        
        //$properties = '"VolumeGeneral": "' . $volumn.'", "Weight": "' . $weigth . '", "Cost": "' . $cost . '", ' 
        $properties = '"Weight": "' . $weigth . '", "Cost": "' . $cost . '", ' 
                    . '"CitySender": "' . $kievSender . '", "CityRecipient": "' . $recipient .'", '
                    . '"CargoType": "Cargo", "ServiceType": "' . $type . '", "SeatsAmount": "1" ';
        
        $result = $this->sendRequest("InternetDocument", "getDocumentPrice", $properties);
        if( isset($result->success) && $result->success ){     
            $cost = 0;
            foreach (  $result->data as $c){
                $cost = $c->Cost;
            }
            return $cost;
        }
        else{
            $this->setError($result->errors);            
        }
    }
    
    /**
     * Отправка запроса
     * @param string $model - модель запроса
     * @param string $method - метод Новой почты
     * @param string $properties - свойства
     * @return array objects
     */
    public function sendRequest($model, $method,  $properties = "")
    {   
        $data_string = '{"apiKey": "'.$this->key.'", "modelName": "'.$model.'", "calledMethod": "'.$method.'", "methodProperties": {'.$properties.'} }';            
        $headers = array(
                         "Content-Type: application/json",                            
                         "Content-length: ".strlen($data_string)
                    );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        $responce = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($responce);
    }

}