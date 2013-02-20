<?php

/**
    SQL Table Schema Builder extension for the PHP Fat-Free Framework

    The contents of this file are subject to the terms of the GNU General
    Public License Version 3.0. You may not use this file except in
    compliance with the license. Any of the license terms and conditions
    can be waived if you get permission from the copyright holder.

    crafted by   __ __     __
                |__|  |--.|  |--.-----.-----.
                |  |    < |    <|  -__|-- __|
                |__|__|__||__|__|_____|_____|

    Copyright (c) 2012 by ikkez
    Christian Knuth <ikkez0n3@gmail.com>
    https://github.com/ikkez/F3-Sugar/

        @package DB
        @version 1.2.1
 **/


namespace DB\SQL;

class Schema {

    public
        $dataTypes = array(
            'BOOLEAN' =>    array('mysql|sqlite2?' => 'BOOLEAN',
                                  'pgsql' => 'text',
                                  'mssql|sybase|dblib|odbc|sqlsrv' => 'bit',
                                  'ibm' => 'numeric(1,0)',
            ),
            'INT8' =>       array('mysql' => 'TINYINT(3)',
                                  'sqlite2?|pgsql' => 'integer',
                                  'mssql|sybase|dblib|odbc|sqlsrv' => 'tinyint',
                                  'ibm' => 'smallint',
            ),
            'INT16' =>      array('sqlite2?|pgsql|sybase|odbc|sqlsrv|imb' => 'integer',
                                  'mysql|mssql|dblib' => 'int',
            ),
            'INT32' =>      array('sqlite2?|pgsql' => 'integer',
                                  'mysql|mssql|sybase|dblib|odbc|sqlsrv|imb' => 'bigint',
            ),
            'FLOAT' =>      array('mysql|sqlite2?' => 'FLOAT',
                                  'pgsql' => 'double precision',
                                  'mssql|sybase|dblib|odbc|sqlsrv' => 'float',
                                  'imb' => 'decfloat'
            ),
            'DOUBLE' =>     array('mysql|sqlite2?|ibm' => 'DOUBLE',
                                  'pgsql|sybase|odbc|sqlsrv' => 'double precision',
                                  'mssql|dblib' => 'decimal',
            ),
            'TEXT8' =>      array('mysql|sqlite2?|ibm' => 'VARCHAR(255)',
                                  'pgsql' => 'text',
                                  'mssql|sybase|dblib|odbc|sqlsrv' => 'char(255)',
            ),
            'TEXT16' =>     array('mysql|sqlite2?|pgsql|mssql|sybase|dblib|odbc|sqlsrv' => 'text',
                                  'ibm' => 'BLOB SUB_TYPE TEXT',
            ),
            'TEXT32' =>     array('mysql' => 'LONGTEXT',
                                  'sqlite2?|pgsql|mssql|sybase|dblib|odbc|sqlsrv' => 'text',
                                  'ibm' => 'CLOB(2000000000)',
            ),
            'DATE' =>       array('mysql|sqlite2?|pgsql|mssql|sybase|dblib|odbc|sqlsrv|ibm' => 'date',
            ),
            'DATETIME' =>  array('pgsql' => 'timestamp without time zone',
                                 'mysql|sqlite2?|mssql|sybase|dblib|odbc|sqlsrv' => 'datetime',
                                 'ibm' => 'timestamp',
            ),
            'TIMESTAMP' => array('mysql|dblib|sqlsrv|ibm' => 'timestamp',
                                 'pgsql|odbc' => 'timestamp without time zone',
                                 'sqlite2?|mssql|sybase'=>'DATETIME',
            ),
            'BLOB' =>      array('mysql|odbc|sqlite2?|ibm' => 'blob',
                                 'pgsql' => 'bytea',
                                 'mssql|sybase|dblib' => 'image',
                                 'sqlsrv' => 'varbinary(max)',
            ),
        ),
        $defaultTypes = array(
            'CUR_STAMP' => array(
                'mysql|mssql|sybase|dblib|odbc|sqlsrv' => 'CURRENT_TIMESTAMP',
                'pgsql' => 'LOCALTIMESTAMP(0)',
                'sqlite2?' => "(datetime('now','localtime'))",
            ),
        );

    public
        $name;

    /** @var \DB\SQL */
    protected $db;

    /** @var \Base */
    protected $fw;

