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
    https://github.com/ikkez/F3-Sugar/tree/master/VDB

        @package DB
        @version 0.9.3

 **/

namespace DB\SQL;

class SchemaBuilder {

    public
        $dataTypes = array(
            'BOOLEAN'=>array(       'mysql|sqlite2?'=>'BOOLEAN',
                                    'pgsql'=>'text',
                                    'mssql|sybase|dblib|odbc|sqlsrv'=>'bit',
                                    'ibm'=>'numeric(1,0)',
            ),
            'INT8'=>array(          'mysql'=>'TINYINT(3)',
                                    'sqlite2?|pgsql'=>'integer',
                                    'mssql|sybase|dblib|odbc|sqlsrv'=>'tinyint',
                                    'ibm'=>'smallint',
            ),
            'INT16'=>array(         'sqlite2?|pgsql|sybase|odbc|sqlsrv|imb'=>'integer',
                                    'mysql|mssql|dblib'=>'int',
            ),
            'INT32'=>array(         'sqlite2?|pgsql'=>'integer',
                                    'mysql|mssql|sybase|dblib|odbc|sqlsrv|imb'=>'bigint',
            ),
            'FLOAT'=>array(         'mysql|sqlite2?'=>'FLOAT',
                                    'pgsql'=>'double precision',
                                    'mssql|sybase|dblib|odbc|sqlsrv'=>'float',
                                    'imb'=>'decfloat'
            ),
            'DOUBLE'=>array(        'mysql|sqlite2?|ibm'=>'DOUBLE',
                                    'pgsql|sybase|odbc|sqlsrv'=>'double precision',
                                    'mssql|dblib'=>'decimal',
            ),
            'TEXT8'=>array(         'mysql|sqlite2?|ibm'=>'VARCHAR(255)',
                                    'pgsql'=>'text',
                                    'mssql|sybase|dblib|odbc|sqlsrv'=>'char(255)',
            ),
            'TEXT16'=>array(        'mysql|sqlite2?|pgsql|mssql|sybase|dblib|odbc|sqlsrv'=>'text',
                                    'ibm'=>'BLOB SUB_TYPE TEXT',
            ),
            'TEXT32'=>array(        'mysql'=>'LONGTEXT',
                                    'sqlite2?|pgsql|mssql|sybase|dblib|odbc|sqlsrv'=>'text',
                                    'ibm'=>'CLOB(2000000000)',
            ),
            'DATE'=>array(          'mysql|sqlite2?|pgsql|mssql|sybase|dblib|odbc|sqlsrv|ibm'=>'date',
            ),
            'DATETIME'=>array(      'pgsql'=>'timestamp without time zone',
                                    'mysql|sqlite2?|mssql|sybase|dblib|odbc|sqlsrv'=>'datetime',
                                    'ibm'=>'timestamp',
            ),
        );

    public
        $name;

	/** @var \DB\SQL */
	protected $db;

	/** @var \Base */
	protected $fw;

    const
        TEXT_NoDatatype='The specified datatype %s is not defined in %s driver',
        TEXT_NotNullFieldNeedsDefault='You cannot add the not nullable column `%s´ without specifying a default value';


	public function __construct(\DB\SQL $db) {
		$this->db = $db;
		$this->fw = \Base::instance();
	}

	/**
     * alter table operation stack wrapper
     * @param $name
     * @param $func
     * @return mixed
     */
    public function table($name,$func) {

//        if(!$this->fw->valid($name)) return false;
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
//        if(!self::valid($name)) return false;
        $this->createTable($name);
        return $this->table($name,$func);
    }

    /**
     * parse command array and return backend specific query
     * @param $cmd
     * @return bool
     */
    private function findQuery($cmd) {
        $match=FALSE;
        foreach ($cmd as $backend=>$val)
            if (preg_match('/'.$backend.'/',$this->db->driver())) {
                $match=TRUE;
                break;
            }
        if (!$match) {
            trigger_error('DB Engine not supported');
            return FALSE;
        }
        return $val;
    }

    /**
     * execute query stack
     * @param $query
     * @return bool
     */
    private function execQuerys($query) {
        if($this->db->exec($query) === false) return false;
        return true;
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
        $tables = $this->db->exec($query[0]);
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
                "CREATE TABLE `$table` (
                    id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT
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
	    return $this->execQuerys($query);
    }


