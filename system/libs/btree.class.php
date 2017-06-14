<?php 
/**
* Класс построения и обработки B деревьев
* 
* @package DeltaCMS
* @subpackage Libraries
* @version 3.0
* @author Rudenko Ilya <rudenko@delta-x.com.ua>
* @copyright Copyright 2004, Delta-X ltd.
*/

Class BTree 
{
	/**
	* Исходная информация
	* @var array
	*/
	public $data = array();
	
	/**
	* Отношение родитель - наследники
	* @var array
	*/
	private $relations = array();
	
	/**
	* Отношение наследник - родитель
	* @var array
	*/
	private $reverse_relations = array();
	
	/**
	* Отработанные id, защита от зацикливания
	* @var array
	*/
	private $used = array();
	
	/**
	* Довчерние разделы
	* @var array
	*/
	private $childs = array();
	
	/**
	* Массив, который возвращает ф-я treemenu
	* @var array
	*/
	private $build_treemenu = array();
	
	/**
	* Конструктор класса
	* @param array $data[] = array(id, parent)
	* @return object
	*/
	public function __construct($data) 
    {
		$this->data = $data;
		
		$node = reset($data); 
		
		if (is_array($node)) {
			/**
			* В конструктор передан массив типа $data[] = array($id, $parent)
			*/
			while (list(, $node) = each($data)) {
				$this->relations[$node['parent']][] = $node['id'];
				$this->reverse_relations[$node['id']] = $node['parent'];
			}
		} else {
			/**
			* В конструктор передан массив типа $data[$id] = $parent
			*/
			while (list($id, $parent) = each($data)) {
				$this->relations[$parent][] = $id;
				$this->reverse_relations[$id] = $parent;
			}
		}
	}
	
	/**
	* Определяет дочерние разделы
	* @param int $id
	* @return array
	*/
	private function getChildNodes($id) 
    {
		$this->childs = array();
		$this->used = array();
		
		$this->build($id);
		
		$childs = $this->childs;
		$this->used = array();
		$this->childs = array();
		
		return $childs;
	}
	
	/**
	* Строит дерево
	* @param int $id
	* @return void
	*/
	private function build ($id) 
    {
		/**
		* Защита от зацикливания
		*/
		if (in_array($id, $this->used)) {
			return ;
		}
		$this->used[$id] = $id;
		
		/**
		* Определяем все найденные дочерние разделы
		*/
		$this->childs[] = $id;
		
		if (isset($this->relations[$id]) && is_array($this->relations[$id])) {
			reset($this->relations[$id]);
			while (list(, $child) = each($this->relations[$id])) {
				$this->build($child);
			}
		}
	}
	
	/**
	* Создает путь к разделу, определяет родительские разделы
	* @param int $id
	* @return void
	*/
	private function getParents ($id) 
    {
		$path = array();
		do {
			if (!isset($this->reverse_relations[$id])) {
				break;
			}
			$path[] = $id;
			$id = $this->reverse_relations[$id];
		} while (!empty($id));
		return $path;
	}
	
	/**
	* Определяет id разделов, у которых нет дочерних разделов
	* @param void
	* @return array
	*/
	private function rootNodes() 
    {
		$return = array();
		reset($this->data);
		while (list($id, ) = each($this->data)) {
			if (!isset($this->reverse_relations[$id]) || empty($this->reverse_relations[$id])) {
				$return[] = $id;
			}
		}
		return $return;
	}
	
	/**
	* Запуск построения дерева treemenu
	* @param void
	* @return array
	*/
	public function treemenu() 
    {
		if (empty($this->relations)) {
            return array();
        }
		$this->build_treemenu(0);
		return $this->build_treemenu;
	}
       
    /**
     * Возвращает постороение для вывода
     * 
     * @return array
     */
    public function relation() {
        return $this->relations;
    }
    
    /**
     * Вывод в шаблон
     * 
     * @param string $tmpl - шаблон
     * @param int $lvl уровень
     * @return text
     */
    public function tree_template($tmpl, $lvl = 0)
    {
        $template = new Template( $tmpl );

        reset( $this->relations[$lvl] );
        while ( list($key,$value)=each($this->relations[$lvl]) ){
            $row = $this->data[$value];

            if ( isset($this->relations[$value]) && is_array($this->relations[$value]) ){
                $row['children_content'] = $this->tree_template( $tmpl, $value );
            }

            $template->iterate( '/row/', null, $row );
        }
        return $template->display();
    }

    /**
	 * Строим специальное дерево, которое используется при выводе 
	 * меню в административном интерфейсе
	 * @param int $x
	 * @return array
	 */
	private function build_treemenu($x) 
    {
		reset($this->relations[$x]);
		while (list(, $id) = each($this->relations[$x])) {
			$this->build_treemenu[] = $id;
			if (isset($this->relations[$id])) {
				$this->build_treemenu($id);
			}
		}
	}  
    
    /**
     * генерирует дерево в шаблон
     * 
     * @param int $lvl уровень
     * @param string $tmpl - шаблон
     * @return text вывод шаблона
     */
    public function generate_tmpl($lvl, $tmpl)
    {
        $LeftMenu = new Template("cms/admin/left_menu"); 
        reset($this->relations[$lvl]);
        while (list(, $index) = each($this->relations[$lvl])) {
            $row = $this->data[$index];

            if ( isset($this->relations[$index]) ) {
                $row["children_content"] = $this->generate_tmpl($index, $tmpl);
            }
            $LeftMenu->iterate( "/row/", null, $row );
        }
        
        return $LeftMenu->display();
    }
	
}