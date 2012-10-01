<?php

/**
    VDB - an extension of the SQL database plugin for the PHP Fat-Free Framework

    The contents of this file are subject to the terms of the GNU General
    Public License Version 3.0. You may not use this file except in
    compliance with the license. Any of the license terms and conditions
    can be waived if you get permission from the copyright holder.

    Copyright (c) 2012 by ikkez
    Christian Knuth <mail@ikkez.de>

        @package VDB
        @version 0.8.0
 **/

class VDB extends DB {

    public
        $dataTypes = array(
            'BOOLEAN'=>array(          'mysql|sqlite2?'=>'BOOLEAN',
                                       'pgsql'=>'text',
                                       'mssql|sybase|dblib|odbc|sqlsrv'=>'bit',
                                       'ibm'=>'numeric(1,0)',
            ),
            'INT8'=>array(             'mysql'=>'TINYINT(3)',
                                       'sqlite2?|pgsql'=>'integer',
                                       'mssql|sybase|dblib|odbc|sqlsrv'=>'tinyint',
                                       'ibm'=>'smallint',
            ),
            'INT16'=>array(            'sqlite2?|pgsql|sybase|odbc|sqlsrv|imb'=>'integer',
                                       'mysql|mssql|dblib'=>'int',
            ),
            'INT32'=>array(            'sqlite2?|pgsql'=>'integer',
                                       'mysql|mssql|sybase|dblib|odbc|sqlsrv|imb'=>'bigint',
            ),
            'FLOAT'=>array(            'mysql|sqlite2?'=>'FLOAT',
                                       'pgsql'=>'double precision',
                                       'mssql|sybase|dblib|odbc|sqlsrv'=>'float',
                                       'imb'=>'decfloat'
            ),
            'DOUBLE'=>array(           'mysql|sqlite2?|ibm'=>'DOUBLE',
                                       'pgsql|sybase|odbc|sqlsrv'=>'double precision',
                                       'mssql|dblib'=>'decimal',
            ),
            'TEXT8'=>array(            'mysql|sqlite2?|ibm'=>'VARCHAR(255)',
                                       'pgsql'=>'text',
                                       'mssql|sybase|dblib|odbc|sqlsrv'=>'char(255)',
            ),
            'TEXT16'=>array(           'mysql|sqlite2?|pgsql|mssql|sybase|dblib|odbc|sqlsrv'=>'text',
                                       'ibm'=>'BLOB SUB_TYPE TEXT',
            ),
            'TEXT32'=>array(           'mysql'=>'LONGTEXT',
                                       'sqlite2?|pgsql|mssql|sybase|dblib|odbc|sqlsrv'=>'text',
                                       'ibm'=>'CLOB(2000000000)',
            ),
            'DATE'=>array(             'mysql|sqlite2?|pgsql|mssql|sybase|dblib|odbc|sqlsrv|ibm'=>'date',
            ),
            'DATETIME'=>array(         'pgsql'=>'timestamp without time zone',
                                       'mysql|sqlite2?|mssql|sybase|dblib|odbc|sqlsrv'=>'datetime',
                                       'ibm'=>'timestamp',
            ),
    );

    public
        $name;

    const
        TEXT_NoDatatype='The specified datatype %s is not defined in %s driver';

    /**
     * alter table operation stack wrapper
     * @param $name
     * @param $func
     * @return mixed
     */
    public function table($name,$func) {
        $func = func_get_args();
        array_shift($func);
        $tmp = $this->name;
        $this->name = $name;
        if(count($func)>1)
            foreach($func as $f)
                $return[] = call_user_func($f,$this);
        else    $return = call_user_func($func[0],$this);
        $this->name = $tmp;
        return $return;
    }

    /**
     * create table operation stack wrapper
     * @param $name
     * @param $func
     * @return mixed
     */
    public function create($name,$func){
        $this->createTable($name);
        return $this->table($name,$func);
    }

    /**
     * parse command array and return backend specifiy query
     * @param $cmd
     * @return bool
     */
    private function findQuery($cmd) {
        $match=FALSE;
        foreach ($cmd as $backend=>$val)
            if (preg_match('/'.$backend.'/',$this->backend)) {
                $match=TRUE;
                break;
            }
        if (!$match) {
            trigger_error(self::TEXT_DBEngine);
            return FALSE;
        }
        return $val;
    }

