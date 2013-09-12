<?php

/**
    Cortex - a general purpose mapper for the PHP Fat-Free Framework

    The contents of this file are subject to the terms of the GNU General
    Public License Version 3.0. You may not use this file except in
    compliance with the license. Any of the license terms and conditions
    can be waived if you get permission from the copyright holder.

    crafted by   __ __     __
                |__|  |--.|  |--.-----.-----.
                |  |    < |    <|  -__|-- __|
                |__|__|__||__|__|_____|_____|

    Copyright (c) 2013 by ikkez
    Christian Knuth <ikkez0n3@gmail.com>
    https://github.com/ikkez/F3-Sugar/

        @package DB
        @version 0.10.1
        @date 17.01.2013
 **/

namespace DB;
use DB\SQL\Schema;

class Cortex extends Cursor {

    protected
        // options
        $db,            // DB object [ \DB\SQL, \DB\Jig, \DB\Mongo ]
        $table,         // selected table, string
        $fluid,         // fluid sql schema mode, boolean
        $fieldConf,     // field configuration, array

        // internal vars, don't touch
        $dbsType,       // mapper engine type [Jig, SQL, Mongo]
        $fieldsCache,   // relation field cache
        $saveCsd;       // mm rel save cascade

    /** @var Cursor */
    protected $mapper;

    /** @var CortexQueryParser */
    protected $queryParser;

    static
        $init = false;  // just init without mapper

    const
        // special datatypes
        DT_TEXT_SERIALIZED = 1,
        DT_TEXT_JSON = 2,

        // error messages
        E_ARRAY_DATATYPE = 'Unable to save an Array in field %s. Use DT_SERIALIZED or DT_JSON.',
        E_CONNECTION = 'No valid DB Connection given.',
        E_NO_TABLE = 'No table specified.',
        E_UNKNOWN_DB_ENGINE = 'This unknown DB system is not supported: %s',
        E_FIELD_SETUP = 'No field setup defined',
        E_UNKNOWN_FIELD = 'Field %s does not exist in %s.',
        E_INVALID_RELATION_OBJECT = 'You can only save hydrated mapper objects',
        E_NULLABLE_COLLISION = 'Unable to set NULL to the NOT NULLABLE field: %s',
        E_WRONG_RELATION_CLASS = 'Relations only works with Cortex objects',
        E_MM_REL_VALUE = 'Invalid value for many field "%s". Expecting null, split-able string, hydrated mapper object, or array of mapper objects.',
        E_MM_REL_CLASS = 'Mismatching m:m relation config from class `%s` to `%s`.',
        E_MM_REL_FIELD = 'Mismatching m:m relation keys from `%s` to `%s`.',
        E_REL_CONF_INC = 'Incomplete relation config for `%s`. Linked key is missing.',
        E_MISSING_REL_CONF = 'Cannot create related model. Specify a model name or relConf array.';

    /**
     * init the ORM, based on given DBS
     * @param null|object $db
     * @param string $table
     * @param null|bool $fluid
     */
    public function __construct($db = NULL, $table = NULL, $fluid = NULL)
    {
        if (!is_null($fluid))
            $this->fluid = $fluid;
        if (!is_object($this->db=(is_string($db=($db?:$this->db))?\Base::instance()->get($db):$db)))
            trigger_error(self::E_CONNECTION);
        if($this->db instanceof Jig)
            $this->dbsType = 'jig';
        elseif ($this->db instanceof SQL)
            $this->dbsType = 'sql';
        elseif ($this->db instanceof Mongo)
            $this->dbsType = 'mongo';
        if (strlen($this->table=strtolower($table?:$this->table))==0&&!$this->fluid)
            trigger_error(self::E_NO_TABLE);
        if (static::$init == TRUE) return;
        if ($this->fluid)
            static::setup($this->db,$this->getTable(),($fluid?array():null));
        $this->initMapper();
    }

    /**
     * create mapper instance
     */
    public function initMapper()
    {
        switch ($this->dbsType) {
            case 'jig':
                $this->mapper = new Jig\Mapper($this->db, $this->table);
                break;
            case 'sql':
                $this->mapper = new SQL\Mapper($this->db, $this->table);
                break;
            case 'mongo':
                $this->mapper = new Mongo\Mapper($this->db, $this->table);
                break;
            default:
                trigger_error(sprintf(self::E_UNKNOWN_DB_ENGINE,$this->dbsType));
        }
        $this->queryParser = CortexQueryParser::instance();
        $this->reset();
        if(!empty($this->fieldConf))
            foreach($this->fieldConf as $key=>&$conf)
                $conf=static::resolveRelationConf($conf);
    }

    /**
     * set model definition
     * config example:
     *  array('title' => array(
     *          'type' => \DB\SQL\Schema::DT_TEXT,
     *          'default' => 'new record title',
     *          'nullable' => true
     *          )
     *        '...' => ...
     *  )
     * @param array $config
     */
    function setFieldConfiguration(array $config)
    {
        $this->fieldConf = $config;
        $this->reset();
    }

    /**
     * returns model field conf array
     * @return array|null
     */
    public function getFieldConfiguration()
    {
        return $this->fieldConf;
    }

    /**
     * kick start to just fetch the config
     * @return array
     */
    static public function resolveConfiguration()
    {
        $refl = new \ReflectionClass(get_called_class());
        $refl->setStaticPropertyValue('init', true);
        $self = $refl->newInstance();
        $refl->setStaticPropertyValue('init', false);
        $conf = array (
            'table'=>$self->getTable(),
            'fieldConf'=>$self->getFieldConfiguration(),
            'db'=> $self->db,
            'fluid'=> $self->fluid,
        );
        unset($self);
        return $conf;
    }

