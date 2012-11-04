<?php

/**
 *  VDB Schema Builder Testing Suite
 *
 **/
class VDB_Tests extends F3instance {

	function run() {
        $this->set('title','Variable DB Schema Builder');

        // prevent errors breaking the render process,
        // set this to false for debugging purpose
       F3::set('QUIET',true);

        $dbs = array(
            'mysql' =>  new VDB('mysql:host=localhost;port=3306;dbname=test','root',''),
            'sqlite' => new VDB('sqlite::memory:'),
            'pgsql' => new VDB('pgsql:host=localhost;dbname=test','test','1234'),
        );

        $tname = 'test_table';

        foreach( $dbs as $type => $db) {

            $this->set('DB',$db);
            $type .= ': ';
            $db->dropTable($tname);

            // create table
            $cr_result = $db->createTable($tname);
            $result = $db->getTables();
            $this->expect(
                $cr_result == true && in_array($tname,$result) == true,
                $type.'create table'.$this->getTime(),
                $type.'could not create table'.$this->getTime()
            );

            foreach(array_keys($db->dataTypes) as $index=>$field) {
                // testing column types
                list($r1,$r2) = $db->table($tname,
                    function($table) use($index,$field) {
                        return $table->addCol('column_'.$index,$field);
                    },
                    function($table){ return $table->getCols(); });
                $this->expect(
                    $r1 == true && in_array('column_'.$index,$r2) == true,
                    $type.'adding column ['.$field.'], nullable'.$this->getTime(),
                    $type.'cannot add a nullable column of type:'.$field.$this->getTime()
                );
            }

            // default value text, not nullable
            list($r1,$r2) = $db->table($tname,
                function($table){   return $table->addCol('text_default_not_null','TEXT8',false,'foo bar'); },
                function($table){   return $table->getCols(true); });
            $this->expect(
                $r1 == true && in_array('text_default_not_null',array_keys($r2)) == true && $r2['text_default_not_null']['default'] == 'foo bar' && $r2['text_default_not_null']['null'] == false,
                $type.'adding column [TEXT8], not nullable with default value'.$this->getTime(),
                $type.'missmatching default value in not nullable [TEXT8] column'.$this->getTime()
            );

            // default value numeric, not nullable
            list($r1,$r2) = $db->table($tname,
                function($table){   return $table->addCol('int_default_not_null','INT8',false,123); },
                function($table){   return $table->getCols(true); });
            $this->expect(
                $r1 == true && in_array('int_default_not_null',array_keys($r2)) == true && $r2['int_default_not_null']['default'] == 123 && $r2['int_default_not_null']['null'] == false,
                $type.'adding column [INT8], not nullable with default value'.$this->getTime(),
                $type.'missmatching default value in not nullable [INT8] column'.$this->getTime()
            );

            // default value text, nullable
            list($r1,$r2) = $db->table($tname,
                function($table){   return $table->addCol('text_default_nullable','TEXT8',true,'foo bar'); },
                function($table){   return $table->getCols(true); });
            $this->expect(
                $r1 == true && in_array('text_default_nullable',array_keys($r2)) == true && $r2['text_default_nullable']['default'] == 'foo bar',
                $type.'adding column [TEXT8], nullable with default value'.$this->getTime(),
                $type.'missmatching default value in nullable [TEXT8] column'.$this->getTime()
            );

            // default value numeric, nullable
            list($r1,$r2) = $db->table($tname,
                function($table){   return $table->addCol('int_default_nullable','INT8',true,123); },
                function($table){   return $table->getCols(true); });
            $this->expect(
                $r1 == true && in_array('int_default_nullable',array_keys($r2)) == true && $r2['int_default_nullable']['default'] == 123,
                $type.'adding column [INT8], nullable with default value'.$this->getTime(),
                $type.'missmatching default value in nullable [INT8] column'.$this->getTime()
            );

            // rename column
            list($r1,$r2) = $db->table($tname,
                function($table){ return $table->renameCol('text_default_not_null','title123'); },
                function($table){ return $table->getCols(); });
            $this->expect(
                $r1 == true &&
                in_array('title123',$r2) == true &&
                in_array('text_default_not_null',$r2) == false,
                $type.'renaming column'.$this->getTime(),
                $type.'cannot rename a column'.$this->getTime()
            );
            $db->table($tname, function($table){ return $table->renameCol('title123','text_default_not_null'); });

            // remove column
            list($r1,$r2) = $db->table($tname,
                function($table){ return $table->dropCol('title'); },
                function($table){ return $table->getCols(); });
            $this->expect(
                $r1 == true && in_array('title',$r2) == false,
                $type.'removng column'.$this->getTime(),
                $type.'cannot remove a column'.$this->getTime()
            );

            // rename table
            $db->dropTable('test123');
            $valid = $db->table($tname,
                function($table){ return $table->renameTable('test123'); });
            $this->expect(
                $valid == true && in_array('test123',$db->getTables()) == true && in_array($tname,$db->getTables()) == false,
                $type.'renaming table'.$this->getTime(),
                $type.'cannot rename a table'.$this->getTime()
            );
            $db->table('test123',
                function($table) use($tname) { $table->renameTable($tname); });

            // drop table
            $db->dropTable($tname);
            $this->expect(
                in_array($tname,$db->getTables()) == false,
                $type.'drop table'.$this->getTime(),
                $type.'cannot not drop table'.$this->getTime()
            );

        }
        F3::set('QUIET',false);
        echo $this->render('basic/results.htm');
        //echo var_dump($this->profile());
	}

    var $roundTime = 0;
    private function getTime() {
        $time = microtime(TRUE)-$this->get('timer') - $this->roundTime;
        $this->roundTime = microtime(TRUE)-$this->get('timer');
        return ' [ '.sprintf('%.3f',$time).'s ]';
    }


}