    /**
     * get all tables of current DB
     * @return bool|array list of tables, or false
     */
    public function getTables() {
        $cmd=array(
            'mysql'=>array(
                "show tables"),
            'sqlite2?'=>array(
                "SELECT name FROM sqlite_master WHERE type='table' AND name!='sqlite_sequence'"),
            'mssql|sqlsrv|pgsql|sybase|dblib|odbc'=>array(
                "select table_name from information_schema.tables where table_schema = 'public'"),
            'ibm'=>array("select TABLE_NAME from sysibm.tables"),
        );
        $query = $this->findQuery($cmd);
        if(!$query[0]) return false;
        $tables = $this->exec($query[0]);
        if ($tables && is_array($tables) && count($tables)>0)
            foreach ($tables as &$table)
                $table = array_shift($table);
        return $tables;
    }

    /**
     * create a basic table, containing ID field
     * @param $table
     * @return bool
     */
    public function createTable($table) {
        if(in_array($table,$this->getTables())) return true;
        $cmd=array(
            'sqlite2?|sybase|dblib|odbc'=>array(
                "CREATE TABLE $table (
                    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT )"),
            'mysql'=>array(
                "CREATE TABLE $table (
                    id SERIAL
                ) DEFAULT CHARSET=utf8"),
            'pgsql'=>array(
                "CREATE TABLE $table (id SERIAL PRIMARY KEY)"),
            'mssql'=>array(
                "CREATE TABLE $table (id INT PRIMARY KEY);"
            ),
            'ibm'=>array(
                "CREATE TABLE $table (
                    id INTEGER AS IDENTITY NOT NULL, PRIMARY KEY(id));"
            ),
        );
        $query = $this->findQuery($cmd);
        if(!$query[0]) return false;
        return (!$this->exec($query[0]))?TRUE:FALSE;
    }

    /**
     * rename a table
     * @param $new_name
     * @return bool
     */
    public function renameTable($new_name) {
        if(preg_match('/odbc/',$this->backend)) {
            $this->exec("SELECT * INTO $new_name FROM $this->name");
            $this->dropTable();
            $this->name = $new_name;
            return true;
        } else {
            $cmd=array(
                'sqlite2?|pgsql'=>array(
                    "ALTER TABLE $this->name RENAME TO $new_name;"),
                'mysql|ibm'=>array(
                    "RENAME TABLE `$this->name` TO `$new_name`;"),
                'mssql|sybase|dblib'=>array(
                    "sp_rename $this->name, $new_name")
            );
            $query = $this->findQuery($cmd);
            if(!$query[0]) return false;
            $this->name = $new_name;
            return (!$this->exec($query[0]))?TRUE:FALSE;
        }
    }

    /**
     * drop table
     * @param null $name
     * @return bool
     */
    public function dropTable($name = null) {
        $table = ($name)?$name:$this->name;
        $cmd=array(
            'mysql|sqlite2?|pgsql|mssql|sybase|dblib|ibm|odbc'=>array(
                "DROP TABLE IF EXISTS $table;"),
        );
        $query = $this->findQuery($cmd);
        if(!$query[0]) return false;
        return (!$this->exec($query[0]))?TRUE:FALSE;
    }

    /**
     * get columns of a table
     * @param bool $types
     * @return array
     */
    public function getCols($types = false) {
        if(empty($this->name)) trigger_error('No table specified.');
        $columns = array();
        $schema = $this->schema($this->name,60);
        foreach($schema['result'] as $cols) {
            if($types) {
                $columns[$cols[$schema['field']]] = array(
                    'type'=>$cols[$schema['type']],
                    'null'=>($cols[$schema['nullname']] == $schema['nullval'])?true:false,
                    //'default'=>$cols['Default'], // TODO: add defaults, sqlite: dflt_value
                    //'extra'=>$cols['Extra'], // TODO: add auto increment
                );
            } else
                $columns[] = $cols[$schema['field']];
        }
        return $columns;
    }

    /**
     * add a column to a table
     * @param $column
     * @param $type
     * @param bool $nullable
     * @param bool $passThrough
     * @return bool
     */
    public function addCol($column,$type,$nullable = true,$passThrough = false) {
        // check if column is already existing
        if(in_array($column,$this->getCols())) return false;
        //prepare columntypes
        if($passThrough == false) {
            if(!array_key_exists(strtoupper($type),$this->dataTypes)){
                trigger_error(sprintf(self::TEXT_NoDatatype,strtoupper($type),$this->backend));
                return FALSE;
            }
            $type_val = $this->findQuery($this->dataTypes[strtoupper($type)]);
            if(!$type_val) return false;
        } else $type_val = $type;
        $null_cmd = ($nullable)?'NULL':'NOT NULL';
        $cmd=array(
            'sqlite2?'=>array(
                "ALTER TABLE `$this->name` ADD `$column` $type_val $default $null_cmd"),
            'mysql|pgsql|mssql|sybase|dblib|odbc'=>array(
                "ALTER TABLE $this->name ADD $column $type_val $null_cmd"),
            'ibm'=>array(
                "ALTER TABLE $this->name ADD COLUMN $column $type_val $null_cmd"),
        );
        $query = $this->findQuery($cmd);
        if(!$query[0]) return false;
        return (!$this->exec($query[0]))?TRUE:FALSE;
    }

    /**
     * removes a column from a table
     * @param $column
     * @return bool
     */
    public function dropCol($column) {
        if(preg_match('/sqlite2?/',$this->backend)) {
            // SQlite does not support drop column directly
            $newCols = array();
            $newCols = $this->getCols(true);
            // unset primary-key fields, TODO: support other PKs than ID and multiple PKs
            unset($newCols['id']);
            // unset drop field
            unset($newCols[$column]);
            $this->begin();
            $this->create($this->name.'_new',function($table) use($newCols) {
                // TODO: add PK fields
                foreach($newCols as $name => $col)
                    $table->addCol($name,$col['type'],$col['null'],true);
                $fields = (!empty($newCols))?', '.implode(', ',array_keys($newCols)):'';
                $table->exec('INSERT INTO '.$table->name.
                    ' SELECT id'.$fields.' FROM '.$table->name);
            });
            $tname = $this->name;
            $this->dropTable();
            $this->table($this->name.'_new',function($table) use($tname){
                $table->renameTable($tname);
            });
            $this->commit();
            return true;
        } else {
            $cmd=array(
                'mysql|mssql|sybase|dblib'=>array(
                    "ALTER TABLE `$this->name` DROP `$column`"),
                'pgsql|odbc|ibm'=>array(
                    "ALTER TABLE $this->name DROP COLUMN $column"),
            );
            $query = $this->findQuery($cmd);
            if(!$query[0]) return false;
            return (!$this->exec($query[0]))?TRUE:FALSE;
        }
    }

    /**
     * rename a column
     * @param $column
     * @param $column_new
     * @return bool
     */
    public function renameCol($column,$column_new) {
        if(preg_match('/sqlite2?/',$this->backend)) {
            // SQlite does not support rename column directly
            $newCols = array();
            $newCols = $this->getCols(true);
            // unset primary-key fields, TODO: support other PKs than ID and multiple PKs
            unset($newCols['id']);
            // rename column
            $newCols[$column_new] = $newCols[$column];
            unset($newCols[$column]);
            $this->begin();
            $oname = $this->name;
            $this->create($this->name.'_new',function($table) use($newCols,$oname) {
                foreach($newCols as $name => $col)
                    $table->addCol($name,$col['type'],$col['null'],true);
                // TODO: add PK fields here
                $new_fields = (!empty($newCols))?', '.implode(', ',array_keys($newCols)):'';
                $cur_fields = implode(', ',array_keys($table->getCols()));
                $table->exec('INSERT INTO '.$table->name.'(id'.$new_fields.') SELECT '.$cur_fields.' FROM '.$oname);
            });
            $tname = $this->name;
            $this->dropTable();
            $this->table($this->name.'_new',
                function($table) use($tname){ $table->renameTable($tname); });
            $this->commit();
            return true;
        } elseif(preg_match('/odbc/',$this->backend)) {
            // no rename column for odbc
            $colTypes = $this->getCols(true);
            $this->begin();
            $this->addCol($column,$colTypes[$column]['type'],$colTypes[$column]['null'],true);
            $this->exec("UPDATE $this->name SET $column_new = $column");
            $this->dropCol($column);
            $this->commit();
            return true;
        } else {
            $colTypes = $this->getCols(true);
            $cmd=array(
                'mysql'=>array(
                    "ALTER TABLE `$this->name` CHANGE `$column` `$column_new` ".$colTypes[$column]['type']),
                'pgsql|ibm'=>array(
                    "ALTER TABLE $this->name RENAME COLUMN $column TO $column_new"),
                'mssql|sybase|dblib'=>array(
                    "sp_rename $this->name.$column, $column_new"),
            );
            $query = $this->findQuery($cmd);
            if(!$query[0]) return false;
            return (!$this->exec($query[0]))?TRUE:FALSE;
        }
    }

}