    /**
     * returns model table name
     * @return string
     */
    public function getTable()
    {
        if (!$this->table && $this->fluid)
            $this->table = strtolower(get_class($this));
        return $this->table;
    }

    /**
     * setup / update table schema
     * @static
     * @param $db
     * @param $table
     * @param $fields
     * @return bool
     */
    static public function setup($db=null, $table=null, $fields=null)
    {
        $self = get_called_class();
        if (is_null($db) || is_null($table) || is_null($fields))
            $df = $self::resolveConfiguration();
        if (!is_object($db=(is_string($db=($db?:$df['db']))?\Base::instance()->get($db):$db)))
            trigger_error(self::E_CONNECTION);
        if (strlen($table=strtolower($table?:$df['table']))==0)
            trigger_error(self::E_NO_TABLE);
        if (is_null($fields))
            if (!empty($df['fieldConf']))
                $fields = $df['fieldConf'];
            elseif(!$df['fluid']) {
                trigger_error(self::E_FIELD_SETUP);
                return false;
            } else
                $fields = array();
        if ($db instanceof SQL) {
            $schema = new Schema($db);
            // prepare field configuration
            if (!empty($fields))
                foreach($fields as $key => &$field) {
                    // fetch relation field types
                    $field = static::resolveRelationConf($field);
                    // check m:m relation
                    if (array_key_exists('has-many', $field)) {
                        // m:m relation conf [class,to-key,from-key]
                        if (!is_array($relConf = $field['has-many']))
                            continue;
                        $rel = $relConf[0]::resolveConfiguration();
                        // check if foreign conf matches m:m
                        if (array_key_exists($relConf[1],$rel['fieldConf']) && !is_null($relConf[1])
                            && key($rel['fieldConf'][$relConf[1]]) == 'has-many') {
                            // compute mm table name
                            $fConf = $rel['fieldConf'][$relConf[1]]['has-many'];
                            $mmTable = static::getMMTableName(
                                $rel['table'], $relConf[1], $table, $key, $fConf);
                            // create dummy to invoke table
                            $mmRel = new Cortex($db,$mmTable,true);
                            $rand = rand(0,1000000);
                            $mmRel->{$relConf[1]} = $rand;
                            $mmRel->{$key} = $rand;
                            $mmRel->save();
                            $mmRel->reset();
                            $mmRel->erase(array($relConf[1].' = :rand AND '.$key.' = :rand',':rand'=>$rand));
                        }
                    }
                    // skip virtual fields with no type
                    if (!array_key_exists('type', $field)) {
                        unset($fields[$key]);
                        continue;
                    }
                    // transform array fields
                    if (in_array($field['type'], array(self::DT_TEXT_JSON, self::DT_TEXT_SERIALIZED)))
                        $field['type']=$schema::DT_TEXT;
                    // defaults values
                    if (!array_key_exists('nullable', $field)) $field['nullable'] = true;
                }
            if (!in_array($table, $schema->getTables())) {
                // create table
                $table = $schema->createTable($table);
                foreach ($fields as $field_key => $field_conf)
                    $table->addColumn($field_key, $field_conf);
                $table->build();
            } else {
                // add missing fields
                $table = $schema->alterTable($table);
                $existingCols = $table->getCols();
                foreach ($fields as $field_key => $field_conf)
                    if (!in_array($field_key, $existingCols))
                        $table->addColumn($field_key, $field_conf);
                // remove unused fields
                // foreach ($existingCols as $col)
                //     if (!in_array($col, array_keys($fields)) && $col!='id')
                //     $table->dropColumn($col);
                $table->build();
            }
        }
        return true;
    }

    /**
     * erase all model data, handle with care
     * @param null $db
     * @param null $table
     */
    static public function setdown($db=null, $table=null)
    {
        $self = get_called_class();
        if (is_null($db) || is_null($table))
            $df = $self::resolveConfiguration();
        if (!is_object($db=(is_string($db=($db?:$df['db']))?\Base::instance()->get($db):$db)))
            trigger_error(self::E_CONNECTION);
        if (strlen($table=strtolower($table?:$df['table']))==0)
            trigger_error(self::E_NO_TABLE);
        if (isset($df) && !empty($df['fieldConf']))
            $fields = $df['fieldConf'];
        else
            $fields = array();
        $mmTables = array();
        foreach ($fields as $key => $field) {
            $field = static::resolveRelationConf($field);
            if (array_key_exists('has-many',$field)) {
                if (!is_array($relConf = $field['has-many']))
                    continue;
                $rel = $relConf[0]::resolveConfiguration();
                // check if foreign conf matches m:m
                if (array_key_exists($relConf[1],$rel['fieldConf']) && !is_null($relConf[1])
                    && key($rel['fieldConf'][$relConf[1]]) == 'has-many') {
                    // compute mm table name
                    $fConf = $rel['fieldConf'][$relConf[1]]['has-many'];
                    $mmTables[] = static::getMMTableName(
                        $rel['table'], $relConf[1], $table, $key, $fConf);
                }
            }
        }
        $deletable[] = $table;
        $deletable += $mmTables;
        if($db instanceof Jig) {
            /** @var Jig $db */
            $dir = $db->dir();
            foreach ($deletable as $item)
                if(file_exists($dir.$item))
                    unlink($dir.$item);
        } elseif($db instanceof SQL) {
            /** @var SQL $db */
            $schema = new Schema($db);
            $tables = $schema->getTables();
            foreach ($deletable as $item)
                if(in_array($item, $tables))
                    $schema->dropTable($item);
        } elseif($db instanceof Mongo) {
            /** @var Mongo $db */
            foreach ($deletable as $item)
                $db->{$item}->drop();
        }
    }

