<?php
/**
 * Расширение для класса cmsShowView, которое позволяет выводить простые справочники
 * @package DeltaCMS
 * @subpackage CMS
 * @version 3.0
 * @author Rudenko Ilya <rudenko@delta-x.com.ua>
 * @copyright Copyright 2006, Delta-X ltd.
 */

class cmsShowViewInfo extends cmsShowView
{
	public function __construct(DB $DBServer, $table_name) 
    {
		$table = cmsTable::getInfoByAlias($DBServer->db_alias, $table_name);
		$query = "select * from `$table_name`";
		if (!empty($table['fk_order_name'])) {
			$query .= " order by `$table[fk_order_name]` $table[fk_order_direction]";
		}
		parent::__construct($DBServer, $query, CMS_VIEW, $table_name);
		$this->addColumn('name', '90%');
	}
    
}

?>