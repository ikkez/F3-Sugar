<?php

/**
 *  VDB Schema Builder Testing Suite
 *
 **/
class VDB_Tests extends F3instance {

    function myErrorHandler($code, $text, $file, $line) {
        return true;
    }

	function run() {
        $this->set('title','Variable DB Schema Builder');

        $oeh = set_error_handler(array("self","myErrorHandler"));

        $dbs = array(
            'mysql' =>  new VDB('mysql:host=localhost;port=3306;dbname=test','root',''),
            'sqlite' => new VDB('sqlite::memory:'),
            'pgsql' => new VDB('pgsql:host=localhost;dbname=test','test','1234'),
        );

        foreach( $dbs as $type => $db) {

            $this->set('DB',$db);
            $type .= ': ';
            $db->dropTable('test');

            // create table
            $cr_result = $db->createTable('test');
            $result = $db->getTables();
            $this->expect(
                $cr_result == true && in_array('test',$result) == true,
                $type.'create table',
                $type.'could not create table'
            );

            foreach(array_keys($db->dataTypes) as $index=>$field) {
                // testing column types
                list($r1,$r2) = $db->table('test',
                    function($table) use($index,$field) {
                        return $table->addCol('column_'.$index,$field);
                    },
                    function($table){ return $table->getCols(); });
                $this->expect(
                    $r1 == true && in_array('column_'.$index,$r2) == true,
                    $type.'adding column ['.$field.'], nullable',
                    $type.'cannot add a nullable column of type:'.$field
                );
            }

            // default value text, not nullable
            list($r1,$r2) = $db->table('test',
                function($table){   return $table->addCol('text_default_not_null','TEXT8',false,'foo bar'); },
                function($table){   return $table->getCols(true); });
            $this->expect(
                $r1 == true && in_array('text_default_not_null',array_keys($r2)) == true && $r2['text_default_not_null']['default'] == 'foo bar' && $r2['text_default_not_null']['null'] == false,
                $type.'adding column [TEXT8], not nullable with default value',
                $type.'missmatching default value in not nullable [TEXT8] column'
            );

            // default value numeric, not nullable
            list($r1,$r2) = $db->table('test',
                function($table){   return $table->addCol('int_default_not_null','INT8',false,123); },
                function($table){   return $table->getCols(true); });
            $this->expect(
                $r1 == true && in_array('int_default_not_null',array_keys($r2)) == true && $r2['int_default_not_null']['default'] == 123 && $r2['int_default_not_null']['null'] == false,
                $type.'adding column [INT8], not nullable with default value',
                $type.'missmatching default value in not nullable [INT8] column'
            );

            // default value text, nullable
            list($r1,$r2) = $db->table('test',
                function($table){   return $table->addCol('text_default_nullable','TEXT8',true,'foo bar'); },
                function($table){   return $table->getCols(true); });
            $this->expect(
                $r1 == true && in_array('text_default_nullable',array_keys($r2)) == true && $r2['text_default_nullable']['default'] == 'foo bar',
                $type.'adding column [TEXT8], nullable with default value',
                $type.'missmatching default value in nullable [TEXT8] column'
            );

            // default value numeric, nullable
            list($r1,$r2) = $db->table('test',
                function($table){   return $table->addCol('int_default_nullable','INT8',true,123); },
                function($table){   return $table->getCols(true); });
            $this->expect(
                $r1 == true && in_array('int_default_nullable',array_keys($r2)) == true && $r2['int_default_nullable']['default'] == 123,
                $type.'adding column [INT8], nullable with default value',
                $type.'missmatching default value in nullable [INT8] column'
            );

            // rename column
            list($r1,$r2,$r3) = $db->table('test',
                function($table){ return $table->renameCol('text_default_not_null','title123'); },
                function($table){ return $table->getCols(); });
            $this->expect(
                $r1 == true &&
                in_array('title123',$r2) == true &&
                in_array('text_default_not_null',$r2) == false,
                $type.'renaming column',
                $type.'cannot rename a column'
            );
            $db->table('test', function($table){ return $table->renameCol('title123','text_default_not_null'); });

            // remove column
            list($r1,$r2) = $db->table('test',
                function($table){ return $table->dropCol('title'); },
                function($table){ return $table->getCols(); });
            $this->expect(
                $r1 == true && in_array('title',$r2) == false,
                $type.'removng column',
                $type.'cannot remove a column'
            );

            // rename table
            $db->dropTable('test123');
            $valid = $db->table('test',
                function($table){ return $table->renameTable('test123'); });
            $this->expect(
                $valid == true && in_array('test123',$db->getTables()) == true && in_array('test',$db->getTables()) == false,
                $type.'renaming table',
                $type.'cannot rename a table'
            );
            $db->table('test123',
                function($table){ $table->renameTable('test'); });

            // drop table
            $db->dropTable('test');
            $this->expect(
                in_array('test',$db->getTables()) == false,
                $type.'drop table',
                $type.'cannot not drop table'
            );

        }

        echo $this->render('basic/results.htm');

	}


}