    /**
     * computes the m:m table name
     * @param string $ftable foreign table
     * @param string $fkey   foreign key
     * @param string $ptable own table
     * @param string $pkey   own key
     * @param null|array $fConf  foreign conf [class,key]
     * @return string
     */
    static protected function getMMTableName($ftable, $fkey, $ptable, $pkey, $fConf=null)
    {
        if ($fConf) {
            list($fclass, $pfkey) = $fConf;
            $self = get_called_class();
            // check for a matching config
            if (!is_int(strpos($fclass, $self)))
                trigger_error(sprintf(self::E_MM_REL_CLASS, $fclass, $self));
            if ($pfkey != $pkey)
                trigger_error(sprintf(self::E_MM_REL_FIELD,
                    $fclass.'.'.$pfkey, $self.'.'.$pkey));
        }
        $mmTable = array($ftable.'__'.$fkey, $ptable.'__'.$pkey);
        natcasesort($mmTable);
        $return = strtolower(str_replace('\\', '_', implode('_mm_', $mmTable)));
        return $return;
    }

    /**
     * resolve relation field types
     * @param $field
     * @return mixed
     */
    protected static function resolveRelationConf($field)
    {
        if (array_key_exists('belongs-to-one', $field)) {
            // find primary field definition
            if (!is_array($relConf = $field['belongs-to-one']))
                $relConf = array($relConf, '_id');
            // set field type
            if ($relConf[1] == '_id')
                $field['type'] = Schema::DT_INT8;
            else {
                // find foreign field type
                $fc = $relConf[0]::resolveConfiguration();
                $field['type'] = $fc['fieldConf'][$relConf[1]]['type'];
            }
            $field['nullable'] = true;
        }
        elseif (array_key_exists('belongs-to-many', $field)){
            $field['type'] = self::DT_TEXT_JSON;
            $field['nullable'] = true;
        }
        elseif (array_key_exists('has-many', $field)){
            $relConf = $field['has-many'];
            if(!is_array($relConf))
                return $field;
            $rel = $relConf[0]::resolveConfiguration();
            if(array_key_exists('has-many',$rel['fieldConf'][$relConf[1]])) {
                $field['has-many']['rel'] = 'has-many';
                $field['has-many']['relTable'] = $rel['table'];
                $field['has-many']['relField'] = $relConf[1];
            } else {
                $field['has-many']['rel'] = 'belongs-to-one';
            }
        }
        return $field;
    }

    /**
     * Return an array of result arrays matching criteria
     * @param null  $filter
     * @param array $options
     * @param int   $ttl
     * @return array
     */
    public function afind($filter = NULL, array $options = NULL, $ttl = 0)
    {
        $result = $this->find($filter, $options, $ttl);
        return $this->castAll($result,1);
    }

    /**
     * Return an array of objects matching criteria
     * @param array|null $filter
     * @param array|null $options
     * @param int        $ttl
     * @return array
     */
    public function find($filter = NULL, array $options = NULL, $ttl = 0)
    {
        $this->reset();
        $filter = $this->queryParser->prepareFilter($filter,$this->dbsType);
        $options = $this->queryParser->prepareOptions($options, $this->dbsType);
        $result = $this->mapper->find($filter, $options, $ttl);
        foreach($result as &$mapper)
            $mapper = $this->factory($mapper);
        return $result;
    }

    /**
     * Retrieve first object that satisfies criteria
     * @param null  $filter
     * @param array $options
     * @return Cortex
     */
    public function load($filter = NULL, array $options = NULL)
    {
        $this->reset();
        $filter = $this->queryParser->prepareFilter($filter, $this->dbsType);
        $options = $this->queryParser->prepareOptions($options, $this->dbsType);
        $this->mapper->load($filter, $options);
        return $this;
    }

    /**
     * Delete object/s and reset ORM
     * @param $filter
     * @return void
     */
    public function erase($filter = null)
    {
        $filter = $this->queryParser->prepareFilter($filter, $this->dbsType);
        $this->mapper->erase($filter);
    }

    /**
     * Save mapped record
     * @return mixed
     **/
    function save()
    {
        $result = $this->dry() ? $this->insert() : $this->update();
        // m:m save cascade
        if (!empty($this->saveCsd)) {
            $fields = $this->fieldConf;
            foreach($this->saveCsd as $key => $val) {
                $relConf = $fields[$key]['has-many'];
                if (!isset($relConf['refTable'])) {
                    // compute mm table name
                    $mmTable = static::getMMTableName($relConf['relTable'],
                        $relConf['relField'], $this->getTable(), $key);
                    $this->fieldConf[$key]['has-many']['refTable'] = $mmTable;
                } else
                    $mmTable = $relConf['refTable'];
                $rel = $this->getRelInstance(null, array('db'=>$this->db, 'table'=>$mmTable));
                // delete all refs
                if (is_null($val))
                    $rel->erase(array($relConf['relField'].' = ?', $this->get('_id')));
                // update refs
                elseif (is_array($val)) {
                    $id = $this->get('_id');
                    $rel->erase(array($relConf['relField'].' = ?', $id));
                    foreach($val as $v) {
                        $rel->set($key,$v);
                        $rel->set($relConf['relField'],$id);
                        $rel->save();
                        $rel->reset();
                    }
                }
                unset($rel);
            }
        }
        return $result;
    }

    public function count($filter = NULL)
    {
        $filter = $this->queryParser->prepareFilter($filter, $this->dbsType);
        return $this->mapper->count($filter);
    }

