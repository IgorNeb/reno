<?php
/**
 * Инфоблоки
 * 
 * @package DeltaCMS
 * @subpackage Site
 * @version 3.0
 * @author Naumenko A.
 * @copyright c-format, 2016
 */
class Infoblock 
{

    /**
	 * Раздел
	 *
	 * @var int
	 */
	private $info_id = 0;
    
    /**
	 * Шаблон
	 *
	 * @var string
	 */
	private $template = '';
    
    /**
     * Информация об разделе
     * @var array
     */
    public $block = array();
    
    /**
	 * Конструктор класса
     * 
	 * @global DB $DB
	 * @param int $id
     * @return array $data	 
	 */    
	function __construct($id) 
    {
        global $DB;
        
        $this->info_id = $id;
        
        $query = " SELECT 
                             tb_block.id, 
                             tb_block.show_title,  
                             IFNULL(tb_template.uniq_name, '') as template,
                             IF(tb_child.id is not null, 'item', 'message') as type,
                             tb_block.name_".LANGUAGE_CURRENT." as title,
                             tb_block.subtitle_".LANGUAGE_CURRENT." as subtitle
                    FROM  site_infoblock as tb_block                        
                    LEFT JOIN site_infoblock AS tb_child ON tb_child.group_id=tb_block.id and tb_child.active='1'
                    LEFT JOIN site_infoblock_message AS tb_message ON tb_message.group_id=tb_block.id and tb_message.active='1'
                    LEFT JOIN site_infoblock_template AS tb_template ON tb_template.id=tb_block.template_id 
                    WHERE tb_block.id='{$id}' and tb_block.active='1' 
                    GROUP BY tb_block.id    
                    ORDER BY tb_block.priority";
        $this->block = $DB->query_row($query);
        
        if (isset($this->block['template']) && !empty($this->block['template'])) {
            $this->template = $this->block['template'];            
        }
    }
    
    public function gallery() 
    {
        if ($this->template == 'image_gallery') {
            $Gallery = new Gallery('site_infoblock', $this->info_id);
            $data = $Gallery->getPhotos(9, 0);
            return $data;
        }
        return array();
    }
    
    /**
     * Возращает шаблон
     * @return string
     */
    public function getTemplate() 
    {
        return (empty($this->template)) ? 'default' : $this->template;
    }
    
    /**
     * Изменяем шаблон Раздела
     * @return string
     */
    public function setTemplate($template) 
    {
        $this->template = $template;
    }
    
         
    /**
     * Выбор сообщений инфоблока
     * 
     * @global DB $DB
     * @return array $data
     */
    public function getMessage()
    {
        global $DB;

        $where = ($this->block['type'] == 'item') ? where_clause('tb_block.group_id', $this->info_id) : where_clause('tb_message.group_id', $this->info_id);
        $query = "SELECT
                    tb_message.*,
                    tb_block.name_".LANGUAGE_CURRENT." as group_name
                FROM site_infoblock_message as tb_message                        
                INNER JOIN site_infoblock as tb_block ON tb_block.id = tb_message.group_id
                WHERE tb_message.active='1' AND tb_block.active='1' " . $where . "
                ORDER BY tb_block.priority, tb_message.priority ";                
        $data = $DB->query($query);
        
        //name_ru => name и if (empty(name_uk)) name_uk=name_ru 
        $data = Misc::parseLangFields($data);
        
        $data = $this->parseMessage($data);       
        
        return $data;                   
    }
    
    /**
     * Выбор товаров инфоблока. Возращает шаблон
     * 
     * @global DB $DB
     * @return text
     */
    public function getProducts()
    {
        global $DB;

        $query = "
            SELECT
                product_id
            FROM site_infoblock_product_relation as tb_relation
            WHERE tb_relation.infoblock_id='{$this->info_id}'
        ";
        $list = $DB->fetch_column($query);
        if (!empty($list)) {
            $Shop = new Shop(1);
            $products = $Shop->getDirectProductInfo($list);
            if (count($products)) {
                $TmplProducts = new Template("shop/product_block");
                $TmplProducts->setGlobal('is_four', true);
                $TmplProducts->iterateArray("/product/", null, $products);
                return $TmplProducts->display();
            }
        }
        return '';                   
    }
    
    /**
     * Обработка сообщений инфоблоков
     * 
     * @param array $data
     * @return array
     */
    private function parseMessage($data)
    {
        foreach ($data as $i => $row) {        
            $data[$i]['index']       = $i + 1;
            #$data[$i]['description'] = nl2br($row['description']);    
            $data[$i]['image']       = Uploads::getIsFile('site_infoblock_message', 'image', $row['id'], $row['image']);
            
            if (!empty($row['link']) && strpos($row['link'], 'http') === false) {
                $data[$i]['link'] = "/" . LANGUAGE_URL . ltrim($row['link'], '/');
            }
        } 
        
        return $data;
    }
    
    /**
     * Вывод шаблона
     * 
     * @param array $message
     * @return text 
     */
    public function display($message)
    {
       
        $template = $this->getTemplate();
        $Template =  new Template('infoblock/'. $template);
        $Template->set($this->block);
        
        if ($this->block['type'] == 'item') {
            $group = -1;
            foreach ($message as $row) {
                if ($row['group_id'] != $group) {
                    $fp = $Template->iterate('/group/', null, $row);
                    $group = $row['group_id'];
                }
                $Template->iterate('/group/message/', $fp, $row);
            }
        } else {
            $Template->iterateArray('/message/', null, $message);
        }
        
        //прикрепленные товары к инфоблоку
        //$Template->set('products', $this->getProducts());
        
        //галерея
        $gallery = $this->gallery();
        if (count($gallery)) {
            $Template->set("show_gallery", true);
            $Template->iterateArray('/gallery/', null, $gallery);
        }
         
        if (Auth::isAdmin()) {
            $Template->setGlobal('admin_link', '<a href="/admin/site/infoblock/?group_id='.$this->info_id.'" target="_blank">[ред. ИнфоБлок]</a>');
        }
        
        return $Template->display();
    }
}