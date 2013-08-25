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
        @version 0.9.4
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
        $mapper,        // ORM object
        $fieldsCache,   // relation field cache
        $saveCsd,       // mm rel save cascade
        $relRegistry;

    static
        $init = false;  // just init without mapper

    const
        // special datatypes
        DT_TEXT_SERIALIZED = 1,
        DT_TEXT_JSON = 2,

        // error messages
        E_ARRAYDATATYPE = 'Unable to save an Array in field %s. Use DT_SERIALIZED or DT_JSON.',
        E_CONNECTION = 'No valid DB Connection given.',
        E_NOTABLE = 'No table specified.',
        E_UNKNOWNDBENGINE = 'This unknown DB system is not supported: %s',
        E_FIELDSETUP = 'No field setup defined',
        E_BRACKETS = 'Invalid query: unbalanced brackets found',
        E_UNKNOWNFIELD = 'Field %s does not exist in %s.',
        E_INVALIDRELATIONOBJECT = 'You can only save hydrated mapper objects',
        E_NULLABLECOLLISION = 'Unable to set NULL to the NOT NULLABLE field: %s',
        E_WRONGRELATIONCLASS = 'Relations only works with Cortex objects',
        E_MMRELVALUE = 'Invalid value for m:m field "%s". Expecting null, string, hydrated mapper object, or array of mapper objects.';

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
        $this->dbsType = get_class($this->db);
        if (strlen($this->table=strtolower($table?:$this->table))==0&&!$this->fluid)
            trigger_error(self::E_NOTABLE);
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
            case 'DB\Jig':
                $this->mapper = new Jig\Mapper($this->db, $this->table);
                break;
            case 'DB\SQL':
                $this->mapper = new SQL\Mapper($this->db, $this->table);
                break;
            case 'DB\Mongo':
                $this->mapper = new Mongo\Mapper($this->db, $this->table);
                break;
            default:
                trigger_error(sprintf(self::E_UNKNOWNDBENGINE,$this->dbsType));
        }
        $this->reset();
        $this->relRegistry = array();
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

        if (!$self instanceof Cortex)
            trigger_error(self::E_WRONGRELATIONCLASS);
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
            trigger_error(self::E_NOTABLE);
        if (is_null($fields))
            if (!empty($df['fieldConf']))
                $fields = $df['fieldConf'];
            elseif(!$df['fluid']) {
                trigger_error(self::E_FIELDSETUP);
                return false;
            } else
                $fields = array();
        $table = strtolower($table);
        $dbsType = get_class($db);
        if ($dbsType == 'DB\SQL') {
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
                            // check for a matching config
                            if (!is_int(strpos($fConf[0],$self)))
                                trigger_error(sprintf('mismatching m2m relation config from class `%s` to `%s`.',
                                    $fConf[0],$self));
                            if ($fConf[1] != $key)
                                trigger_error(sprintf('mismatching m2m relation keys from `%s` to `%s`.',
                                    $fConf[0].'.'.$fConf[1],$self.'.'.$key));
                            // compute mm table name
                            $mmTable = array($rel['table'].'__'.$relConf[1],
                                             $table.'__'.$key);
                            natcasesort($mmTable);
                            $mmTable = strtolower(str_replace('\\','_',implode('_mm_',$mmTable)));
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
        $refl = new \ReflectionClass(get_called_class());
        $df = $refl->getDefaultProperties();
        if (!is_object($db=(is_string($db=($db?:$df['db']))?\Base::instance()->get($db):$db)))
            trigger_error(self::E_CONNECTION);
        if (strlen($table = strtolower($table?:$df['table'])) == 0)
            trigger_error(self::E_NOTABLE);
        $dbsType = get_class($db);
        switch ($dbsType) {
            case 'DB\Jig':
                $refl = new \ReflectionObject($db);
                $prop = $refl->getProperty('dir');
                $prop->setAccessible(true);
                $dir = $prop->getValue($db);
                if(file_exists($dir.$table))
                    unlink($dir.$table);
                break;
            case 'DB\SQL':
                $schema = new Schema($db);
                if(in_array($table, $schema->getTables()))
                    $schema->dropTable($table);
                break;
            case 'DB\Mongo':
                $db->{$table}->drop();
                break;
        }
    }

    /**
     * resolve relation field types
     * @param $field
     * @return mixed
     */
    protected static function resolveRelationConf($field)
    {
        if (array_key_exists('belongs-to', $field)) {
            // find primary field definition
            if (!is_array($relConf = $field['belongs-to']))
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
                $field['has-many']['rel'] = 'belongs-to';
            }
        }
        return $field;
    }

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
     * @return array|bool|null
     */
    private function prepareFilter($cond = NULL)
    {
        if (is_null($cond)) return $cond;
        if (is_string($cond))
            $cond = array($cond);
        $cond = $this->convertNamedParams($cond);
        $cond[0] = str_replace(array('&&','||'),array('AND','OR'),$cond[0]);
        $ops = array('<=', '>=', '<>', '<', '>', '!=', '==', '=', 'like');
        foreach ($ops as &$op) $op = preg_quote($op);
        $op_quote = implode('|', $ops);

        switch ($this->dbsType) {
            case 'DB\Jig':
                return $this->_jig_parse_filter($cond);
            case 'DB\Mongo':
                $parts = preg_split("/\s*(\)|\(|\bAND\b|\bOR\b)\s*/i", array_shift($cond), -1,
                    PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                foreach ($parts as &$part)
                    if (preg_match('/'.$op_quote.'/i', $part, $match)) {
                        $bindValue = is_int(strpos($part,'?')) ? array_shift($cond) : null;
                        $part = $this->_mongo_parse_relational_op($part, $bindValue);
                    }
                $ncond = $this->_mongo_parse_logical_op($parts);
                return $ncond;
            case 'DB\SQL':
                // preserve identifier
                $cond[0] = preg_replace('/(?!\B)_id/','id',$cond[0]);
                return $cond;
        }
    }

    /**
     * converts named parameter filter to positional
     * @param $cond
     * @return array
     */
    function convertNamedParams($cond)
    {
        if (count($cond)<=1) return $cond;
        if (is_int(strpos($cond[0],':'))) {
            // named param found
            $where = explode(' ',array_shift($cond));
            $params = array(0);
            $pos = 0;
            foreach ($where as $val)
                if (is_int(strpos($val,':')) && in_array($val,array_keys($cond))) {
                    $where = str_replace($val, '?', $where);
                    $params[] = $cond[$val];
                } elseif($val == '?')
                    $params[] = $cond[$pos++];
            $cond = array(implode(' ',$where)) + $params;
        }
        return $cond;
    }

    /**
     * convert filter array to jig syntax
     * @param $cond
     * @return array
     */
    private function _jig_parse_filter($cond){
        // split logical
        $parts = preg_split("/\s*(\)|\(|\bAND\b|\bOR\b)\s*/i", array_shift($cond), -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $ncond = array();
        foreach ($parts as &$part) {
            if (in_array(strtoupper($part), array('AND', 'OR')))
                continue;
            // prefix field names
            $part = preg_replace('/([a-z_-]+)/i', '@$1', $part, -1, $count);
            // value comparison
            if (is_int(strpos($part, '?'))) {
                $val = array_shift($cond);
                preg_match('/(@\w+)/i', $part, $match);
                // find like operator
                if (is_int(strpos(strtoupper($part), ' @LIKE '))) {
                    // %var% -> /var/
                    if (substr($val, 0, 1) == '%' && substr($val, -1, 1) == '%')
                        $val = str_replace('%', '/', $val);
                    // var%  -> /^var/
                    elseif (substr($val, -1, 1) == '%')
                        $val = '/^'.str_replace('%', '', $val).'/';
                    // %var  -> /var$/
                    elseif (substr($val, 0, 1) == '%')
                        $val = '/'.substr($val, 1).'$/';
                    $part = 'preg_match(?,'.$match[0].')';
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
    private function _mongo_parse_logical_op($parts)
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
            return array('$or' => $ncond);
        else
            return $ncond[0];
    }

    /**
     * find and convert relational operators
     * @param $cond
     * @param $var
     * @return array|null
     */
    private function _mongo_parse_relational_op($cond, $var)
    {
        if (is_null($cond)) return $cond;
        $ops = array('<=', '>=', '<>', '<', '>', '!=', '==', '=', 'like');
        foreach ($ops as &$op) $op = preg_quote($op);
        $op_quote = implode('|', $ops);
        if (preg_match('/'.$op_quote.'/i', $cond, $match)) {
            $exp = explode($match[0], $cond);
            // unbound value
            if (is_numeric($exp[1]))
                $var = $exp[1];
            // field comparison
            elseif(!is_int(strpos($exp[1], '?')))
                return array('$where' => 'this.'.trim($exp[0]).' '.$match[0].' this.'.trim($exp[1]));
            // find like operator
            if (strtoupper($match[0]) == 'LIKE') {
                // %var% -> /var/
                if (substr($var, 0, 1) == '%' && substr($var, -1, 1) == '%')
                    $rgx = str_replace('%', '/', $var);
                // var%  -> /^var/
                elseif (substr($var, -1, 1) == '%')
                    $rgx = '/^'.str_replace('%', '', $var).'/';
                // %var  -> /var$/
                elseif (substr($var, 0, 1) == '%')
                    $rgx = '/'.substr($var, 1).'$/';
                $var = new \MongoRegex($rgx);
            } // translate operators
            elseif (!in_array($match[0], array('==', '='))) {
                $opr = str_replace(array('<>', '<', '>', '!', '='),
                    array('$ne', '$lt', '$gt', '$n', 'e'), $match[0]);
                $var = array($opr => (strtolower($var) == 'null') ? null : $var + 0);
            } elseif(trim($exp[0]) == '_id' && !$var instanceof \MongoId)
                $var = new \MongoId($var);
            return array(trim($exp[0]) => $var);
        }
        return $cond;
    }

    /**
     * convert options array syntax
     *
     * example:
     *   array('order'=>'location') // default direction is ASC
     *   array('order'=>'num1 desc, num2 asc')
     *
     * @param $options
     * @return array|null
     */
    private function prepareOptions($options)
    {
        if (!empty($options) && is_array($options)) {
            switch ($this->dbsType) {
                case 'DB\Jig':
                    if (array_key_exists('order', $options))
                        $options['order'] = str_replace(array('asc', 'desc'),
                            array('SORT_ASC', 'SORT_DESC'), strtolower($options['order']));
                    break;
            }
            switch ($this->dbsType) {
                case 'DB\Mongo':
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

    /**
     * Return an array of result arrays matching criteria
     * @param null  $filter
     * @param array $options
     * @param int   $ttl
     * @return array
     */
    public function afind($filter = NULL, array $options = NULL, $ttl = 0)
    {
        return array_map(array($this,'cast'),$this->find($filter,$options,$ttl));
    }

    /************************\
     *
     *  ORM specific methods
     *
    \************************/

    /**
     * Return an array of objects matching criteria
     * @param array|null $filter
     * @param array|null $options
     * @param int        $ttl
     * @return array
     */
    public function find($filter = NULL, array $options = NULL, $ttl = 0)
    {
        $filter = $this->prepareFilter($filter);
        $options = $this->prepareOptions($options);
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
        $filter = $this->prepareFilter($filter);
        $options = $this->prepareOptions($options);
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
        $filter = $this->prepareFilter($filter);
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
                    $mmTable = array($relConf['relTable'].'__'.$relConf['relField'],
                                     $this->getTable().'__'.$key);
                    natcasesort($mmTable);
                    $mmTable = strtolower(str_replace('\\', '_', implode('_mm_', $mmTable)));
                    $this->fieldConf[$key]['has-many']['refTable'] = $mmTable;
                } else
                    $mmTable = $relConf['refTable'];
                $rel = (isset($this->relRegistry[$mmTable])) ?
                    $this->relRegistry[$mmTable] : new Cortex($this->db, $mmTable);
                // delete all refs
                if (is_null($val))
                    $rel->erase(array($key.' = ?', $this->get('_id')));
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
        $filter = $this->prepareFilter($filter);
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
        if (!empty($fields) && in_array($key,array_keys($fields))) {
            // handle relations
            if (!is_int($val) && is_array($fields[$key])
                && array_key_exists('belongs-to', $fields[$key])) {
                // one-to-many, one-to-one
                if(is_null($val))
                    $val = NULL;
                elseif (!(is_string($val) && $this->dbsType == 'DB\Jig')) {
                    if (!$val instanceof Cortex || $val->dry())
                        trigger_error(self::E_INVALIDRELATIONOBJECT);
                    else {
                        $relConf = $fields[$key]['belongs-to'];
                        $rel_field = (is_array($relConf) ? $relConf[1] : '_id');
                        $val = $val->get($rel_field);
                    }
                }
            } elseif (is_array($fields[$key]) && array_key_exists('belongs-to-many', $fields[$key])) {
                // many-to-many, unidirectional
                $fields[$key]['type'] = self::DT_TEXT_JSON;
                if (is_null($val))
                    $val = NULL;
                elseif (is_string($val))
                    $val = \Base::instance()->split($val);
                elseif (!is_array($val) && !(is_object($val) && $val instanceof Cortex && !$val->dry()))
                    trigger_error(sprintf(self::E_MMRELVALUE,$key));
                else {
                    $relConf = $fields[$key]['belongs-to-many'];
                    $rel_field = (is_array($relConf) ? $relConf[1] : '_id');
                    if (is_object($val)) {
                        while (!$val->dry()) {
                            $nval[] = $val->get($rel_field);
                            $val->next();
                        }
                        $val = $nval;
                    } else
                        foreach ($val as $index => &$item)
                            if (is_object($item))
                                if (!$item instanceof Cortex || $item->dry())
                                    trigger_error(self::E_INVALIDRELATIONOBJECT);
                                else $item = $item[$rel_field];
                }
            }
            elseif (is_array($fields[$key]) && array_key_exists('has-many', $fields[$key])) {
                // many-to-many, bidirectional
                $relConf = $fields[$key]['has-many'];
                if ($relConf['rel'] == 'has-many') {
                    // custom setter
                    if (method_exists($this, 'set_'.$key))
                        $val = $this->{'set_'.$key}($val);
                    $this->saveCsd[$key] = $val;
                    return $val;
                } elseif ($relConf['rel'] == 'belongs-to') {
                    // TODO: many-to-one, bidirectional, inverse way
                    trigger_error("not implemented");
                }
            }
            // convert array content
            if (is_array($val) && $this->dbsType == 'DB\SQL' && !empty($fields))
                if ($fields[$key]['type'] == self::DT_TEXT_SERIALIZED)
                    $val = serialize($val);
                elseif ($fields[$key]['type'] == self::DT_TEXT_JSON)
                    $val = json_encode($val);
                else
                    trigger_error(sprintf(self::E_ARRAYDATATYPE, $key));
            // add nullable polyfill
            if ($val === NULL && ($this->dbsType == 'DB\Jig' || $this->dbsType == 'DB\Mongo')
                && !empty($fields) && array_key_exists('nullable', $fields[$key])
                && $fields[$key]['nullable'] === false)
                trigger_error(sprintf(self::E_NULLABLECOLLISION,$key));
            // MongoId shorthand
            if ($this->dbsType == 'DB\Mongo' && $key == '_id' && !$val instanceof \MongoId)
                $val = new \MongoId($val);
            // fluid SQL
            if ($this->fluid && $this->dbsType == 'DB\SQL') {
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
        $id = ($this->dbsType == 'DB\SQL')?'id':'_id';
        if ($key == '_id' && $this->dbsType == 'DB\SQL')
            $key = $id;
        if(!empty($fields) && isset($fields[$key])) {
            // check field cache
            if(!array_key_exists($key,$this->fieldsCache) && is_array($fields[$key])) {
                // load relations
                if (isset($fields[$key]['belongs-to'])) {
                    // one-to-X, bidirectional, direct way
                    if (!$this->exists($key))
                        $this->fieldsCache[$key] = null;
                    else {
                        $relConf = $fields[$key]['belongs-to'];
                        if (!is_array($relConf))
                            $relConf = array($relConf, $id);
                        $rel = $this->getRelInstance($relConf[0]);
                        $rel->load(array($relConf[1].' = ?', $this->mapper->{$key}));
                        $this->fieldsCache[$key] = ((!$rel->dry()) ? $rel : null);
                    }
                }
                elseif (isset($fields[$key]['has-one'])) {
                    // one-to-one, bidirectional, inverse way
                    $fromConf = $fields[$key]['has-one'];
                    if (!is_array($fromConf))
                        trigger_error('Incomplete has-one config. Linked key missing.');
                    $rel = $this->getRelInstance($fromConf[0]);
                    $relFieldConf = $rel->getFieldConfiguration();
                    if (key($relFieldConf[$fromConf[1]]) == 'belongs-to') {
                        $toConf = $relFieldConf[$fromConf[1]]['belongs-to'];
                        if (!is_array($toConf))
                            $toConf = array($toConf, $id);
                        if ($toConf[1] != $id && !$this->exists($toConf[1]))
                            $this->fieldsCache[$key] = null;
                        else
                            $this->fieldsCache[$key] = $rel->load(
                                array($fromConf[1].' = ?', $this->mapper->{$toConf[1]}));
                    }
                }
                elseif (isset($fields[$key]['has-many'])){
                    $fromConf = $fields[$key]['has-many'];
                    if (!is_array($fromConf))
                        trigger_error('Incomplete has-many config. Linked key missing.');
                    $rel = $this->getRelInstance($fromConf[0]);
                    $relFieldConf = $rel->getFieldConfiguration();
                    // one-to-many, bidirectional, inverse way
                    if (key($relFieldConf[$fromConf[1]]) == 'belongs-to') {
                        $toConf = $relFieldConf[$fromConf[1]]['belongs-to'];
                        if(!is_array($toConf))
                            $toConf = array($toConf, $id);
                        if ($toConf[1] != $id && !$this->exists($toConf[1]))
                            $this->fieldsCache[$key] = null;
                        else
                            $this->fieldsCache[$key] = $rel->find(
                                array($fromConf[1].' = ?', $this->mapper->{$toConf[1]}));
                    }
                    // many-to-many, bidirectional
                    elseif (key($relFieldConf[$fromConf[1]]) == 'has-many') {
                        $relConf = $fields[$key]['has-many'];
                        if (!array_key_exists('refTable', $relConf)) {
                            // compute mm table name
                            $mmTable = array($relConf['relTable'].'__'.$relConf['relField'],
                                $this->getTable().'__'.$key);
                            natcasesort($mmTable);
                            $mmTable = strtolower(str_replace('\\', '_',
                                implode('_mm_', $mmTable)));
                            $this->fieldConf[$key]['has-many']['refTable'] = $mmTable;
                        } else
                            $mmTable = $relConf['refTable'];
                        // create mm table mapper
                        $rel = (array_key_exists($mmTable, $this->relRegistry)) ?
                            $this->relRegistry[$mmTable] : new Cortex($this->db, $mmTable);
                        $results = $rel->find(array($relConf['relField'].' = ?',
                                                    $this->mapper->{$id}));
                        foreach ($results as $el) {
                            $where[] = '_id = ?';
                            $filter[] = $el->get($key);
                        }
                        if (!isset($where)) {
                            $this->fieldsCache[$key] = NULL;
                        } else {
                            $crit = implode(' OR ', $where);
                            array_unshift($filter, $crit);
                            unset($rel);
                            // create foreign table mapper
                            $rel = $this->getRelInstance($relConf[0]);
                            $this->fieldsCache[$key] = $rel->find($filter);
                        }
                    }
                } elseif (isset($fields[$key]['belongs-to-many'])) {
                    // many-to-many, unidirectional
                    $fields[$key]['type'] = self::DT_TEXT_JSON;
                    $result = !$this->exists($key) ? null :$this->mapper->get($key);
                    if ($this->dbsType == 'DB\SQL')
                        $result = json_decode($result, true);
                    if (!is_array($result))
                        $this->fieldsCache[$key] = $result;
                    else {
                        // hydrate mapper
                        $relConf = $fields[$key]['belongs-to-many'];
                        if (!is_array($relConf))
                            $relConf = array($relConf, $id);
                        $rel = $this->getRelInstance($relConf[0]);
                        foreach ($result as $el) {
                            $where[] = $relConf[1].' = ?';
                            $filter[] = $el;
                        }
                        $crit = implode(' OR ', $where);
                        array_unshift($filter, $crit);
                        $this->fieldsCache[$key] = $rel->find($filter);
                    }
                }
                // resolve array fields
                elseif ($this->dbsType == 'DB\SQL' && isset($fields[$key]['type'])) {
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
     * creates and caches related mapper objects
     * @param $name
     * @return Cortex
     */
    protected function getRelInstance($name)
    {
        if (array_key_exists($name, $this->relRegistry))
            return $this->relRegistry[$name];
        $rel = new $name;
        if (!$rel instanceof Cortex)
            trigger_error(self::E_WRONGRELATIONCLASS);
        $this->relRegistry[$name] = $rel;
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
                // post process configured fields
                if (isset($this->fieldConf[$key]) && is_array($this->fieldConf[$key])) {
                    // handle relations
                    if (($rel_depths === TRUE || (is_int($rel_depths) && $rel_depths >= 0))) {
                        $relTypes = array('belongs-to','has-many','belongs-to-many','has-one');
                        foreach ($relTypes as $type)
                            if (isset($this->fieldConf[$key][$type])) {
                                $relType = $type;
                                break;
                            }
                        // cast relations
                        if (isset($relType)) {
                            // cast relations
                            if (($relType == 'belongs-to' || $relType == 'belongs-to-many')
                                && !$mp->exists($key))
                                $val = null;
                            else
                                $val = $mp->get($key);
                            if (!is_null($val)) {
                                if ($relType == 'belongs-to' || $relType == 'has-one')
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
                    elseif ($this->dbsType == 'DB\SQL' && isset($this->fieldConf[$key]['type']))
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
        $this->mapper->skip($ofs);
        return $this;
    }

    public function first()
    {
        $this->mapper->first();
        return $this;
    }

    public function last()
    {
        $this->mapper->last();
        return $this;
    }

    public function reset() {
        $this->mapper->reset();
        $this->fieldsCache = array();
        $this->saveCsd = array();
        // set default values
        if(($this->dbsType == 'DB\Jig' || $this->dbsType == 'DB\Mongo')
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