    /**
     * Bind value to key
     * @return mixed
     * @param $key string
     * @param $val mixed
     */
    function set($key, $val)
    {
        $fields = $this->fieldConf;
        unset($this->fieldsCache[$key]);
        // pre-process if field config available
        if (!empty($fields) && isset($fields[$key]) && is_array($fields[$key])) {
            // handle relations
            if (isset($fields[$key]['belongs-to-one'])) {
                // one-to-many, one-to-one
                if(is_null($val))
                    $val = NULL;
                elseif (is_object($val) &&
                    !($this->dbsType=='mongo' && $val instanceof \MongoId))
                    // fetch fkey from mapper
                    if (!$val instanceof Cortex || $val->dry())
                        trigger_error(self::E_INVALID_RELATION_OBJECT);
                    else {
                        $relConf = $fields[$key]['belongs-to-one'];
                        $rel_field = (is_array($relConf) ? $relConf[1] : '_id');
                        $val = $val->get($rel_field);
                    }
            } elseif (isset($fields[$key]['belongs-to-many'])) {
                // many-to-many, unidirectional
                $fields[$key]['type'] = self::DT_TEXT_JSON;
                $relConf = $fields[$key]['belongs-to-many'];
                $rel_field = (is_array($relConf) ? $relConf[1] : '_id');
                $val = $this->getForeignKeysArray($val, $rel_field, $key);
            }
            elseif (isset($fields[$key]['has-many'])) {
                // many-to-many, bidirectional
                $relConf = $fields[$key]['has-many'];
                if ($relConf['rel'] == 'has-many') {
                    // custom setter
                    if (method_exists($this, 'set_'.$key))
                        $val = $this->{'set_'.$key}($val);
                    $val = $this->getForeignKeysArray($val,'_id',$key);
                    $this->saveCsd[$key] = $val; // array of keys
                    return $val;
                } elseif ($relConf['rel'] == 'belongs-to-one') {
                    // TODO: many-to-one, bidirectional, inverse way
                    trigger_error("not implemented");
                }
            }
            // convert array content
            if (is_array($val) && $this->dbsType == 'sql' && !empty($fields))
                if ($fields[$key]['type'] == self::DT_TEXT_SERIALIZED)
                    $val = serialize($val);
                elseif ($fields[$key]['type'] == self::DT_TEXT_JSON)
                    $val = json_encode($val);
                else
                    trigger_error(sprintf(self::E_ARRAY_DATATYPE, $key));
            // add nullable polyfill
            if ($val === NULL && ($this->dbsType == 'jig' || $this->dbsType == 'mongo')
                && !empty($fields) && array_key_exists('nullable', $fields[$key])
                && $fields[$key]['nullable'] === false)
                trigger_error(sprintf(self::E_NULLABLE_COLLISION,$key));
            // MongoId shorthand
            if ($this->dbsType == 'mongo' && $key == '_id' && !$val instanceof \MongoId)
                $val = new \MongoId($val);
        }
        // fluid SQL
        if ($this->fluid && $this->dbsType == 'sql') {
            $schema = new Schema($this->db);
            $table = $schema->alterTable($this->table);
            // add missing field
            if(!in_array($key,$table->getCols())) {
                // determine data type
                if (is_int($val)) $type = $schema::DT_INT;
                elseif (is_double($val)) $type = $schema::DT_DOUBLE;
                elseif (is_float($val)) $type = $schema::DT_FLOAT;
                elseif (is_bool($val)) $type = $schema::DT_BOOLEAN;
                elseif (date('Y-m-d H:i:s', strtotime($val)) == $val) $type = $schema::DT_DATETIME;
                elseif (date('Y-m-d', strtotime($val)) == $val) $type = $schema::DT_DATE;
                elseif (strlen($val)<256) $type = $schema::DT_VARCHAR256;
                else $type = $schema::DT_TEXT;
                $table->addColumn($key)->type($type);
                $table->build();
                // update mapper fields
                $newField = $table->getCols(true);
                $newField = $newField[$key];
                $refl = new \ReflectionObject($this->mapper);
                $prop = $refl->getProperty('fields');
                $prop->setAccessible(true);
                $fields = $prop->getValue($this->mapper);
                $fields[$key] = $newField + array('value'=>NULL,'changed'=>NULL);
                $prop->setValue($this->mapper,$fields);
            }
        }
        // custom setter
        if (method_exists($this, 'set_'.$key))
            $val = $this->{'set_'.$key}($val);
        return $this->mapper->{$key} = $val;
    }

