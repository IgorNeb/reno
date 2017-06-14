<?php
/**
 * Класс обработки фотогаллереи
 * @package DeltaCMS
 * @subpackage Gallery
 * @version 3.0
 * @author Naumenko A.
 * @copyright (c) 2014, c-format
 */

class uiGallery extends Gallery 
{       
        
    /**
     * @var string template
     */
    public $Template = '';

    /**
     *
     * @var array params
     */
    public $param = array();
       
    /**
	 * Конструктор класса
	 *
	 * @param int $group_id
	 */
	public function __construct($group_table, $group_id)
    {
		parent::__construct($group_table, $group_id);
	}
        
    /**
     * 
     * @param string $param - param name
     * @param type $value - значение
     */
    public function setParam($param, $value)
    {
        $this->param[$param] = $value;
    }
        
    /**
	 * Метод, который выполняется после того, как указаны колонки, которые необходимо вывести
     * 
	 * @param void
	 * @return string
	 */
	public function display( $template = '', $template_photo = 'cms/admin/ui_gallery_photo') 
    {
        global $DB;

        require_once TEMPLATE_ROOT.'cms/admin/ui_gallery_libs.ru.tmpl';

        // Шаблон таблицы, в которой выводятся данные
        $template = (empty($template)) ? 'cms/admin/ui_gallery' : $template;
        $this->Template = new Template( $template );

        $this->Template->set('group_id', $this->group_id);
        $this->Template->set('group_table', $this->group_table);           

        $this->Template->set($this->param);                

        $Template =  new Template($template_photo);   
        $Template->setGlobal('current_url_link', CURRENT_URL_LINK);

        $table_id = $DB->result("select id from cms_table where name='gallery_photo'");            
        $Template->setGlobal('table_id', $table_id);

        $photos = $this->getPhotos(500, 0, 0);

        reset($photos);
        while (list(, $row) = each($photos)) {
            //размер
            $filesize = filesize(UPLOADS_ROOT . $row['photo']);
            if ($filesize > 1024 * 1024) {
                $row['size'] = number_format( ($filesize * 100 / (1024 * 1024)) / 100, 2, '.', ' ')  . ' MB';
    		} else {
                $row['size'] = number_format( ($filesize * 100 / 1024) / 100 ) . ' KB';
            }
                                
            $Template->iterate('/photo/', null, $row);
        }
            
        $this->Template->set('cms_gallery_layout', $Template->display());
        return $this->Template->display();
    }
    
}