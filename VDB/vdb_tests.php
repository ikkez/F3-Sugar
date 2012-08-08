<?php

class VDB_Tests extends F3instance {

	function run() {
        $this->set('title','Variable DB');


        $dbs = array(
            'mysql' => new VDB(
                'mysql:host=localhost;port=3306;dbname=test',
                'root',
                ''
            ),
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

            // add column
            $result1 = $db->addCol('test','title','TEXT8');
            $this->expect(
                $result1 == true && in_array('title',$db->getCols('test')) == true,
                $type.'adding column',
                $type.'cannot add a column'
            );

            // rename column
            $result1 = $db->renameCol('test','title','title123');
            $this->expect(
                $result1 == true &&
                in_array('title123',$db->getCols('test')) == true &&
                in_array('title',$db->getCols('test')) == false,
                $type.'renaming column',
                $type.'cannot rename a column'
            );
            $result1 = $db->renameCol('test','title123','title');

            // remove column
            $result1 = $db->removeCol('test','title');
            $this->expect(
                $result1 == true && in_array('title',$db->getCols('test')) == false,
                $type.'removng column',
                $type.'cannot remove a column'
            );

            // rename table
            $db->dropTable('test123');
            $valid = $db->renameTable('test','test123');
            $this->expect(
                $valid == true && in_array('test123',$db->getTables()) == true && in_array('test',$db->getTables()) == false,
                $type.'renaming table',
                $type.'cannot rename a table'
            );
            $db->renameTable('test123','test');

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