    /**
     * Retrieve contents of key
     * @return mixed
     * @param $key string
     */
    function get($key)
    {
        $fields = $this->fieldConf;
        $id = ($this->dbsType == 'sql')?'id':'_id';
        if ($key == '_id' && $this->dbsType == 'sql')
            $key = $id;
        if(!empty($fields) && isset($fields[$key]) && is_array($fields[$key])) {
            // check field cache
            if(!array_key_exists($key,$this->fieldsCache) && is_array($fields[$key])) {
                // load relations
                if (isset($fields[$key]['belongs-to-one'])) {
                    // one-to-X, bidirectional, direct way
                    if (!$this->exists($key) || is_null($this->mapper->{$key}))
                        $this->fieldsCache[$key] = null;
                    else {
                        $relConf = $fields[$key]['belongs-to-one'];
                        if (!is_array($relConf))
                            $relConf = array($relConf, $id);
                        $rel = $this->getRelInstance($relConf[0]);
                        $result = $rel->findone(array($relConf[1].' = ?', $this->mapper->{$key}));
                        $this->fieldsCache[$key] = ((!empty($result)) ? $result : null);
                    }
                }
                elseif (isset($fields[$key]['has-one'])) {
                    // one-to-one, bidirectional, inverse way
                    $fromConf = $fields[$key]['has-one'];
                    if (!is_array($fromConf))
                        trigger_error(sprintf(self::E_REL_CONF_INC,$key));
                    $rel = $this->getRelInstance($fromConf[0]);
                    $relFieldConf = $rel->getFieldConfiguration();
                    if (key($relFieldConf[$fromConf[1]]) == 'belongs-to-one') {
                        $toConf = $relFieldConf[$fromConf[1]]['belongs-to-one'];
                        if (!is_array($toConf))
                            $toConf = array($toConf, $id);
                        if ($toConf[1] != $id && (!$this->exists($toConf[1])
                            || is_null($this->mapper->{$toConf[1]})))
                            $this->fieldsCache[$key] = null;
                        else
                            $this->fieldsCache[$key] = $rel->findone(
                                array($fromConf[1].' = ?', $this->mapper->{$toConf[1]}));
                    }
                }
                elseif (isset($fields[$key]['has-many'])){
                    $fromConf = $fields[$key]['has-many'];
                    if (!is_array($fromConf))
                        trigger_error(sprintf(self::E_REL_CONF_INC, $key));
                    $rel = $this->getRelInstance($fromConf[0]);
                    $relFieldConf = $rel->getFieldConfiguration();
                    // one-to-many, bidirectional, inverse way
                    if (key($relFieldConf[$fromConf[1]]) == 'belongs-to-one') {
                        $toConf = $relFieldConf[$fromConf[1]]['belongs-to-one'];
                        if(!is_array($toConf))
                            $toConf = array($toConf, $id);
                        if ($toConf[1] != $id && (!$this->exists($toConf[1])
                            || is_null($this->mapper->{$toConf[1]})))
                            $this->fieldsCache[$key] = null;
                        else
                            $this->fieldsCache[$key] = $rel->find(
                                array($fromConf[1].' = ?', $this->mapper->{$toConf[1]}));
                    }
                    // many-to-many, bidirectional
                    elseif (key($relFieldConf[$fromConf[1]]) == 'has-many') {
                        if (!array_key_exists('refTable', $fromConf)) {
                            // compute mm table name
                            $toConf = $relFieldConf[$fromConf[1]]['has-many'];
                            $mmTable = static::getMMTableName($fromConf['relTable'],
                                $fromConf['relField'], $this->getTable(), $key, $toConf);
                            $this->fieldConf[$key]['has-many']['refTable'] = $mmTable;
                        } else
                            $mmTable = $fromConf['refTable'];
                        // create mm table mapper
                        $rel = $this->getRelInstance(null,array('db'=>$this->db,'table'=>$mmTable));
                        $results = $rel->find(array($fromConf['relField'].' = ?',
                                                    $this->mapper->{$id}));
                        $fkeys = array();
                        // collect foreign keys
                        foreach ($results as $el)
                            $fkeys[] = $el->get($key);
                        if (empty($fkeys))
                            $this->fieldsCache[$key] = NULL;
                        else {
                            // create foreign table mapper
                            unset($rel);
                            $rel = $this->getRelInstance($fromConf[0]);
                            // load foreign models
                            $filter = array($id.' IN ?', $fkeys);
                            $this->fieldsCache[$key] = $rel->find($filter);
                        }
                    }
                } elseif (isset($fields[$key]['belongs-to-many'])) {
                    // many-to-many, unidirectional
                    $fields[$key]['type'] = self::DT_TEXT_JSON;
                    $result = !$this->exists($key) ? null :$this->mapper->get($key);
                    if ($this->dbsType == 'sql')
                        $result = json_decode($result, true);
                    if (!is_array($result))
                        $this->fieldsCache[$key] = $result;
                    else {
                        // create foreign table mapper
                        $relConf = $fields[$key]['belongs-to-many'];
                        if (!is_array($relConf))
                            $relConf = array($relConf, $id);
                        $rel = $this->getRelInstance($relConf[0]);
                        $fkeys = array();
                        foreach ($result as $el)
                            $fkeys[] = $el;
                        // load foreign models
                        $filter = array($relConf[1].' IN ?', $fkeys);
                        $this->fieldsCache[$key] = $rel->find($filter);
                    }
                }
                // resolve array fields
                elseif ($this->dbsType == 'sql' && isset($fields[$key]['type'])) {
                    if ($fields[$key]['type'] == self::DT_TEXT_SERIALIZED)
                        $this->fieldsCache[$key] = unserialize($this->mapper->{$key});
                    elseif ($fields[$key]['type'] == self::DT_TEXT_JSON)
                        $this->fieldsCache[$key] = json_decode($this->mapper->{$key},true);
                }
            }
        }
        // fetch cached value, if existing
        $val = array_key_exists($key,$this->fieldsCache) ? $this->fieldsCache[$key]
                : (($this->exists($key) || $key == $id) ? $this->mapper->{$key} : null);
        // custom getter
        return (method_exists($this, 'get_'.$key)) ? $this->{'get_'.$key}($val) : $val;
    }

