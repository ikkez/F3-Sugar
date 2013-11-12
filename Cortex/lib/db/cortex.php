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
        @version 1.0.0-beta
        @since 24.04.2012
        @date 01.10.2013
 **/

namespace DB;
use DB\SQL\Schema;

class Cortex extends Cursor {

    protected
        // config
        $db,            // DB object [ \DB\SQL, \DB\Jig, \DB\Mongo ]
        $table,         // selected table, string
        $fluid,         // fluid sql schema mode, boolean
        $fieldConf,     // field configuration, array
        // behaviour
        $smartLoading,  // intelligent lazy eager loading, boolean
        $standardiseID, // return standardized '_id' field for SQL when casting
        // internals
        $dbsType,       // mapper engine type [jig, sql, mongo]
        $fieldsCache,   // relation field cache
        $saveCsd,       // mm rel save cascade
        $collectionID,  // collection set identifier
        $relFilter;     // filter for loading related models

    /** @var Cursor */
    protected $mapper;

    /** @var CortexQueryParser */
    protected $queryParser;

    static
        $init = false;  // just init without mapper

    const
        // special datatypes
        DT_SERIALIZED = 'SERIALIZED',
        DT_JSON = 'JSON',

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
        $this->clearRelFilter();
        $f3 = \Base::instance();
        $this->smartLoading = $f3->exists('CORTEX.smartLoading') ?
            $f3->get('CORTEX.smartLoading') : TRUE;
        $this->standardiseID = $f3->exists('CORTEX.standardiseID') ?
            $f3->get('CORTEX.standardiseID') : TRUE;
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