    const
        // DataTypes
        DT_BOOL = 'BOOLEAN',
        DT_BOOLEAN = 'BOOLEAN',
        DT_INT8 = 'INT8',
        DT_TINYINT = 'INT8',
        DT_INT16 = 'INT16',
        DT_INT = 'INT16',
        DT_INT32 = 'INT32',
        DT_BIGINT = 'INT32',
        DT_FLOAT = 'FLOAT',
        DT_DOUBLE = 'DOUBLE',
        DT_DECIMAL = 'DOUBLE',
        DT_TEXT8 = 'TEXT8',
        DT_VARCHAR = 'TEXT8',
        DT_TEXT16 = 'TEXT16',
        DT_TEXT = 'TEXT16',
        DT_TEXT32 = 'TEXT32',
        DT_DATE = 'DATE',
        DT_DATETIME = 'DATETIME',
        DT_TIMESTAMP = 'TIMESTAMP',
        DT_BLOB = 'BLOB',
        DT_BINARY = 'BLOB',

        // column default values
        DF_CURRENT_TIMESTAMP = 'CUR_STAMP',

        // error messages
        TEXT_NoDataType = 'The specified datatype %s is not defined in %s driver',
        TEXT_NotNullFieldNeedsDefault = 'You cannot add the not nullable column `%s´ without specifying a default value',
        TEXT_IllegalName='%s is not a valid table or column name',
        TEXT_CurrentStampDataType = 'Current timestamp as column default is only possible for TIMESTAMP datatype',
        TEXT_ENGINE_NOT_SUPPORTED = 'DB Engine `%s´ is not supported for this action.';

    public function __construct(\DB\SQL $db)
    {
        $this->db = $db;
        $this->fw = \Base::instance();
    }

    /**
     * parse command array and return backend specific query
     * @param $cmd array
     * @return bool|string
     **/
    private function findQuery($cmd)
    {
        $match = FALSE;
        foreach ($cmd as $backend => $val)
            if (preg_match('/'.$backend.'/', $this->db->driver())) {
                $match = TRUE;
                break;
            }
        if (!$match) {
            trigger_error(sprintf(self::TEXT_ENGINE_NOT_SUPPORTED, $this->db->driver()));
            return FALSE;
        }
        return $val;
    }

    /**
     * execute query stack
     * @param $query
     * @return bool
     */
    private function execQuerys($query)
    {
        if ($this->db->exec($query) === false) return false;
        return true;
    }

    /**
     * check if valid table / column name
     * @param string $key
     * @return bool
     */
    public function valid($key)
    {
        if (preg_match('/^(\D\w+(?:\_\w+)*)$/', $key))
            return TRUE;
        // Invalid name
        trigger_error(sprintf(self::TEXT_IllegalName, var_export($key, TRUE)));
        return FALSE;
    }

    /**
     * get a list of all databases
     * @return array|bool
     */
    public function getDatabases() {
        $cmd = array(
            'mysql' => 'SHOW DATABASES',
            'pgsql' => 'SELECT datname FROM pg_catalog.pg_database',
            'mssql|sybase|dblib|sqlsrv' => 'EXEC SP_HELPDB',
        );
        $query = $this->findQuery($cmd);
        if (!$query) return false;
        $result = $this->db->exec($query);
        foreach($result as &$db)
            if(is_array($db)) $db = array_shift($db);
        return $result;
    }

    /**
     * get all tables of current DB
     * @return bool|array list of tables, or false
     */
    public function getTables()
    {
        $cmd = array(
            'mysql' => array(
                "show tables"),
            'sqlite2?' => array(
                "SELECT name FROM sqlite_master WHERE type='table' AND name!='sqlite_sequence'"),
            'mssql|sqlsrv|pgsql|sybase|dblib|odbc' => array(
                "select table_name from information_schema.tables where table_schema = 'public'"),
            'ibm' => array("select TABLE_NAME from sysibm.tables"),
        );
        $query = $this->findQuery($cmd);
        if (!$query[0]) return false;
        $tables = $this->db->exec($query[0]);
        if ($tables && is_array($tables) && count($tables) > 0)
            foreach ($tables as &$table)
                $table = array_shift($table);
        return $tables;
    }