    /**
     * find the ID values of given relation collection
     * @param $val string|array|object|bool
     * @param $rel_field string
     * @param $key string
     * @return array|Cortex|null|object
     */
    protected function getForeignKeysArray($val, $rel_field, $key)
    {
        if (is_null($val))
            return NULL;
        elseif (is_string($val))
            // split-able string of collection IDs
            $val = \Base::instance()->split($val);
        elseif (!is_array($val) && !(is_object($val)
                && $val instanceof Cortex && !$val->dry()))
            trigger_error(sprintf(self::E_MM_REL_VALUE, $key));
        // hydrated mapper as collection
        if (is_object($val)) {
            while (!$val->dry()) {
                $nval[] = $val->get($rel_field);
                $val->next();
            }
            $val = $nval;
        } elseif (is_array($val))
            // array of single hydrated mappers, raw ID value or mixed
            foreach ($val as $index => &$item) {
                if ($this->dbsType == 'mongo' && $rel_field == '_id' && is_string($item))
                    $item = new \MongoId($item);
                elseif (is_object($item) &&
                    !($this->dbsType == 'mongo' && $item instanceof \MongoId))
                    if (!$item instanceof Cortex || $item->dry())
                        trigger_error(self::E_INVALID_RELATION_OBJECT);
                    else $item = $item->get($rel_field);
            }
        return $val;
    }

    /**
     * creates and caches related mapper objects
     * @param string $model
     * @param array $relConf
     * @return Cortex
     */
    protected function getRelInstance($model=null,$relConf=null)
    {
        if (!$model && !$relConf)
            trigger_error(self::E_MISSING_REL_CONF);
        $relConf = $model ? $model::resolveConfiguration() : $relConf;
        $relName = ($model?:'Cortex').'\\'.$relConf['db']->uuid().'\\'.$relConf['table'];
        if (\Registry::exists($relName)) {
            $rel = \Registry::get($relName);
            $rel->reset();
        } else {
            $rel = $model ? new $model : new Cortex($relConf['db'], $relConf['table']);
            if (!$rel instanceof Cortex)
                trigger_error(self::E_WRONG_RELATION_CLASS);
            \Registry::set($relName, $rel);
        }
        return $rel;
    }

    /**
     * Return fields of mapper object as an associative array
     * @return array
     * @param bool|Cortex $obj
     * @param bool|int $rel_depths depths to resolve relations
     */
    public function cast($obj = NULL, $rel_depths = 1)
    {
        $fields = $this->mapper->cast( ($obj) ? $obj->mapper : null );
        if(is_int($rel_depths))
            $rel_depths--;
        if (!empty($this->fieldConf)) {
            $fields += array_fill_keys(array_keys($this->fieldConf),NULL);
            $mp = $obj ? : $this;
            foreach ($fields as $key => &$val) {
                //reset relType
                unset($relType);
                // post process configured fields
                if (isset($this->fieldConf[$key]) && is_array($this->fieldConf[$key])) {
                    // handle relations
                    if (($rel_depths === TRUE || (is_int($rel_depths) && $rel_depths >= 0))) {
                        $relTypes = array('belongs-to-one','has-many','belongs-to-many','has-one');
                        foreach ($relTypes as $type)
                            if (isset($this->fieldConf[$key][$type])) {
                                $relType = $type;
                                break;
                            }
                        // cast relations
                        if (isset($relType)) {
                            // cast relations
                            if (($relType == 'belongs-to-one' || $relType == 'belongs-to-many')
                                && !$mp->exists($key))
                                $val = null;
                            else
                                $val = $mp->get($key);
                            if (!is_null($val)) {
                                if ($relType == 'belongs-to-one' || $relType == 'has-one')
                                    // single object
                                    $val = $val->cast(null, $rel_depths);
                                elseif ($relType == 'belongs-to-many' || $relType == 'has-many')
                                    // multiple objects
                                    foreach ($val as &$item)
                                        $item = !is_null($item) ? $item->cast(null, $rel_depths) : null;
                            }
                        }
                    }
                    // decode array fields
                    elseif ($this->dbsType == 'sql' && isset($this->fieldConf[$key]['type']))
                        if ($this->fieldConf[$key]['type'] == self::DT_TEXT_SERIALIZED)
                            $val=unserialize($this->mapper->{$key});
                        elseif ($this->fieldConf[$key]['type'] == self::DT_TEXT_JSON)
                            $val=json_decode($this->mapper->{$key}, true);
                }
            }
        }
        return $fields;
    }

    /**
     * cast an array of mappers
     * @param string|array $mapper_arr  array of mapper objects, or field name
     * @param int          $rel_depths  depths to resolve relations
     * @return array    array of associative arrays
     */
    function castAll($mapper_arr, $rel_depths=0)
    {
        if (is_string($mapper_arr))
            $mapper_arr = $this->get($mapper_arr);
        if (!$mapper_arr)
            return NULL;
        foreach ($mapper_arr as &$mp)
            $mp = $mp->cast(null,$rel_depths);
        return $mapper_arr;
    }

    /**
     * wrap result mapper
     * @param $mapper
     * @return Cortex
     */
    protected function factory($mapper)
    {
        $cx = clone($this);
        $cx->reset();
        $cx->mapper = $mapper;
        return $cx;
    }

    public function dry() {
        return $this->mapper->dry();
    }

    public function copyfrom($key) {
        $this->mapper->copyfrom($key);
    }

    public function copyto($key) {
        $this->mapper->copyto($key);
    }

    public function skip($ofs = 1) {
        $this->reset(false);
        $this->mapper->skip($ofs);
        return $this;
    }

    public function first()
    {
        $this->reset(false);
        $this->mapper->first();
        return $this;
    }

    public function last()
    {
        $this->reset(false);
        $this->mapper->last();
        return $this;
    }

    public function reset($mapper = true) {
        if ($mapper)
            $this->mapper->reset();
        $this->fieldsCache = array();
        $this->saveCsd = array();
        // set default values
        if(($this->dbsType == 'jig' || $this->dbsType == 'mongo')
            && !empty($this->fieldConf))
            foreach($this->fieldConf as $field_key => $field_conf)
                if(array_key_exists('default',$field_conf))
                    $this->{$field_key} = $field_conf['default'];
    }

