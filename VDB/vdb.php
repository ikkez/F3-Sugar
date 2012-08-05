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
        @version 0.4.1
 **/

class VDB extends DB {

    private
        $dataTypes = array(
            'mysql' => array(
                'BOOL'=>" SET('1') ",
                'BOOLEAN'=>" SET('1') ",
                'INT8'=>' TINYINT(3) UNSIGNED ',
                'INT32'=>' INT(11) UNSIGNED ',
                'FLOAT'=>' DOUBLE ',
                'DOUBLE'=>' DOUBLE ',
                'TEXT8'=>' VARCHAR(255) ',
                'TEXT16'=>' TEXT ',
                'TEXT32'=>' LONGTEXT ',
                'SPECIAL_DATE'=>' DATE ',
                'SPECIAL_DATETIME'=>' DATETIME ',
            ),
            'sqlite2?' => array(
                'BOOL'=>" BOOLEAN ",
                'BOOLEAN'=>" BOOLEAN ",
                'INT8'=>' INTEGER ',
                'INT32'=>' INTEGER ',
                'FLOAT'=>' DOUBLE ',
                'DOUBLE'=>' DOUBLE ',
                'TEXT8'=>' VARCHAR(255) ',
                'TEXT16'=>' TEXT ',
                'TEXT32'=>' TEXT ',
                'SPECIAL_DATE'=>' DATE ',
                'SPECIAL_DATETIME'=>' DATETIME ',
            ),
            'pgsql' => array(
                'BOOL'=>" text ",
                'BOOLEAN'=>" text ",
                'INT8'=>' integer ',
                'INT32'=>' integer ',
                'FLOAT'=>' double precision ',
                'DOUBLE'=>' double precision ',
                'TEXT8'=>' text ',
                'TEXT16'=>' text ',
                'TEXT32'=>' text ',
                'SPECIAL_DATE'=>' date ',
                'SPECIAL_DATETIME'=>' timestamp without time zone ',
            ),
        );

    const
        TEXT_NoDatatype='The specified datatype %s is not defined in %s driver';

    /**
     * get all tables of current DB
     * @return bool|array list of tables, or false
     */
    function getTables(){
        $cmd=array(
            'mysql'=>array(
                "show tables"),
            'sqlite2?'=>array(
                "SELECT name FROM sqlite_master WHERE type='table' AND name!='sqlite_sequence'"),
            //TODO: check if that's working
            'mssql|pgsql|sybase|dblib|ibm|odbc'=>array(
                "select table_name from information_schema.tables where table_schema = 'public'")
        );
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
        $tables = $this->exec($val[0]);
        if ($tables && is_array($tables) && count($tables)>0)
            foreach ($tables as &$table)
                $table = array_shift($table);
        return $tables;
    }

    /**
     * get columns of a table
     * @param $table
     * @param bool $types
     * @return array
     */
    public function getCols($table,$types = false) {
        $columns = array();
        $schema = $this->schema($table,60);
        foreach($schema['result'] as $cols) {
            if($types)  $columns[$cols[$schema['field']]] = $cols[$schema['type']];
            else        $columns[] = $cols[$schema['field']];
        }
        return $columns;
    }

    /**
     * add a column to a table
     * @param $table
     * @param $column
     * @param $type
     * @param bool $passThrough
     * @return bool
     */
    function addCol($table,$column,$type,$passThrough = false) {
        // check if column is already existing
        if(in_array($column,$this->getCols($table))) return false;

        //prepare columntypes
        if($passThrough == false) {
            $match=FALSE;
            foreach ($this->dataTypes as $backend=>$types)
                if (preg_match('/'.$backend.'/',$this->backend)) {
                    $match=TRUE;
                    break;
                }
            if (!$match) {
                trigger_error(self::TEXT_DBEngine);
                return FALSE;
            }
            if(array_key_exists(strtoupper($type),$types))
                $type = $types[strtoupper($type)];
            else {
                trigger_error(sprintf(self::TEXT_NoDatatype,strtoupper($type),$this->backend));
                return FALSE;
            }
        }
        $cmd=array(
            'sqlite2?'=>array(
                "ALTER TABLE `$table` ADD `$column` $type"),
            'mysql|pgsql'=>array(
                "ALTER TABLE $table ADD $column $type"),
            //TODO: complete that
            /*'mssql|sybase|dblib|ibm|odbc'=>array(
                "")*/
        );
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
        return (!$this->exec($val[0]))?TRUE:FALSE;
    }

    /**
     * removes a column from a table
     * @param $table
     * @param $column
     * @return bool
     */
    public function removeCol($table,$column) {

        if(preg_match('/sqlite2?/',$this->backend)) {
            // SQlite does not support drop column directly
            $newCols = array();
            $schema = $this->schema($table,60);
            foreach($schema['result'] as $col)
                if(!in_array($column,$col) && $col[$schema['field']] != 'id')
                    $newCols[$col[$schema['field']]] = $col[$schema['type']];
            $this->begin();
            $this->createTable($table.'_new');
            foreach($newCols as $name => $type)
                $this->addCol($table.'_new',$name,$type,true);
            $fields = (!empty($newCols))?', '.implode(', ',array_keys($newCols)):'';
            $this->exec('INSERT INTO '.$table.'_new SELECT id'.$fields.' FROM '.$table);
            $this->dropTable($table);
            $this->renameTable($table.'_new',$table);
            $this->commit();
            return true;
        } else {
            $cmd=array(
                'mysql'=>array(
                    "ALTER TABLE `$table` DROP `$column` "),
                //TODO: complete that
                'mssql|sybase|dblib|pgsql|ibm|odbc'=>array(
                    "")
            );
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
            return (!$this->exec($val[0]))?TRUE:FALSE;
        }
    }

    /**
     * rename a table
     * @param $table_name
     * @param $new_name
     * @return bool
     */
    public function renameTable($table_name,$new_name) {
        $cmd=array(
            'sqlite2?|pgsql'=>array(
                "ALTER TABLE $table_name RENAME TO $new_name;"),
            'mysql'=>array(
                "RENAME TABLE `$table_name` TO `$new_name`;"),
            //TODO: complete that
            /*'mssql|sybase|dblib|ibm|odbc'=>array(
                "")*/
        );
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
        return (!$this->exec($val[0]))?TRUE:FALSE;
    }

    /**
     * drop table
     * @param $table
     * @return bool
     */
    public function dropTable($table) {
        $cmd=array(
            'mysql|sqlite2?'=>array(
                "DROP TABLE IF EXISTS $table;"),
            //TODO: complete that
            /*'pgsql|mssql|sybase|dblib|ibm|odbc'=>array(
                "")*/
        );
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
        return (!$this->exec($val[0]))?TRUE:FALSE;
    }

    /**
     * create a basic table, containing ID field
     * @param $table
     * @return bool
     */
    function createTable($table) {
        if(in_array($table,$this->getTables())) return true;
        $cmd=array(
            'sqlite2?'=>array(
                "CREATE TABLE $table (
                id INTEGER PRIMARY KEY AUTOINCREMENT )"),
            'mysql'=>array(
                "CREATE TABLE $table (
                id INT(11) NOT NULL AUTO_INCREMENT ,
                PRIMARY KEY ( id )
                ) DEFAULT CHARSET=utf8"),
            //TODO: check if that's working
            'mssql|sybase|dblib|pgsql|ibm|odbc'=>array(
                "CREATE TABLE $table (id SERIAL PRIMARY KEY)")
        );
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
        return (!$this->exec($val[0]))?TRUE:FALSE;
    }
}