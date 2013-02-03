<?php

namespace App;

class Schema extends Controller
{
	function get()
	{
		$f3 = \Base::instance();
		$test = new \Test;

		$f3->set('QUIET', false);

		$dbs = array(
			'mysql' => new \DB\SQL(
				'mysql:host=localhost;port=3306;dbname=fatfree', 'fatfree', ''
			),
			'sqlite' => new \DB\SQL(
				'sqlite::memory:'
//				'sqlite:db/sqlite.db'
			),
			'pgsql' => new \DB\SQL(
				'pgsql:host=localhost;dbname=fatfree','fatfree','fatfree'
			),
		);

		$this->roundTime = microtime(TRUE) - \Base::instance()->get('timer');
		$tname = 'test_table';

		foreach ($dbs as $type => $db) {

			$builder = new \DB\SQL\Schema($db);
			$type .= ': ';
			$builder->dropTable($tname);

			// create table
			$cr_result = $builder->createTable($tname);
			$result = $builder->getTables();
			$test->expect(
				$cr_result == true && in_array($tname, $result),
				$this->getTime().' '.$type.'create table'
			);

			foreach (array_keys($builder->dataTypes) as $index => $field) {
				// testing column types
				$r1 = $builder->addColumn('column_'.$index, $field);
				$r2 = $builder->getCols();
				$test->expect(
					$r1 == true && in_array('column_' . $index, $r2),
					$this->getTime().' '.$type.'adding column [' . $field . '], nullable'
				);
			}

			// adding some testing data
			$ax = new \DB\SQL\Mapper($db, $tname);
			$ax->column_6 = 'hello world';
			$ax->save();
			$ax->reset();
			$result = $ax->find();
			foreach ($result as &$r) {
				$r = $r->cast();
			}
			$test->expect(array_key_exists(0, $result) &&
				$result[0]['column_6'] == 'hello world',
				$this->getTime().' '.$type.'mapping dummy data'
			);

			// default value text, not nullable
			$r1 = $builder->addColumn('text_default_not_null', \DB\SQL\Schema::DT_TEXT8, false,
                'foo bar');
			$r2 = $builder->getCols(true);
			$test->expect(
				$r1 == true &&
					in_array('text_default_not_null', array_keys($r2)) &&
					$r2['text_default_not_null']['default'] == 'foo bar' &&
					$r2['text_default_not_null']['nullable'] == false,
				$this->getTime().' '.$type.'adding column [TEXT8], not nullable with default value'
			);
			unset($ax);
			$ax = new \DB\SQL\Mapper($db, $tname);
			$ax->column_6 = 'tanduay';
			$ax->save();
			$ax->reset();
			$result = $ax->find();
			foreach ($result as &$r) {
				$r = $r->cast();
			}
			$test->expect(array_key_exists(1, $result) &&
					$result[1]['column_6'] == 'tanduay' &&
					$result[1]['text_default_not_null'] == 'foo bar',
				$this->getTime().' '.$type.'mapping dummy data'
			);

			// default value numeric, not nullable
			$r1 = $builder->addColumn('int_default_not_null', \DB\SQL\Schema::DT_INT8, false, 123);
			$r2 = $builder->getCols(true);
			$test->expect(
					$r1 == true &&
					in_array('int_default_not_null', array_keys($r2)) &&
					$r2['int_default_not_null']['default'] == 123 &&
					$r2['int_default_not_null']['nullable'] == false,
				$this->getTime().' '.$type.'adding column [INT8], not nullable with default value'
			);
			unset($ax);
			$ax = new \DB\SQL\Mapper($db, $tname);
			$ax->column_6 = 'test3';
			$ax->save();
			$ax->reset();
			$result = $ax->find();
			foreach ($result as &$r) {
				$r = $r->cast();
			}
			$test->expect(
					array_key_exists(2, $result) &&
					$result[2]['column_6'] == 'test3' &&
					$result[2]['int_default_not_null'] === 123,
				$this->getTime().' '.$type.'mapping dummy data'
			);

			// default value text, nullable
			$r1 = $builder->addColumn('text_default_nullable', \DB\SQL\Schema::DT_TEXT8, true, 'foo bar');
			$r2 = $builder->getCols(true);
			$test->expect(
					$r1 == true &&
					in_array('text_default_nullable', array_keys($r2)) &&
					$r2['text_default_nullable']['default'] == 'foo bar',
				$this->getTime().' '.$type.'adding column [TEXT8], nullable with default value'
			);
			unset($ax);
			$ax = new \DB\SQL\Mapper($db, $tname);
			$ax->column_6 = 'test4';
			$ax->save();
			$ax->reset();
			$ax->column_6 = 'test5';
			$ax->text_default_nullable = null;
			$ax->save();
			$ax->reset();
			$result = $ax->find();
			foreach ($result as &$r) {
				$r = $r->cast();
			}
			$test->expect(
					array_key_exists(3, $result) && array_key_exists(4, $result) &&
					$result[3]['column_6'] == 'test4' && $result[3]['text_default_nullable'] == 'foo bar' &&
					$result[4]['column_6'] == 'test5' && $result[4]['text_default_nullable'] === null,
				$this->getTime().' '.$type.'mapping dummy data'
			);

			// default value numeric, nullable
			$r1 = $builder->addColumn('int_default_nullable', \DB\SQL\Schema::DT_INT8, true, 123);
			$r2 = $builder->getCols(true);
			$test->expect(
				$r1 == true && in_array('int_default_nullable', array_keys($r2)) == true && $r2['int_default_nullable']['default'] == 123,
				$this->getTime().' '.$type.'adding column [INT8], nullable with default value'
			);
			unset($ax);
			$ax = new \DB\SQL\Mapper($db, $tname);
			$ax->column_6 = 'test6';
			$ax->save();
			$ax->reset();
			$ax->column_6 = 'test7';
			$ax->int_default_nullable = null;
			$ax->save();
			$ax->reset();
//			 $db->exec("INSERT INTO $tname (column_6, int_default_nullable) VALUES('test7',NULL);");
			$result = $ax->find();
			foreach ($result as &$r) {
				$r = $r->cast();
			}
			$test->expect(array_key_exists(5, $result) && array_key_exists(6, $result) &&
					$result[5]['column_6'] == 'test6' && $result[5]['int_default_nullable'] === 123 &&
					$result[6]['column_6'] == 'test7' && $result[6]['int_default_nullable'] === null,
				$this->getTime() . ' ' . $type . 'mapping dummy data'
			);

			// current timestamp
			$r1 = $builder->addColumn('stamp', \DB\SQL\Schema::DT_TIMESTAMP, false,
                \DB\SQL\Schema::DF_CURRENT_TIMESTAMP);
			$r2 = $builder->getCols(true);
			$test->expect(
				$r1 == true && in_array('stamp',array_keys($r2)) == true &&
				$r2['stamp']['default'] == \DB\SQL\Schema::DF_CURRENT_TIMESTAMP,
				$this->getTime().' '.$type.
					'adding column [TIMESTAMP], not nullable with current_timestamp default value'
			);

			// rename column
			$r1 = $builder->renameColumn('text_default_not_null', 'title123');
			$r2 = $builder->getCols();
			$test->expect(
				$r1 == true &&
					in_array('title123', $r2) == true &&
					in_array('text_default_not_null', $r2) == false,
				$this->getTime().' '.$type.'renaming column'
			);
			unset($ax);
			$ax = new \DB\SQL\Mapper($db, $tname);
			$ax->title123 = 'test8';
			$ax->save();
			$ax->reset();
			$result = $ax->find();
			foreach ($result as &$r) {
				$r = $r->cast();
			}
			$test->expect(array_key_exists(7, $result) && $result[7]['title123'] == 'test8',
				$this->getTime().' '.$type.'mapping dummy data'
			);
			$builder->alterTable($tname, function ($table) {
				return $table->renameColumn('title123', 'text_default_not_null');
			});

			// remove column
			$r1 = $builder->dropColumn('column_1');
			$r2 = $builder->getCols();
			$test->expect(
				$r1 == true && !empty($r2) && in_array('column_1', $r2) == false,
				$this->getTime().' '.$type.'removng column'
			);

			// rename table
			$builder->dropTable('test123');
			$r1 = $builder->renameTable('test123');
			$test->expect(
				$r1 == true && in_array('test123', $builder->getTables()) &&
					in_array($tname, $builder->getTables()) == false,
				$this->getTime().' '.$type.'renaming table'
			);
			$builder->renameTable($tname);

			// drop table
			$builder->dropTable($tname);
			$test->expect(
				in_array($tname, $builder->getTables()) == false,
				$this->getTime().' '.$type.'drop table'
			);

			// adding composite primary keys
			$builder->createTable($tname);
			$builder->addColumn('version', \DB\SQL\Schema::DT_INT8, false, 1);
			$builder->setPKs(array('id', 'version'));
			$r1 = $builder->getCols(true);

			$test->expect(!empty($r1) &&
				$r1['id']['pkey'] == true && $r1['version']['pkey'] == true,
				$this->getTime().' '.$type.'adding composite primary-keys'
			);
			$test->expect(!empty($r1) &&
				$r1['version']['default'] == '1',
				$this->getTime().' '.$type.'default value on composite primary key'
			);

			// more fields to composite primary key table
			$builder->addColumn('title', \DB\SQL\Schema::DT_TEXT8);
			$builder->addColumn('title2', \DB\SQL\Schema::DT_TEXT8);
			$builder->addColumn('title_notnull', \DB\SQL\Schema::DT_TEXT8, false, "foo");
			$r1 = $builder->getCols(true);
			$test->expect(
				array_key_exists('title', $r1) &&
				array_key_exists('title_notnull', $r1) &&
				$r1['id']['pkey'] == true && $r1['version']['pkey'] == true,
				$this->getTime().' '.$type.'adding more fields to composite pk table'
			);

			// testing primary keys with inserted data
			$ax = new \DB\SQL\Mapper($db, $tname);
			$ax->title = 'test1';
			$ax->save();
			$ax->reset();

			$ax->id = 1;
			$ax->title = 'nullable';
			$ax->version = 2;
			$ax->save();
			$ax->reset();

			$ax->title = 'test3';
			$ax->title2 = 'foobar';
			$ax->title_notnull = 'bar';
			$ax->save();

			$result = $ax->find();
			foreach ($result as &$r) {
				$r = $r->cast();
			}
			$cpk_expected = array(
				0=>array(
					'id' => 1,
					'version' => 1,
					'title' => 'test1',
					'title2' => NULL,
					'title_notnull' => 'foo',
				),
				1=>array(
					'id' => 1,
					'version' => 2,
					'title' => 'nullable',
					'title2' => NULL,
					'title_notnull' => 'foo',
				),
				2=>array(
					'id' => 2,
					'version' => 1,
					'title' => 'test3',
					'title2' => 'foobar',
					'title_notnull' => 'bar',
				),
			);
			foreach ($result as &$r)
				ksort($r);
			foreach ($cpk_expected as &$r)
				ksort($r);
			$test->expect(
				json_encode($result) == json_encode($cpk_expected),
				$this->getTime().' '.$type.'adding items with composite primary-keys'
			);

			$builder->dropTable($tname);

		}
		$f3->set('results', $test->results());
	}

	private $roundTime = 0;

	private function getTime()
	{
		$time = microtime(TRUE) - \Base::instance()->get('timer') - $this->roundTime;
		$this->roundTime = microtime(TRUE) - \Base::instance()->get('timer');
		return ' [ ' . sprintf('%.3f', $time) . 's ]';
	}

}