    function exists($key) {
        return $this->mapper->exists($key);
    }

    function clear($key) {
        $this->mapper->clear($key);
    }

    function insert() {
        return $this->mapper->insert();
    }

    function update() {
        return $this->mapper->update();
    }

    /**
     * cleanup on destruct
     */
    public function __destruct()
    {
        unset($this->mapper);
    }
}


class CortexQueryParser extends \Prefab {

    const
        E_BRACKETS = 'Invalid query: unbalanced brackets found',
        E_INBINDVALUE = 'Bind value for IN operator must be an array',
        E_MISSINGBINDKEY = 'Named bind parameter `%s` does not exist in filter arguments';

    /**
     * converts the given filter array to fit the used DBS
     *
     * example filter:
     *   array('text = ? AND num = ?','bar',5)
     *   array('num > ? AND num2 <= ?',5,10)
     *   array('num1 > num2')
     *   array('text like ?','%foo%')
     *   array('(text like ? OR text like ?) AND num != ?','foo%','%bar',23)
     *
     * @param array $cond
     * @param string $engine
     * @return array|bool|null
     */
    public function prepareFilter($cond, $engine)
    {
        if (is_null($cond)) return $cond;
        if (is_string($cond))
            $cond = array($cond);
        $where = array_shift($cond);
        $args = $cond;
        $where = str_replace(array('&&', '||'), array('AND', 'OR'), $where);
        // prepare IN condition
        $where = preg_replace('/\bIN\b\s*\(\s*(\?|:\w+)?\s*\)/i', 'IN $1', $where);
        switch ($engine) {
            case 'jig':
                return $this->_jig_parse_filter($where, $args);
            case 'mongo':
                $parts = $this->splitLogical($where);
                if (is_int(strpos($where, ':')))
                    list($parts, $args) = $this->convertNamedParams($parts, $args);
                foreach ($parts as &$part)
                    $part = $this->_mongo_parse_relational_op($part, $args);
                $ncond = $this->_mongo_parse_logical_op($parts);
                return $ncond;
            case 'sql':
                // preserve identifier
                $where = preg_replace('/(?!\B)_id/', 'id', $where);
                $parts = $this->splitLogical($where);
                // ensure positional bind params
                if (is_int(strpos($where, ':')))
                    list($parts, $args) = $this->convertNamedParams($parts, $args);
                $ncond = array();
                foreach ($parts as &$part) {
                    // enhanced IN handling
                    if (is_int(strpos($part, '?'))) {
                        $val = array_shift($args);
                        if (is_int($pos = strpos($part, 'IN ?'))) {
                            if (!is_array($val))
                                trigger_error(self::E_INBINDVALUE);
                            $bindMarks = str_repeat('?,', count($val) - 1).'?';
                            $part = substr($part, 0, $pos).'IN ('.$bindMarks.')';
                            $ncond = array_merge($ncond, $val);
                        } else
                            $ncond[] = $val;
                    }
                }
                array_unshift($ncond, implode(' ', $parts));
                return $ncond;
        }
    }

