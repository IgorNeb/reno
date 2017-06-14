<?php


$module = "News";
$module = strtolower($module);


$install_prefix = "install_";

$dbname = globalVar( $DB->db_name, "" );
if ( empty($dbname) ) {
    trigger_error("Не указана База Данных", E_USER_ERROR);
}

$tables_old = array();
$tables_new = array();

$sql = "SHOW TABLES FROM $dbname LIKE '{$install_prefix}{$module}%'";
$tables = $DB->fetch_column( $sql );
reset( $tables );
while ( list($key,$value)=each($tables) ){
    $fields = $DB->query("SHOW FIELDS FROM $value","field");
    $value = substr($value, strlen($install_prefix));   
    $tables_new[$value] = $fields;
}

$sql = "SHOW TABLES FROM $dbname LIKE '{$module}%'";
$tables = $DB->fetch_column( $sql );
reset( $tables );
while ( list($key,$value)=each($tables) ){    
    $fields = $DB->query("SHOW FIELDS FROM $value","field");    
    $tables_old[$value] = $fields;
}

reset($tables_new);
while ( list($table_key,$table_row)=each($tables_new) ) //browse thru master tables 
{    
    $found=false;
    if ( isset($tables_old[$table_key]) ) $found = true;

    if( $found ) 
    {
        $delete_old_fields = array();
        
        reset($tables_old[$table_key]);
        while ( list($field_key,$field_row)=each($tables_old[$table_key]) ){
            if ( !isset($tables_new[$table_key][$field_key]) ) $delete_old_fields[] = "DROP `$field_key`";
        }

        if ( count($delete_old_fields) ){
            $query = "ALTER TABLE `$table_key` " . implode(", ",$delete_old_fields) . "";
            $DB->delete( $query );
        }      
        
        $queries = array();
        $drop    = array();
        $unique  = array();
        
        reset($tables_new[$table_key]);
        while ( list($field_key,$field_row)=each($tables_new[$table_key]) ){
            
            #primary key
            if($field_row["key"]=='PRI') $primary=" PRIMARY KEY "; 
            else $primary='';
            
            //default
            $null_where = "NOT NULL";
            if ( $field_row["null"] == "YES" ) $null_where = "NULL";
                
            //index key
            $index_where = "";
            if ( $field_row["key"] == "MUL" ) $index_where = ", add key ( {$field_row["field"]} )";             
            
            #если найдена такая же колонка - проверяем её структуру
            if ( isset($tables_old[$table_key][$field_key]) ){
                
                if ( $field_row !== $tables_old[$table_key][$field_key] ){
                    
                    if ( $field_row["default"] === NULL ) $field_row["default"] = "";
                    else $field_row["default"] = "DEFAULT '" . $field_row["default"] . "'";                    
                    
                    if ( $field_row["key"] != $tables_old[$table_key][$field_key]["key"] ){
                        $drop[] = $field_key;
                        
                        if ( $field_row["key"] == "URI" ){
                            $unique[] = $field_key;
                        }                        
                        
                    }
                    
                    $queries[] = " CHANGE `{$field_row["field"]}` `{$field_row["field"]}` {$field_row["type"]} $null_where"
                    . " {$field_row["default"]} {$field_row["extra"]} $primary $index_where"; 
                }                
            }
            else {
                
                    if ( $field_row["default"] == NULL ) $field_row["default"] = "";
                    else $field_row["default"] = "DEFAULT '" . $field_row["default"] . "'";                
                
                    $queries[] = " ADD `{$field_row["field"]}` `{$field_row["field"]}` {$field_row["type"]} $null_where"
                    . " {$field_row["default"]} {$field_row["extra"]} $primary $index_where";               
            }
        }
        
        $drop_queries = array();
        
        if ( count( $drop ) ){
                    
            reset( $drop );
            while ( list($key,$value)=each($drop) ){
                switch ( $tables_old[$table_key][$value]["key"] ){
                    case "PRI":
                        $drop_queries[] = "DROP PRIMARY KEY";
                        break;
                    default:
                        $drop_queries[] = "DROP INDEX `$value`";
                        break;
                }
            }
        }        
        
        $queries = array_merge( $drop_queries, $queries );
        unset( $drop_queries );
        
        if ( count( $unique ) ){
           $queries[] = "UNIQUE (`" . implode("`,`",$unique) . "`) ";
        }        
        
        if ( count($queries) ){
            
            $query = "ALTER TABLE `$table_key` " . implode(",", $queries);
            $DB->update( $query );
        }       
        
    }
    else 
    { 
        $primary = array();
        $index   = array();
        $unique  = array();
        
        while ( list($field_key,$field_row)=each($tables_new[$table_key]) ){            
            $null_where = "NOT NULL";
            if ( $field_row["null"] == "YES" ) $null_where = "NULL";            
            
            if($field_row["key"]=='PRI') $primary[] = $field_row["field"]; 
            if($field_row["key"]=='MUL') $index[]   = "INDEX `{$field_row["field"]}` (`{$field_row["field"]}`)"; 
            if($field_row["key"]=='UNI') $unique[]  = $field_row["field"]; 
            
            if ( $field_row["default"] == NULL ) $field_row["default"] = "";
            else $field_row["default"] = "DEFAULT '" . $field_row["default"] . "'";
            
            $query[] = "`{$field_row["field"]}` {$field_row["type"]} $null_where {$field_row["default"]} {$field_row["extra"]} ";            
        }
        
        $query = implode( ", ", $query );
        $query = "CREATE TABLE `$table_key`(" . $query;
        
        if ( count($primary) ) $query .= ", PRIMARY KEY (`" . implode("`,`",$primary) . "`) ";
        if ( count($index) )   $query .= ", " . implode(",",$index);
        if ( count($unique) )  $query .= ", UNIQUE (`" . implode("`,`",$unique) . "`) ";
        
        $query .= ")";        
        $DB->insert( $query );
    }
    
    $DB->delete(" DROP TABLE IF EXISTS `{$install_prefix}{$table_key}`");
}

//triggers
$data_triggers = $DB->query("show triggers LIKE '$module%'");
reset( $data_triggers );
while ( list(,$trigger_row)=each($data_triggers) ){
    $DB->delete("DROP TRIGGER IF EXISTS {$trigger_row["trigger"]}");
}

//views
$dbname = globalVar( $DB->db_name, "" );
$data_views = $DB->fetch_column("SELECT TABLE_NAME FROM information_schema.`TABLES` WHERE TABLE_TYPE LIKE 'VIEW' AND TABLE_SCHEMA LIKE '$dbname' AND TABLE_NAME LIKE '$module%'");
reset( $data_views );
while ( list(,$view_name)=each($data_views) ){
    $DB->delete("DROP VIEW IF EXISTS {$view_name}");
}

exit;