    public function addToCollection($cID) {
        $this->collectionID = $cID;
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
                    if (in_array($field['type'], array(self::DT_JSON, self::DT_SERIALIZED)))
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
        $deletable = array();
        $deletable[] = $table;
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
                    $deletable[] = static::getMMTableName(
                        $rel['table'], $relConf[1], $table, $key, $fConf);
                }
            }
        }
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
            $field['type'] = self::DT_JSON;
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
     * @return CortexCollection
     */
    public function find($filter = NULL, array $options = NULL, $ttl = 0)
    {
        $filter = $this->queryParser->prepareFilter($filter,$this->dbsType);
        $options = $this->queryParser->prepareOptions($options, $this->dbsType);
        $result = $this->mapper->find($filter, $options, $ttl);
        if (empty($result))
            return false;
        foreach($result as &$mapper) {
            $mapper = $this->factory($mapper);
            unset($mapper);
        }
        $cc = new \DB\CortexCollection();
        $cc->setModels($result);
        $this->clearRelFilter();
        return $cc;
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
     * add filter for loading related models
     * @param string $key
     * @param array $filter
     * @return $this
     */
    public function addRelFilter($key,$filter)
    {
        $this->relFilter[$key] = $filter;
        return $this;
    }

    /**
     * removes one or all relation filter
     * @param null|string $key
     */
    public function clearRelFilter($key = null)
    {
        if (!$key)
            $this->relFilter = array();
        elseif(isset($this->relFilter[$key]))
            unset($this->relFilter[$key]);
    }

    /**
     * merge the relation filter to the query criteria if it exists
     * @param string $key
     * @param array $crit
     * @return array
     */
    protected function mergeWithRelFilter($key,$crit)
    {
        if (array_key_exists($key, $this->relFilter)) {
            $filter = $this->relFilter[$key];
            $crit[0] .= ' and '.array_shift($filter);
            $crit = array_merge($crit, $filter);
        }
        return $crit;
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
                    $rel->erase(array($relConf['relField'].' = ?', $this->get('_id',true)));
                // update refs
                elseif (is_array($val)) {
                    $id = $this->get('_id',true);
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
                    !($this->dbsType=='mongo' && $val instanceof \MongoId)) {
                    // fetch fkey from mapper
                    if (!$val instanceof Cortex || $val->dry())
                        trigger_error(self::E_INVALID_RELATION_OBJECT);
                    else {
                        $relConf = $fields[$key]['belongs-to-one'];
                        $rel_field = (is_array($relConf) ? $relConf[1] : '_id');
                        $val = $val->get($rel_field,true);
                    }
                } elseif ($this->dbsType == 'mongo' && !$val instanceof \MongoId)
                    $val = new \MongoId($val);
            } elseif (isset($fields[$key]['belongs-to-many'])) {
                // many-to-many, unidirectional
                $fields[$key]['type'] = self::DT_JSON;
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
                if ($fields[$key]['type'] == self::DT_SERIALIZED)
                    $val = serialize($val);
                elseif ($fields[$key]['type'] == self::DT_JSON)
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
     * @param string $key
     * @param bool $raw
     */
    function get($key,$raw = false)
    {
        $fields = $this->fieldConf;
        $id = ($this->dbsType == 'sql')?'id':'_id';
        if ($key == '_id' && $this->dbsType == 'sql')
            $key = $id;
        if ($raw) {
            $out = $this->exists($key) ? $this->mapper->{$key} : NULL;
            // ensure to return a MongoID object
            // TODO: just for compatibility, test for dropping this part
            if ($this->dbsType == 'mongo' && isset($this->fieldConf[$key])) {
                if (is_string($out) &&
                    (isset($this->fieldConf[$key]['belongs-to-one'])
                        || isset($this->fieldConf[$key]['belongs-to-many'])
                        || isset($this->fieldConf[$key]['has-one'])
                        || isset($this->fieldConf[$key]['has-many']))) {
                    $out = new \MongoId($out);
                }
            }
            return $out;
        }
        if(!empty($fields) && isset($fields[$key]) && is_array($fields[$key])) {
            // check field cache
            if(!array_key_exists($key,$this->fieldsCache) && is_array($fields[$key])) {
                // load relations
                if (isset($fields[$key]['belongs-to-one'])) {
                    // one-to-X, bidirectional, direct way
                    if (!$this->exists($key) || is_null($this->mapper->{$key}))
                        $this->fieldsCache[$key] = null;
                    else {
                        // get config for this field
                        $relConf = $fields[$key]['belongs-to-one'];
                        if (!is_array($relConf))
                            $relConf = array($relConf, $id);
                        // fetch related model
                        $rel = $this->getRelInstance($relConf[0]);
                        // am i part of a result collection?
                        if ($this->collectionID && $this->smartLoading) {
                            $cx = CortexCollection::instance($this->collectionID);
                            // does the collection has cached results for this key?
                            if (!$cx->hasRelSet($key)) {
                                // build the cache, find all values of current key
                                $relKeys = array_unique($cx->getAll($key,true));
                                // find related models
                                $crit = array($relConf[1].' IN ?', $relKeys);
                                $relSet = $rel->find($this->mergeWithRelFilter($key, $crit));
                                // cache relSet, sorted by ID
                                $cx->setRelSet($key, $relSet ? $relSet->getBy($relConf[1]) : NULL);
                            }
                            // get a subset of the preloaded set
                            $result = $cx->getSubset($key,(string) $this->get($key,true));
                            $this->fieldsCache[$key] = $result ? $result[0] : NULL;
                        } else {
                            $crit = array($relConf[1].' = ?', $this->get($key, true));
                            $crit = $this->mergeWithRelFilter($key, $crit);
                            $this->fieldsCache[$key] = $rel->findone($crit);
                        }
                    }
                }
                elseif (($type = isset($fields[$key]['has-one']))
                    || isset($fields[$key]['has-many'])) {
                    $type = $type ? 'has-one' : 'has-many';
                    $fromConf = $fields[$key][$type];
                    if (!is_array($fromConf))
                        trigger_error(sprintf(self::E_REL_CONF_INC, $key));
                    $rel = $this->getRelInstance($fromConf[0]);
                    $relFieldConf = $rel->getFieldConfiguration();
                    // one-to-*, bidirectional, inverse way
                    if (key($relFieldConf[$fromConf[1]]) == 'belongs-to-one') {
                        $toConf = $relFieldConf[$fromConf[1]]['belongs-to-one'];
                        if(!is_array($toConf))
                            $toConf = array($toConf, $id);
                        if ($toConf[1] != $id && (!$this->exists($toConf[1])
                            || is_null($this->mapper->{$toConf[1]})))
                            $this->fieldsCache[$key] = null;
                        elseif($this->collectionID && $this->smartLoading) {
                            // part of a result set
                            $cx = CortexCollection::instance($this->collectionID);
                            if(!$cx->hasRelSet($key)) {
                                // emit eager loading
                                $relKeys = $cx->getAll($toConf[1],true);
                                $crit = array($fromConf[1].' IN ?', $relKeys);
                                $relSet = $rel->find($this->mergeWithRelFilter($key,$crit));
                                $cx->setRelSet($key, $relSet ? $relSet->getBy($fromConf[1],true) : NULL);
                            }
                            $result = $cx->getSubset($key, array($this->get($toConf[1])));
                            $this->fieldsCache[$key] = $result ? (($type == 'has-one')
                                ? $result[0][0] : $result[0]) : NULL;
                        } else {
                            $crit = array($fromConf[1].' = ?', $this->get($toConf[1],true));
                            $crit = $this->mergeWithRelFilter($key, $crit);
                            $this->fieldsCache[$key] = (($type == 'has-one') ? $rel->findone($crit)
                                : $rel->find($crit)) ?: NULL;
                        }
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
                        if ($this->collectionID && $this->smartLoading) {
                            $cx = CortexCollection::instance($this->collectionID);
                            if (!$cx->hasRelSet($key)) {
                                // get IDs of all results
                                $relKeys = $cx->getAll($id,true);
                                // get all pivot IDs
                                $mmRes = $rel->find(array($fromConf['relField'].' IN ?', $relKeys));
                                if (!$mmRes)
                                    $cx->setRelSet($key, NULL);
                                else {
                                    foreach($mmRes as $model) {
                                        $val = $model->get($key,true);
                                        $pivotRel[ (string) $model->get($fromConf['relField'])][] = $val;
                                        $pivotKeys[] = $val;
                                    }
                                    // cache pivot keys
                                    $cx->setRelSet($key.'_pivot', $pivotRel);
                                    // preload all rels
                                    $pivotKeys = array_unique($pivotKeys);
                                    $fRel = $this->getRelInstance($fromConf[0]);
                                    $crit = array($id.' IN ?', $pivotKeys);
                                    $relSet = $fRel->find($this->mergeWithRelFilter($key, $crit));
									if($relSet === false){
                                        trigger_error(sprintf(self::E_REL_CONF_INC, $key));
                                    }
                                    $cx->setRelSet($key, $relSet->getBy($id));
                                    unset($fRel);
                                }
                            }
                            // fetch subset from preloaded rels using cached pivot keys
                            $fkeys = $cx->getSubset($key.'_pivot', $this->get($id));
                            $this->fieldsCache[$key] = $fkeys ?
                                $cx->getSubset($key, $fkeys[0]) : NULL;
                        } // no collection
                        else {
                            // find foreign keys
                            $results = $rel->find(
                                array($fromConf['relField'].' = ?', $this->get($id,true)));
                            if(!$results)
                                $this->fieldsCache[$key] = NULL;
                            else {
                                $fkeys = $results->getAll($key,true);
                                // create foreign table mapper
                                unset($rel);
                                $rel = $this->getRelInstance($fromConf[0]);
                                // load foreign models
                                $filter = array($id.' IN ?', $fkeys);
                                $filter = $this->mergeWithRelFilter($key, $filter);
                                $this->fieldsCache[$key] = $rel->find($filter);
                            }
                        }
                    }
                }
                elseif (isset($fields[$key]['belongs-to-many'])) {
                    // many-to-many, unidirectional
                    $fields[$key]['type'] = self::DT_JSON;
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
                            $fkeys[] = (string) $el;
                        // if part of a result set
                        if ($this->collectionID && $this->smartLoading) {
                            $cx = CortexCollection::instance($this->collectionID);
                            if (!$cx->hasRelSet($key)) {
                                // find all keys
                                $relKeys = ($cx->getAll($key,true));
                                if ($this->dbsType == 'sql'){
                                    foreach ($relKeys as &$val) {
                                        $val = substr($val, 1, -1);
                                        unset($val);
                                    }
                                    $relKeys = json_decode('['.implode(',',$relKeys).']');
                                } else
                                    $relKeys = call_user_func_array('array_merge', $relKeys);
                                // get related models
                                $crit = array($relConf[1].' IN ?', array_unique($relKeys));
                                $relSet = $rel->find($this->mergeWithRelFilter($key, $crit));
                                // cache relSet, sorted by ID
                                $cx->setRelSet($key, $relSet ? $relSet->getBy($relConf[1]) : NULL);
                            }
                            // get a subset of the preloaded set
                            $this->fieldsCache[$key] = $cx->getSubset($key, $fkeys);
                        } else {
                            // load foreign models
                            $filter = array($relConf[1].' IN ?', $fkeys);
                            $filter = $this->mergeWithRelFilter($key, $filter);
                            $this->fieldsCache[$key] = $rel->find($filter);
                        }
                    }
                }
                // resolve array fields
                elseif ($this->dbsType == 'sql' && isset($fields[$key]['type'])) {
                    if ($fields[$key]['type'] == self::DT_SERIALIZED)
                        $this->fieldsCache[$key] = unserialize($this->mapper->{$key});
                    elseif ($fields[$key]['type'] == self::DT_JSON)
                        $this->fieldsCache[$key] = json_decode($this->mapper->{$key},true);
                }
            }
        }
        // fetch cached value, if existing
        $val = array_key_exists($key,$this->fieldsCache) ? $this->fieldsCache[$key]
            : (($this->exists($key) || $key == $id) ? $this->mapper->{$key} : null);
        if ($this->dbsType == 'mongo' && $val instanceof \MongoId) {
            // conversion to string makes further processing in template, etc. much easier
            $val = (string) $val;
        }
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
        if (is_object($val) && $val instanceof CortexCollection)
            $val = $val->expose();
        elseif (is_string($val))
            // split-able string of collection IDs
            $val = \Base::instance()->split($val);
        elseif (!is_array($val) && !(is_object($val)
                && ($val instanceof Cortex && !$val->dry())))
            trigger_error(sprintf(self::E_MM_REL_VALUE, $key));
        // hydrated mapper as collection
        if (is_object($val)) {
            while (!$val->dry()) {
                $nval[] = $val->get($rel_field,true);
                $val->next();
            }
            $val = $nval;
        }
        elseif (is_array($val)) {
            // array of single hydrated mappers, raw ID value or mixed
            foreach ($val as $index => &$item) {
                if (is_object($item) &&
                    !($this->dbsType == 'mongo' && $item instanceof \MongoId))
                    if (!$item instanceof Cortex || $item->dry())
                        trigger_error(self::E_INVALID_RELATION_OBJECT);
                    else $item = $item->get($rel_field,true);
                unset($item);
            }
            if ($this->dbsType == 'mongo'&& $rel_field == '_id')
                foreach ($val as $index => &$item) {
                    if (is_string($item))
                        $item = new \MongoId($item);
                }
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
                            if (is_array($val) || is_object($val)) {
                                if ($relType == 'belongs-to-one' || $relType == 'has-one')
                                    // single object
                                    $val = $val->cast(null, $rel_depths);
                                elseif ($relType == 'belongs-to-many' || $relType == 'has-many')
                                    // multiple objects
                                    foreach ($val as $k => $item)
                                        $val[$k] = !is_null($item) ? $item->cast(null, $rel_depths) : null;
                            }
                        }
                        if ($val instanceof CortexCollection)
                            $val = $val->expose();
                    }
                    // decode array fields
                    elseif ($this->dbsType == 'sql' && isset($this->fieldConf[$key]['type']))
                        if ($this->fieldConf[$key]['type'] == self::DT_SERIALIZED)
                            $val=unserialize($this->mapper->{$key});
                        elseif ($this->fieldConf[$key]['type'] == self::DT_JSON)
                            $val=json_decode($this->mapper->{$key}, true);
                }
                if ($this->dbsType == 'mongo' && $key == '_id')
                    $val = (string) $val;
                if ($this->dbsType == 'sql' && $key == 'id' && $this->standardiseID) {
                    $fields['_id'] = $val;
                    unset($fields[$key]);
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
        $out = array();
        foreach ($mapper_arr as $mp)
            $out[] = $mp->cast(null,$rel_depths);
        return $out;
    }

    /**
     * wrap result mapper
     * @param $mapper
     * @return Cortex
     */
    protected function factory($mapper)
    {
        $cx = clone($this);
        $cx->reset(false);
        $cx->mapper = $mapper;
        return $cx;
    }

    public function dry() {
        return $this->mapper->dry();
    }

    public function copyfrom($key,$fieldConfOnly=false)
    {
        $fields = \Base::instance()->get($key);
        if ($fieldConfOnly)
            $fields = array_intersect_key($fields,$this->fieldConf);
        foreach($fields as $key=>$val)
            $this->set($key,$val);
    }

    public function copyto($key) {
        \Base::instance()->set($key, $this->cast(null,0));
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

    public function reset($mapper = true)
    {
        if ($mapper)
            $this->mapper->reset();
        $this->fieldsCache = array();
        $this->saveCsd = array();
        // set default values
        if(($this->dbsType == 'jig' || $this->dbsType == 'mongo')
            && !empty($this->fieldConf))
            foreach($this->fieldConf as $field_key => $field_conf)
                if(array_key_exists('default',$field_conf)) {
                    $val = ($field_conf['default'] === \DB\SQL\Schema::DF_CURRENT_TIMESTAMP)
                        ? date('Y-m-d H:i:s') : $field_conf['default'];
                    $this->set($field_key, $val);
                }
    }

    function exists($key) {
        if ($key == '_id') return true;
        return $this->mapper->exists($key);
    }

    function clear($key) {
        $this->mapper->clear($key);
    }

    function insert() {
        $res = $this->mapper->insert();
        if (is_array($res))
            $res = $this->mapper;
        if (is_object($res))
            $res = $this->factory($res);
        return is_int($res) ? $this : $res;
    }

    function update() {
        $res = $this->mapper->update();
        if (is_array($res))
            $res = $this->mapper;
        if (is_object($res))
            $res = $this->factory($res);
        return is_int($res) ? $this : $res;
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
                foreach ($parts as &$part) {
                    $part = $this->_mongo_parse_relational_op($part, $args);
                    unset($part);
                }
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
                    $fC = substr($val, 0, 1);
                    $lC = substr($val, -1, 1);
                    // %var% -> /var/
                    if ($fC == '%' && $lC == '%')
                        $val = str_replace('%', '/', $val);
                    // var%  -> /^var/
                    elseif ($lC == '%')
                        $val = '/^'.str_replace('%', '', $val).'/';
                    // %var  -> /var$/
                    elseif ($fC == '%')
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
            } elseif ($count >= 1) {
                // field comparison
                preg_match_all('/(@\w+)/i', $part, $matches);
                $chks = array();
                foreach ($matches[0] as $field)
                    $chks[] = 'isset('.$field.')';
                $part = '('.implode(' && ',$chks).' && ('.$part.'))';
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
                $child[] = $part;
            // condition type
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
        if (is_null($part))
            return $part;
        if (preg_match('/\<\=|\>\=|\<\>|\<|\>|\!\=|\=\=|\=|like|in|not in/i', $part, $match)) {
            $var = is_int(strpos($part, '?')) ? array_shift($args) : null;
            $exp = explode($match[0], $part);
            $key = trim($exp[0]);
            // unbound value
            if (is_numeric($exp[1]))
                $var = $exp[1];
            // field comparison
            elseif (!is_int(strpos($exp[1], '?')))
                return array('$where' => 'this.'.$key.' '.$match[0].' this.'.trim($exp[1]));
            $upart = strtoupper($match[0]);
            // MongoID shorthand
            if ($key == '_id') {
                if (is_array($var))
                    foreach ($var as &$id) {
                        if (!$id instanceof \MongoId)
                            $id = new \MongoId($id);
                        unset($id);
                    }
                elseif(!$var instanceof \MongoId)
                    $var = new \MongoId($var);
            }
            // find LIKE operator
            if ($upart == 'LIKE') {
                $fC = substr($var, 0, 1);
                $lC = substr($var, -1, 1);
                // %var% -> /var/
                if ($fC == '%' && $lC == '%')
                    $rgx = str_replace('%', '/', $var);
                // var%  -> /^var/
                elseif ($lC == '%')
                    $rgx = '/^'.str_replace('%', '', $var).'/';
                // %var  -> /var$/
                elseif ($fC == '%')
                    $rgx = '/'.substr($var, 1).'$/';
                $var = new \MongoRegex($rgx);
            } // find IN operator
            elseif (in_array($upart, array('IN','NOT IN'))) {
                $var = array(($upart=='NOT IN')?'$nin':'$in' => $var);
            } // translate operators
            elseif (!in_array($match[0], array('==', '='))) {
                $opr = str_replace(array('<>', '<', '>', '!', '='),
                    array('$ne', '$lt', '$gt', '$n', 'e'), $match[0]);
                $var = array($opr => (strtolower($var) == 'null') ? null :
                    (is_object($var) ? $var : (is_numeric($var) ? $var + 0 : $var)));
            }
            return array($key => $var);
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

class CortexCollection extends \ArrayIterator {

    protected
        $relSets = array(),
        $pointer = 0,
        $cid;

    const
        E_UnknownCID = 'This Collection does not exist: %s';

    public function __construct() {
        $this->cid = uniqid('cortex_collection_');
        \Registry::set($this->cid,$this);
        parent::__construct();
    }

    public function __destruct() {
        \Registry::clear($this->cid);
    }

    /**
     * set a collection of models
     * @param $models
     */
    function setModels($models) {
        array_map(array($this,'add'),$models);
    }

    /**
     * add single model to collection
     * @param $model
     */
    function add(Cortex $model)
    {
        $model->addToCollection($this->cid);
        $this->append($model);
    }

    public function getRelSet($key) {
        return (isset($this->relSets[$key])) ? $this->relSets[$key] : null;
    }

    public function setRelSet($key,$set) {
        $this->relSets[$key] = $set;
    }

    public function hasRelSet($key) {
        return array_key_exists($key,$this->relSets);
    }

    public function expose() {
        return $this->getArrayCopy();
    }

    /**
     * get an intersection from a cached relation-set, based on given keys
     * @param string $prop
     * @param array $keys
     * @return array
     */
    public function getSubset($prop,$keys) {
        if (!is_array($keys))
            $keys = \Base::instance()->split($keys);
        if (!$this->hasRelSet($prop) || !($relSet = $this->getRelSet($prop)))
            return null;
        foreach ($keys as &$key)
            if ($key instanceof \MongoId)
                $key = (string) $key;
        return array_values(array_intersect_key($relSet, array_flip($keys)));
    }

    /**
     * returns all values of a specified property from all models
     * @param string $prop
     * @param bool $raw
     * @return array
     */
    public function getAll($prop, $raw = false)
    {
        $out = array();
        foreach ($this->getArrayCopy() as $model)
            if ($model->exists($prop)) {
                $val = $model->get($prop, $raw);
                if (!empty($val))
                    $out[] = $val;
            }
        return $out;
    }

    /**
     * return all models keyed by a specified index key
     * @param string $index
     * @param bool $nested
     * @return array
     */
    public function getBy($index, $nested = false)
    {
        $out = array();
        foreach ($this->getArrayCopy() as $model)
            if ($model->exists($index)) {
                $val = $model->get($index, true);
                if (!empty($val))
                    if($nested) $out[(string) $val][] = $model;
                    else        $out[(string) $val] = $model;
            }
        return $out;
    }

    /**
     * @param $cid
     * @return CortexCollection
     */
    static public function instance($cid) {
        if (!\Registry::exists($cid))
            trigger_error(sprintf(self::E_UnknownCID, $cid));
        return \Registry::get($cid);
    }

}