    /**
     * split where criteria string into logical chunks
     * @param $cond
     * @return array
     */
    protected function splitLogical($cond)
    {
        return preg_split('/\s*(\)|\(|\bAND\b|\bOR\b)\s*/i', $cond, -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    }

    /**
     * converts named parameter filter to positional
     * @param $parts
     * @param $args
     * @return array
     */
    protected function convertNamedParams($parts, $args)
    {
        if (empty($args)) return array($parts, $args);
        $params = array();
        $pos = 0;
        foreach ($parts as &$part) {
            if (preg_match('/:\w+/i', $part, $match)) {
                if (!isset($args[$match[0]]))
                    trigger_error(sprintf(self::E_MISSINGBINDKEY,
                        $match[0]));
                $part = str_replace($match[0], '?', $part);
                $params[] = $args[$match[0]];
            } elseif (is_int(strpos($part, '?')))
                $params[] = $args[$pos++];
        }
        return array($parts, $params);
    }

    /**
     * convert filter array to jig syntax
     * @param $where
     * @param $args
     * @return array
     */
    protected function _jig_parse_filter($where, $args)
    {
        $parts = $this->splitLogical($where);
        if (is_int(strpos($where, ':')))
            list($parts, $args) = $this->convertNamedParams($parts, $args);
        $ncond = array();
        foreach ($parts as &$part) {
            if (in_array(strtoupper($part), array('AND', 'OR')))
                continue;
            // prefix field names
            $part = preg_replace('/([a-z_-]+)/i', '@$1', $part, -1, $count);
            // value comparison
            if (is_int(strpos($part, '?'))) {
                $val = array_shift($args);
                preg_match('/(@\w+)/i', $part, $match);
                // find like operator
                if (is_int(strpos($upart = strtoupper($part), ' @LIKE '))) {
                    // %var% -> /var/
                    if (substr($val, 0, 1) == '%' && substr($val, -1, 1) == '%')
                        $val = str_replace('%', '/', $val);
                    // var%  -> /^var/
                    elseif (substr($val, -1, 1) == '%')
                        $val = '/^'.str_replace('%', '', $val).'/'; // %var  -> /var$/
                    elseif (substr($val, 0, 1) == '%')
                        $val = '/'.substr($val, 1).'$/';
                    $part = 'preg_match(?,'.$match[0].')';
                } // find IN operator
                else if (is_int($pos = strpos($upart, ' @IN '))) {
                    if ($not = is_int($npos = strpos($upart, '@NOT')))
                        $pos = $npos;
                    $part = ($not ? '!' : '').'in_array('.substr($part, 0, $pos).
                        ',array(\''.implode('\',\'', $val).'\'))';
                }
                // add existence check
                $part = '(isset('.$match[0].') && '.$part.')';
                $ncond[] = $val;
            } elseif ($count == 2) {
                // field comparison
                preg_match_all('/(@\w+)/i', $part, $matches);
                $part = '(isset('.$matches[0][0].') && isset('.$matches[0][1].') && ('.$part.'))';
            }
        }
        array_unshift($ncond, implode(' ', $parts));
        return $ncond;
    }

    /**
     * find and wrap logical operators AND, OR, (, )
     * @param $parts
     * @return array
     */
    protected function _mongo_parse_logical_op($parts)
    {
        $b_offset = 0;
        $ncond = array();
        $child = array();
        for ($i = 0, $max = count($parts); $i < $max; $i++) {
            $part = $parts[$i];
            if ($part == '(') {
                // add sub-bracket to parse array
                if ($b_offset > 0)
                    $child[] = $part;
                $b_offset++;
            } elseif ($part == ')') {
                $b_offset--;
                // found closing bracket
                if ($b_offset == 0) {
                    $ncond[] = ($this->_mongo_parse_logical_op($child));
                    $child = array();
                } elseif ($b_offset < 0)
                    trigger_error(self::E_BRACKETS);
                else
                    // add sub-bracket to parse array
                    $child[] = $part;
            } // add to parse array
            elseif ($b_offset > 0)
                $child[] = $part; // condition type
            elseif (!is_array($part)) {
                if (strtoupper($part) == 'AND')
                    $add = true;
                elseif (strtoupper($part) == 'OR')
                    $or = true;
            } else // skip
            $ncond[] = $part;
        }
        if ($b_offset > 0)
            trigger_error(self::E_BRACKETS);
        if (isset($add))
            return array('$and' => $ncond);
        elseif (isset($or))
            return array('$or' => $ncond); else
            return $ncond[0];
    }

    /**
     * find and convert relational operators
     * @param $part
     * @param $args
     * @return array|null
     */
    protected function _mongo_parse_relational_op($part, &$args)
    {
        if (is_null($part)) return $part;
        $ops = array('<=', '>=', '<>', '<', '>', '!=', '==', '=', 'like', 'in', 'not in');
        foreach ($ops as &$op)
            $op = preg_quote($op);
        $op_quote = implode('|', $ops);
        if (preg_match('/'.$op_quote.'/i', $part, $match)) {
            $var = is_int(strpos($part, '?')) ? array_shift($args) : null;
            $exp = explode($match[0], $part);
            // unbound value
            if (is_numeric($exp[1]))
                $var = $exp[1];
            // field comparison
            elseif (!is_int(strpos($exp[1], '?')))
                return array('$where' => 'this.'.trim($exp[0]).' '.$match[0].' this.'.trim($exp[1]));
            $upart = strtoupper($match[0]);
            // find LIKE operator
            if ($upart == 'LIKE') {
                $fC = substr($var, 0, 1);
                $lC = substr($var, -1, 1);
                // %var% -> /var/
                if ($fC == '%' && $lC == '%')
                    $rgx = str_replace('%', '/', $var);
                // var%  -> /^var/
                elseif ($lC == '%')
                    $rgx = '/^'.str_replace('%', '', $var).'/'; // %var  -> /var$/
                elseif ($fC == '%')
                    $rgx = '/'.substr($var, 1).'$/';
                $var = new \MongoRegex($rgx);
            } // find IN operator
            elseif (in_array($upart, array('IN','NOT IN'))) {
                foreach ($var as &$id)
                    if (!$id instanceof \MongoId)
                        $id = new \MongoId($id);
                $var = array(($upart=='NOT IN')?'$nin':'$in' => $var);
            } // translate operators
            elseif (!in_array($match[0], array('==', '='))) {
                $opr = str_replace(array('<>', '<', '>', '!', '='),
                    array('$ne', '$lt', '$gt', '$n', 'e'), $match[0]);
                $var = array($opr => (strtolower($var) == 'null') ? null :
                    (is_object($var) ? $var : $var + 0));
            } elseif (trim($exp[0]) == '_id' && !$var instanceof \MongoId)
                $var = new \MongoId($var);
            return array(trim($exp[0]) => $var);
        }
        return $part;
    }

    /**
     * convert options array syntax to given engine type
     *
     * example:
     *   array('order'=>'location') // default direction is ASC
     *   array('order'=>'num1 desc, num2 asc')
     *
     * @param array $options
     * @param string $engine
     * @return array|null
     */
    public function prepareOptions($options, $engine)
    {
        if (!empty($options) && is_array($options)) {
            switch ($engine) {
                case 'jig':
                    if (array_key_exists('order', $options))
                        $options['order'] = str_replace(array('asc', 'desc'),
                            array('SORT_ASC', 'SORT_DESC'), strtolower($options['order']));
                    break;
            }
            switch ($engine) {
                case 'mongo':
                    if (array_key_exists('order', $options)) {
                        $sorts = explode(',', $options['order']);
                        $sorting = array();
                        foreach ($sorts as $sort) {
                            $sp = explode(' ', trim($sort));
                            $sorting[$sp[0]] = (array_key_exists(1, $sp) &&
                                strtoupper($sp[1]) == 'DESC') ? -1 : 1;
                        }
                        $options['order'] = $sorting;
                    }
                    break;
            }
            return $options;
        } else
            return null;
    }
}