    /**
     * set primary keys
     * @param $pks array
     * @return bool
     */
    public function setPKs($pks) {
        $pk_string = implode(', ',$pks);

        if(preg_match('/sqlite2?/',$this->db->driver())) {
            // collect primary key field information
            $colTypes = $this->getCols(true);
            $pk_def = array();
            foreach($pks as $name) {
                $dfv = $colTypes[$name]['default'];
                $df_def = ($dfv !== false) ? " DEFAULT '".$dfv."'" : '';
                $pk_def[] = $name.' '.$colTypes[$name]['type'].$df_def;
            }
            $pk_def = implode(', ',$pk_def);
            // fetch all non primary key fields
            $newCols = array();
            foreach ( $colTypes as $colname => $conf)
                if(!in_array($colname,$pks)) $newCols[$colname] = $conf;

            $this->db->begin();
            $oname = $this->name;
            // rename to temp
            $this->table($this->name, function($table){
                $table->renameTable($table->name.'_temp');
            });
            // create new origin table, with new private keys and their fields
            $this->db->exec("CREATE TABLE $oname ( $pk_def, PRIMARY KEY ($pk_string) );");
            // create insert trigger to work-a-round autoincrement in multiple primary keys
            // is set on first PK if it's an int field, // TODO: remove trigger if exists before?
            if(strstr(strtolower($colTypes[$pks[0]]['type']),'int'))
                $this->db->exec('CREATE TRIGGER '.$oname.'_insert AFTER INSERT ON '.$oname.
                            ' WHEN (NEW.'.$pks[0].' IS NULL) BEGIN'.
                                ' UPDATE '.$oname.' SET '.$pks[0].' = ('.
                                    ' select coalesce( max( '.$pks[0].' ), 0 ) + 1 from '.$oname.
                                ') WHERE ROWID = NEW.ROWID;'.
                            ' END;');
            // add non-pk fields and import all data
            $this->table($oname,function($table) use($newCols) {
                foreach($newCols as $name => $col)
                    $table->addCol($name,$col['type'],$col['null'],$col['default'],true);
                $fields = implode(', ',$table->getCols());
                $table->db->exec('INSERT INTO '.$table->name.'('.$fields.') SELECT '.$fields.' FROM '.$table->name.'_temp');
                // TODO: add check if the data really has been copied, before dropping old table.
            });
            // drop old table
            $this->dropTable($oname.'_temp');
            $this->db->commit();
            return true;

        } else {
            $cmd=array(
                'sybase|dblib|odbc'=>array(
                    "CREATE INDEX ".$this->name."_pkey ON ".$this->name." ( $pk_string );"
                ),
                'mysql'=>array(
                    "ALTER TABLE $this->name DROP PRIMARY KEY, ADD PRIMARY KEY ( $pk_string );"),
                'pgsql'=>array(
                    "ALTER TABLE $this->name DROP CONSTRAINT ".$this->name.'_pkey;',
                    "ALTER TABLE $this->name ADD CONSTRAINT ".$this->name."_pkey PRIMARY KEY ( $pk_string );",
                ),
                'mssql'=>array(
                    ""),
                'ibm'=>array(
                    ""),
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
    public function renameTable($new_name) {
        if(preg_match('/odbc/',$this->db->driver())) {
            $this->db->exec("SELECT * INTO $new_name FROM $this->name");
            $this->dropTable();
            $this->name = $new_name;
            return true;
        } else {
            $cmd=array(
                'sqlite2?|pgsql'=>array(
                    "ALTER TABLE $this->name RENAME TO $new_name;"),
                'mysql'=>array(
                    "RENAME TABLE `$this->name` TO `$new_name`;"),
                'ibm'=>array(
                    "RENAME TABLE $this->name TO $new_name;"),
                'mssql|sybase|dblib'=>array(
                    "sp_rename $this->name, $new_name")
            );
            $query = $this->findQuery($cmd);
            if(!$query[0]) return false;
            $this->name = $new_name;
            return $this->execQuerys($query);
        }
    }

    /**
     * drop table
     * @param null $name
     * @return bool
     */
    public function dropTable($name = null) {
        $table = ($name)?$name:$this->name;
//        if(!self::valid($table)) return false;
        $cmd=array(
            'mysql|sqlite2?|pgsql|mssql|sybase|dblib|ibm|odbc'=>array(
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
    public function getCols($types = false) {
        if(empty($this->name)) trigger_error('No table specified.');
        $columns = array();
        $schema = $this->db->schema($this->name,0);
        foreach($schema as $name => $cols) {
            if($types) {
                $default = $cols['default'];
                // remove single-qoutes in sqlite
                if(preg_match('/sqlite2?/',$this->db->driver()))
                    $default=substr($default,1,-1);
                // extract value from character_data in postgre
                if(preg_match('/pgsql/',$this->db->driver()) && !is_null($default))
                    if(is_int(strpos($default,'nextval')))
                        $default=null; // set autoincrement defaults to null
                    elseif(preg_match("/\'(.*)\'/",$default,$match))
                        $default = $match[1];
                $columns[$name] = array(
                    // 'type'=>$cols['type'], // TODO: this is no longer the real returned type from db :-/
                    'type'=>$cols['type_raw'], // need to add this to the schema method
                    'null'=>$cols['nullable'],
                    'default'=>$default,
                    'primary'=>$cols['pkey'],
                );
            } else
                $columns[] = $name;
        }
        return $columns;
    }

    /**
     * add a column to a table
     * @param string $column name
     * @param string $type datatype definition
     * @param bool $nullable allow NULL values
     * @param bool|mixed $default default insert value
     * @param bool $passThrough
     * @return bool
     */
    public function addCol($column,$type,$nullable = true,$default = false,$passThrough = false) {
        // check if column is already existing
        if(in_array($column,$this->getCols())) return false;
        // prepare columntypes
        if($passThrough == false) {
            if(!array_key_exists(strtoupper($type),$this->dataTypes)){
                trigger_error(sprintf(self::TEXT_NoDatatype,strtoupper($type),$this->db->driver()));
                return FALSE;
            }
            $type_val = $this->findQuery($this->dataTypes[strtoupper($type)]);
            if(!$type_val) return false;
        } else $type_val = $type;
        $null_cmd = ($nullable) ? 'NULL' : 'NOT NULL';
        $def_cmds = array(
            'sqlite2?|mysql|pgsql|mssql|sybase|dblib|odbc'=>'DEFAULT',
            'ibm'=>'WITH DEFAULT',
        );
        // not nullable fields should have a default value [SQlite]
        if($default === false && $nullable === false)
            trigger_error(sprintf(self::TEXT_NotNullFieldNeedsDefault,$column));
        else
            $def_cmd = ($default !== false) ?
                $this->findQuery($def_cmds).' '.
                    "'".htmlspecialchars($default,ENT_QUOTES,$this->fw->get('ENCODING'))."'" : '';
        $cmd=array(
            'mysql|sqlite2?'=>array(
                "ALTER TABLE `$this->name` ADD `$column` $type_val $null_cmd $def_cmd"),
            'pgsql|mssql|sybase|dblib|odbc'=>array(
                "ALTER TABLE $this->name ADD $column $type_val $null_cmd $def_cmd"),
            'ibm'=>array(
                "ALTER TABLE $this->name ADD COLUMN $column $type_val $null_cmd $def_cmd"),
        );
        $query = $this->findQuery($cmd);
        return $this->execQuerys($query);
    }

    /**
     * removes a column from a table
     * @param $column
     * @return bool
     */
    public function dropCol($column) {
        $colTypes = $this->getCols(true);
        // check if column exists
        if(!in_array($column,array_keys($colTypes))) return true;
        if(preg_match('/sqlite2?/',$this->db->driver())) {
            // SQlite does not support drop column directly
            // unset primary-key fields, TODO: support other PKs than ID and multiple PKs
            unset($colTypes['id']);
            // unset drop field
            unset($colTypes[$column]);
            $this->db->begin();
            $this->create($this->name.'_new',function($table) use($colTypes) {
                // TODO: add PK fields
                foreach($colTypes as $name => $col)
                    $table->addCol($name,$col['type'],$col['null'],$col['default'],true);
                $fields = (!empty($colTypes)) ? ', '.implode(', ',array_keys($colTypes)) : '';
                $table->db->exec('INSERT INTO `'.$table->name.'` '.
                    'SELECT id'.$fields.' FROM '.$table->name);
            });
            $tname = $this->name;
            $this->dropTable();
            $this->table($this->name.'_new',function($table) use($tname){
                $table->renameTable($tname);
            });
            $this->db->commit();
            return true;
        } else {
            $cmd=array(
                'mysql|mssql|sybase|dblib'=>array(
                    "ALTER TABLE $this->name DROP $column"),
                'pgsql|odbc|ibm'=>array(
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
    public function renameCol($column,$column_new) {
        $colTypes = $cur_fields = $this->getCols(true);
        // check if column is already existing
        if(!in_array($column,array_keys($colTypes))) return false;
        if(preg_match('/sqlite2?/',$this->db->driver())) {
            // SQlite does not support drop or rename column directly
            // unset primary-key fields, TODO: support other PKs than ID and multiple PKs
            unset($colTypes['id']);
            // rename column
            $colTypes[$column_new] = $colTypes[$column];
            unset($colTypes[$column]);
            $this->db->begin();
            $oname = $this->name;
            $this->create($this->name.'_new',function($table) use($colTypes,$cur_fields,$oname) {
                foreach($colTypes as $name => $col)
                    $table->addCol($name,$col['type'],$col['null'],$col['default'],true);
                // TODO: add PK fields here
                $new_fields = (!empty($colTypes)) ? ', '.implode(', ',array_keys($colTypes)) : '';
                $table->db->exec('INSERT INTO `'.$table->name.'`(id'.$new_fields.') '.
                             'SELECT '.implode(', ',array_keys($cur_fields)).' FROM '.$oname);
            });
            $tname = $this->name;
            $this->dropTable();
            $this->table($this->name.'_new',
                function($table) use($tname){ $table->renameTable($tname); });
            $this->db->commit();
            return true;
        } elseif(preg_match('/odbc/',$this->db->driver())) {
            // no rename column for odbc
            $this->db->begin();
            $this->table($this->name,function($table) use($column, $colTypes){
                $table->addCol($column.'_new',$colTypes[$column]['type'],$colTypes[$column]['null'],$colTypes[$column]['default'],true);
            });
            $this->db->exec("UPDATE $this->name SET $column_new = $column");
            $this->dropCol($column);
            $this->db->commit();
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
            return $this->execQuerys($query);
        }
    }
}