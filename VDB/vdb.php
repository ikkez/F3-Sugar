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
        @version 0.1.1
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
            'sqlite2' => array(
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
     * add a column to a table
     * @param $table
     * @param $column
     * @param $type
     * @return bool
     */
    function addCol($table,$column,$type) {
        // check if column is already existing
        $schema = $this->schema($table,60);
        foreach($schema['result'] as $col) {
            if(in_array($column,$col)) return false;
        }
        //prepare columntypes
        $match=FALSE;
        foreach ($this->dataTypes as $backend=>$val)
            if (preg_match('/'.$backend.'/',$this->backend)) {
                $match=TRUE;
                break;
            }
        if(array_key_exists(strtoupper($type),$val))
            $type = $val[strtoupper($type)];
        else {
            trigger_error(sprintf(self::TEXT_NoDatatype,strtoupper($type),$this->backend));
            return FALSE;
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
     * create a basic table, containing ID field
     * @param $table
     * @return bool
     */
    function createTable($table) {
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