    /**
     * create a basic table, containing required ID serial field
     * @param $name
     * @return bool|SchemaBuilder
     */
    public function createTable($name)
    {
        if (!$this->valid($name)) return false;
        if (in_array($name, $this->getTables())) return false;
        $cmd = array(
            'sqlite2?|sybase|dblib|odbc' => array(
                "CREATE TABLE $name (id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT )"),
            'mysql' => array(
                "CREATE TABLE `$name` (id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT)
                DEFAULT CHARSET=utf8"),
            'pgsql' => array(
                "CREATE TABLE $name (id SERIAL PRIMARY KEY)"),
            'mssql' => array(
                "CREATE TABLE $name (id INT PRIMARY KEY);"
            ),
            'ibm' => array(
                "CREATE TABLE $name (id INTEGER AS IDENTITY NOT NULL, PRIMARY KEY(id));"
            ),
        );
        $query = $this->findQuery($cmd);
        $this->execQuerys($query);
        $this->alterTable($name);
        return $this;
    }

    /**
     * select table for altering operations
     *
     * @param $name
     * @return mixed
     */
    public function alterTable($name)
    {
    if(!$this->valid($name)) return false;
        $this->name = $name;
        return $this;
    }

    /**
     * set primary keys
     * @param $pks array
     * @return bool
     */
    public function setPKs($pks)
    {
        $pk_string = implode(', ', $pks);
        if (preg_match('/sqlite2?/', $this->db->driver())) {
            $this->db->exec('DROP TRIGGER IF EXISTS '.$this->name.'_insert');
            // collect primary key field information
            $colTypes = $this->getCols(true);
            $pk_def = array();
            foreach ($pks as $name) {
                $dfv = $colTypes[$name]['default'];
                $df_def = ($dfv !== false) ? " DEFAULT '".$dfv."'" : '';
                $pk_def[] = $name.' '.$colTypes[$name]['type'].$df_def;
            }
            $currentPKs = array();
            foreach ($colTypes as $colname => $conf)
                if ($conf['pkey']) $currentPKs[] = $colname;
            // from comp to single pkey
            if (count($pks) <= 1 && count($currentPKs) >= 1) {
                // already the right pk
                if ((count($pks) == 1 && count($currentPKs) == 1) && $pks[0] == $currentPKs[0])
                    return true;
                // rebuild with single pk
                $oname = $this->name;
                $this->renameTable($this->name.'_temp');
                $this->createTable($oname);
                foreach ($colTypes as $name => $col)
                    $this->addColumn($name, $col['type'], $col['nullable'], $col['default'], true);
                $fields = implode(', ', array_keys($colTypes));
                $this->db->exec('INSERT INTO '.$this->name.'('.$fields.') '.
                                'SELECT '.$fields.' FROM '.$this->name.'_temp');
                // drop old table
                $this->dropTable($this->name.'_temp');
                return true;
            }
            $pk_def = implode(', ', $pk_def);
            // fetch all new non primary key fields
            $newCols = array();
            foreach ($colTypes as $colname => $conf)
                if (!in_array($colname, $pks)) $newCols[$colname] = $conf;
            if(!$this->db->inTransaction())
                $this->db->begin();
            $oname = $this->name;
            // rename to temp
            $this->renameTable($this->name.'_temp');
            // find dynamic defaults
            foreach($newCols as $name=>$col)
                if($col['default'] == \DF::CURRENT_TIMESTAMP) $dyndef[$name] = $col;
            $dynfields = '';
            if(!empty($dyndef))
                foreach($dyndef as $n=>$col) {
                    $dynfields.= $n.' '.$col['type'].' '.(($col['nullable']) ? 'NULL' : 'NOT NULL').
                        ' DEFAULT '.$this->findQuery($this->defaultTypes[$col['default']]).',';
                    unset($newCols[$n]);
                }
            // create new origin table, with new private keys and their fields
            $this->db->exec("CREATE TABLE $oname ( $pk_def, $dynfields PRIMARY KEY ($pk_string) );");
            // add non-pk fields
            $this->alterTable($oname);
            foreach ($newCols as $name => $col)
                $this->addColumn($name, $col['type'], $col['nullable'], $col['default'], true);
            // create insert trigger to work-a-round autoincrement in multiple primary keys
            // is set on first PK if it's an int field
            if (strstr(strtolower($colTypes[$pks[0]]['type']), 'int'))
                $this->db->exec('CREATE TRIGGER '.$oname.'_insert AFTER INSERT ON '.$oname.
                                ' WHEN (NEW.'.$pks[0].' IS NULL) BEGIN'.
                                ' UPDATE '.$oname.' SET '.$pks[0].' = ('.
                                ' select coalesce( max( '.$pks[0].' ), 0 ) + 1 from '.$oname.
                                ') WHERE ROWID = NEW.ROWID;'.
                                ' END;');
            // import all data
            $cols = $this->getCols();
            $fields = implode(', ', $cols);
            $this->db->exec('INSERT INTO '.$oname.'('.$fields.') '.
                      'SELECT '.$fields.' FROM '.$oname.'_temp');
            // drop old table
            $this->dropTable($oname.'_temp');
            if (!$this->db->inTransaction())
                $this->db->commit();
            return true;

        } else {
            $cmd = array(
                'mssql|sybase|dblib|odbc' => array(
                    "CREATE INDEX ".$this->name."_pkey ON ".$this->name." ( $pk_string );"
                ),
                'mysql' => array(
                    "ALTER TABLE $this->name DROP PRIMARY KEY, ADD PRIMARY KEY ( $pk_string );"),
                'pgsql' => array(
                    "ALTER TABLE $this->name DROP CONSTRAINT ".$this->name.'_pkey;',
                    "ALTER TABLE $this->name ADD CONSTRAINT ".$this->name."_pkey PRIMARY KEY ( $pk_string );",
                ),
            );
            $query = $this->findQuery($cmd);
            return $this->execQuerys($query);
        }
    }

    /**
     * rename a table
     * @param $new_name
     * @return bool
     */
    public function renameTable($new_name)
    {
        if (!$this->valid($new_name)) return false;
        if (preg_match('/odbc/', $this->db->driver())) {
            $this->db->exec("SELECT * INTO $new_name FROM $this->name");
            $this->dropTable();
            $this->name = $new_name;
            return true;
        } else {
            $cmd = array(
                'sqlite2?|pgsql' => array(
                    "ALTER TABLE $this->name RENAME TO $new_name;"),
                'mysql' => array(
                    "RENAME TABLE `$this->name` TO `$new_name`;"),
                'ibm' => array(
                    "RENAME TABLE $this->name TO $new_name;"),
                'mssql|sybase|dblib' => array(
                    "sp_rename $this->name, $new_name")
            );
            $query = $this->findQuery($cmd);
            if (!$query[0]) return false;
            $this->name = $new_name;
            return $this->execQuerys($query);
        }
    }

    /**
     * drop table
     * @param null $name
     * @return bool
     */
    public function dropTable($name = null)
    {
        $table = ($name) ? $name : $this->name;
        $cmd = array(
            'mysql|sqlite2?|pgsql|mssql|sybase|dblib|ibm|odbc' => array(
                "DROP TABLE IF EXISTS $table;"),
        );
        $query = $this->findQuery($cmd);
        return $this->execQuerys($query);
    }

    /**
     * get columns of a table
     * @param bool $types
     * @return array
     */
    public function getCols($types = false)
    {
        if (empty($this->name)) trigger_error('No table specified.');
        $columns = array();
        $schema = $this->db->schema($this->name, 0);
        if (!$types)
            return array_keys($schema);
        else
            foreach ($schema as $name => &$cols) {
                    $default = $cols['default'];
                    if(!is_null($default) && (
                        is_int(strpos($this->findQuery($this->defaultTypes['CUR_STAMP']),$default))
                        || $default == "('now'::text)::timestamp(0) without time zone")){
                        $default = 'CUR_STAMP';
                    } else {
                        // remove single-qoutes in sqlite
                        if (preg_match('/sqlite2?/', $this->db->driver()))
                            $default = substr($default, 1, -1);
                        // extract value from character_data in postgre
                        if (preg_match('/pgsql/', $this->db->driver()) && !is_null($default))
                            if (is_int(strpos($default, 'nextval')))
                                $default = null; // drop autoincrement default
                            elseif (preg_match("/\'(.*)\'/", $default, $match))
                                $default = $match[1];
                    }
                $cols['default'] = $default;
            }
        return $schema;
    }

    /**
     * add a column to a table
     * @param string     $name        name
     * @param string     $type        DataType definition
     * @param bool       $nullable    allow NULL values
     * @param bool|mixed $default     default insert value
     * @param bool       $passThrough don't match $type against DT array
     * @return bool
     */
    public function addColumn($name, $type, $nullable = true, $default = false, $passThrough = false)
    {
        if (!$this->valid($name)) return false;
        // check if column is already existing
        if (in_array($name, $this->getCols())) return false;
        // prepare column types
        if ($passThrough == false) {
            if (!array_key_exists(strtoupper($type), $this->dataTypes)) {
                trigger_error(sprintf(self::TEXT_NoDataType, strtoupper($type), $this->db->driver()));
                return FALSE;
            }
            $type_val = $this->findQuery($this->dataTypes[strtoupper($type)]);
            if (!$type_val) return false;
        } else $type_val = $type;
        $null_cmd = ($nullable) ? 'NULL' : 'NOT NULL';
        $def_cmds = array(
            'sqlite2?|mysql|pgsql|mssql|sybase|dblib|odbc' => 'DEFAULT',
            'ibm' => 'WITH DEFAULT',
        );
        // not nullable fields should have a default value [SQlite]
        if ($default === false && $nullable === false) {
            trigger_error(sprintf(self::TEXT_NotNullFieldNeedsDefault, $name));
            return false;
        }
        // default value
        if($default !== false) {
            $def_cmd = $this->findQuery($def_cmds).' ';
            if ($default === self::DF_CURRENT_TIMESTAMP) {
                // timestamp default
                $stamp_type = $this->findQuery($this->dataTypes['TIMESTAMP']);
                if ($type != 'TIMESTAMP' &&
                    ($passThrough && strtoupper($type) != strtoupper($stamp_type))
                ) {
                    trigger_error(self::TEXT_CurrentStampDataType);
                    return false;
                }
                if (preg_match('/sqlite2?/', $this->db->driver())) {
                    // sqlite: dynamic column default only works when rebuilding the table
                    $colTypes = $this->getCols(true);
                    // remember primary-key fields
                    foreach ($colTypes as $key => $col)
                        if ($col['pkey']) {
                            $pkeys[] = $key;
                            if ($key == 'id') unset($colTypes[$key]);
                        }
                    $oname = $this->name;
                    $this->renameTable($oname.'_temp_stamp');
                    $new = new self($this->db);
                    $new->db->exec('CREATE TABLE '.$oname.' ('.
                                   'id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,'.
                                   "$name $type_val $null_cmd DEFAULT ".
                                   $this->findQuery($this->defaultTypes[strtoupper($default)]).")");
                    $new->alterTable($oname);
                    foreach ($colTypes as $name => $col)
                        $new->addColumn($name, $col['type'], $col['nullable'], $col['default'], true);
                    $fields = !empty($colTypes) ? implode(', ', array_keys($colTypes)) : '';
                    $new->setPKs($pkeys);
                    if(!empty($fields))
                    $new->db->exec('INSERT INTO `'.$new->name.'` ('.$fields.') '.
                                   'SELECT '.$fields.' FROM `'.$this->name.'`;');
                    $this->dropTable();
                    $this->alterTable($oname);
                    return true;
                } else {
                    $def_cmd .= $this->findQuery($this->defaultTypes[strtoupper($default)]);
                }
            } else {
                // static default
                $pdo_type = preg_match('/int|bool/i', $type_val, $parts) ?
                    constant('\PDO::PARAM_'.strtoupper($parts[0])) : \PDO::PARAM_STR;
                $def_cmd .= $this->db->quote(htmlspecialchars($default, ENT_QUOTES,
                    $this->fw->get('ENCODING')), $pdo_type);
            }
        } else
            $def_cmd = '';
        $cmd = array(
            'mysql|sqlite2?' => array(
                "ALTER TABLE `$this->name` ADD `$name` $type_val $null_cmd $def_cmd"),
            'pgsql|mssql|sybase|dblib|odbc' => array(
                "ALTER TABLE $this->name ADD $name $type_val $null_cmd $def_cmd"),
            'ibm' => array(
                "ALTER TABLE $this->name ADD COLUMN $name $type_val $null_cmd $def_cmd"),
        );
        $query = $this->findQuery($cmd);
        return $this->execQuerys($query);
    }

    /**
     * removes a column from a table
     * @param $column
     * @return bool
     */
    public function dropColumn($column)
    {
        $colTypes = $this->getCols(true);
        // check if column exists
        if (!in_array($column, array_keys($colTypes))) return true;
        if (preg_match('/sqlite2?/', $this->db->driver())) {
            // SQlite does not support drop column directly
            // unset dropped field
            unset($colTypes[$column]);
            // remember primary-key fields
            foreach ($colTypes as $key => $col)
                if ($col['pkey']) {
                    $pkeys[] = $key;
                    if ($key == 'id') unset($colTypes[$key]);
                }
            $new = new self($this->db);
            if (!$new->db->inTransaction())
                $new->db->begin();
            $new->createTable($this->name.'_temp_drop');
            foreach ($colTypes as $name => $col)
                $new->addColumn($name, $col['type'], $col['nullable'], $col['default'], true);
            $fields = !empty($colTypes) ? ', '.implode(', ', array_keys($colTypes)) : '';
            $new->setPKs($pkeys);
            $new->db->exec('INSERT INTO `'.$new->name.'` '.
                'SELECT id'.$fields.' FROM '.$this->name);
            $tname = $this->name;
            $this->dropTable();
            $new->renameTable($tname);
            if (!$new->db->inTransaction())
                $new->db->commit();
            $this->alterTable($tname);
            return true;
        } else {
            $cmd = array(
                'mysql|mssql|sybase|dblib' => array(
                    "ALTER TABLE $this->name DROP $column"),
                'pgsql|odbc|ibm' => array(
                    "ALTER TABLE $this->name DROP COLUMN $column"),
            );
            $query = $this->findQuery($cmd);
            return $this->execQuerys($query);
        }
    }

    /**
     * rename a column
     * @param $column
     * @param $column_new
     * @return bool
     */
    public function renameColumn($column, $column_new)
    {
        if (!$this->valid($column_new)) return false;
        $colTypes = $cur_fields = $this->getCols(true);
        // check if column is already existing
        if (!in_array($column, array_keys($colTypes))) return false;
        if (preg_match('/sqlite2?/', $this->db->driver())) {
            // SQlite does not support drop or rename column directly
            // remind primary-key fields
            foreach ($colTypes as $key => $col)
                if ($col['pkey'] == true) {
                    $pkeys[] = (($key == $column) ? $column_new : $key);
                    if ($key == 'id') unset($colTypes[$key]);
                }
            $oname=$this->name;
            $this->renameTable($oname.'_temp_rename');
            $new = new self($this->db);
            if (!$new->db->inTransaction())
                $new->db->begin();
            $new->createTable($oname);
            foreach ($colTypes as $name => $col)
                $new->addColumn((($name == $column) ? $column_new : $name),
                    $col['type'], $col['nullable'], $col['default'], true);
            foreach (array_keys($colTypes) as $type)
                $new_fields[] = ', '.(($type == $column) ? $column_new : $type);
            $new->setPKs($pkeys);
            $new->db->exec('INSERT INTO `'.$new->name.'` ("id"'.implode($new_fields).') '.
                'SELECT "'.implode('", "', array_keys($cur_fields)).'" FROM `'.$this->name.'`;');
            $new->dropTable($this->name);
            if (!$new->db->inTransaction())
                $new->db->commit();
            $this->alterTable($oname);
            return true;
        } elseif (preg_match('/odbc/', $this->db->driver())) {
            // no rename column for odbc, create temp column
            if (!$this->db->inTransaction())
                $this->db->begin();
            $this->addColumn($column_new, $colTypes[$column]['type'],
                $colTypes[$column]['nullable'], $colTypes[$column]['default'], true);
            $this->db->exec("UPDATE $this->name SET $column_new = $column");
            $this->dropColumn($column);
            if (!$this->db->inTransaction())
                $this->db->commit();
            return true;
        } else {
            $colTypes = $this->getCols(true);
            $cmd = array(
                'mysql' => array(
                    "ALTER TABLE `$this->name` CHANGE `$column` `$column_new` ".$colTypes[$column]['type']),
                'pgsql|ibm' => array(
                    "ALTER TABLE $this->name RENAME COLUMN $column TO $column_new"),
                'mssql|sybase|dblib' => array(
                    "sp_rename $this->name.$column, $column_new"),
            );
            $query = $this->findQuery($cmd);
            return $this->execQuerys($query);
        }
    }
}