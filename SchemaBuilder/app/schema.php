<?php

namespace App;

class Schema extends Controller
{
	function get()
	{
		$f3 = \Base::instance();
		$test = new \Test;
		$f3->set('title', 'Variable DB Schema Builder');

		// prevent errors breaking the render process,
		// set this to false for debugging purpose
		$f3->set('QUIET', true); // TODO: does not work :-/

		$dbs = array(
			'mysql' => new \DB\SQL(
				'mysql:host=localhost;port=3306;dbname=test', 'root', '',
				array(\PDO::ATTR_STRINGIFY_FETCHES => true)
			),
			'sqlite' => new \DB\SQL(
				'sqlite::memory:'
			),
			'pgsql' => new \DB\SQL(
				'pgsql:host=localhost;dbname=test','test','1234',
				array(\PDO::ATTR_STRINGIFY_FETCHES => true)
			),
		);

		$this->roundTime = microtime(TRUE) - \Base::instance()->get('timer');
		$tname = 'test_table';

		foreach ($dbs as $type => $db) {

			$builder = new \DB\SQL\SchemaBuilder($db);
			$type .= ': ';
			$builder->dropTable($tname);

			// create table
			$cr_result = $builder->createTable($tname);
			$result = $builder->getTables();
			$test->expect(
				$cr_result == true && in_array($tname, $result) == true,
				$this->getTime().' '.$type.'create table'
			);

			foreach (array_keys($builder->dataTypes) as $index => $field) {
				// testing column types
				list($r1, $r2) = $builder->table($tname,
					function ($table) use ($index, $field) {
						return $table->addCol('column_' . $index, $field);
					},
					function ($table) {
						return $table->getCols();
					});
				$test->expect(
					$r1 == true && in_array('column_' . $index, $r2) == true,
					$this->getTime().' '.$type.'adding column [' . $field . '], nullable'
				);
			}

			// adding some testing data
			$ax = new \DB\SQL\Mapper($db, $tname);
			$ax->column_6 = 'hello world';
			$ax->save();
			$ax->reset();
			$result = $ax->find();
			foreach ($result as &$r) $r = $r->cast();

			$test->expect(array_key_exists(0, $result) &&
				$result[0]['column_6'] == 'hello world',
				$this->getTime().' '.$type.'mapping dummy data'
			);

			// default value text, not nullable
			list($r1, $r2) = $builder->table($tname,
				function ($table) {
					return $table->addCol('text_default_not_null', 'TEXT8', false, 'foo bar');
				},
				function ($table) {
					return $table->getCols(true);
				});
			$test->expect(
				$r1 == true &&
					in_array('text_default_not_null', array_keys($r2)) == true &&
					$r2['text_default_not_null']['default'] == 'foo bar' &&
					$r2['text_default_not_null']['null'] == false,
				$this->getTime().' '.$type.'adding column [TEXT8], not nullable with default value'
			);

			unset($ax);
			$ax = new \DB\SQL\Mapper($db, $tname);
			$ax->column_6 = 'tanduay';
			$ax->save();
			$ax->reset();
			$result = $ax->find();
			foreach ($result as &$r) $r = $r->cast();
			$test->expect(array_key_exists(1, $result) &&
					$result[1]['column_6'] == 'tanduay' && $result[1]['text_default_not_null'] == 'foo bar',
				$this->getTime().' '.$type.'mapping dummy data'
			);

			// default value numeric, not nullable
			list($r1, $r2) = $builder->table($tname,
				function ($table) {
					return $table->addCol('int_default_not_null', 'INT8', false, 123);
				},
				function ($table) {
					return $table->getCols(true);
				});
			$test->expect(
				$r1 == true && in_array('int_default_not_null', array_keys($r2)) == true && $r2['int_default_not_null']['default'] == 123 && $r2['int_default_not_null']['null'] == false,
				$this->getTime().' '.$type.'adding column [INT8], not nullable with default value'
			);

			unset($ax);
			$ax = new \DB\SQL\Mapper($db, $tname);
			$ax->column_6 = 'test3';
			$ax->save();
			$ax->reset();
			$result = $ax->find();
			foreach ($result as &$r) $r = $r->cast();
			$test->expect(array_key_exists(2, $result) &&
					$result[2]['column_6'] == 'test3' && $result[2]['int_default_not_null'] == '123',
				$this->getTime().' '.$type.'mapping dummy data'
			);

			// default value text, nullable
			list($r1, $r2) = $builder->table($tname,
				function ($table) {
					return $table->addCol('text_default_nullable', 'TEXT8', true, 'foo bar');
				},
				function ($table) {
					return $table->getCols(true);
				});
			$test->expect(
				$r1 == true && in_array('text_default_nullable', array_keys($r2)) == true && $r2['text_default_nullable']['default'] == 'foo bar',
				$this->getTime().' '.$type.'adding column [TEXT8], nullable with default value'
			);

			unset($ax);
			$ax = new \DB\SQL\Mapper($db, $tname);
			$ax->column_6 = 'test4';
			$ax->save();
			$ax->reset();

			$ax->column_6 = 'test5';
			$ax->text_default_nullable = null; //TODO: not possible in axon, right now?!
			$ax->save();
			$ax->reset();
//			 $db->exec("INSERT INTO $tname (column_6, text_default_nullable) VALUES('test5',NULL);");
			$result = $ax->find();
			foreach ($result as &$r) $r = $r->cast();
			$test->expect(array_key_exists(3, $result) && array_key_exists(4, $result) &&
					$result[3]['column_6'] == 'test4' && $result[3]['text_default_nullable'] == 'foo bar' &&
					$result[4]['column_6'] == 'test5' && $result[4]['text_default_nullable'] == null, // TODO: notice === will not work
				$this->getTime().' '.$type.'mapping dummy data'
			);

			// default value numeric, nullable
			list($r1, $r2) = $builder->table($tname,
				function ($table) {
					return $table->addCol('int_default_nullable', 'INT8', true, 123);
				},
				function ($table) {
					return $table->getCols(true);
				});
			$test->expect(
				$r1 == true && in_array('int_default_nullable', array_keys($r2)) == true && $r2['int_default_nullable']['default'] == 123,
				$this->getTime().' '.$type.'adding column [INT8], nullable with default value'
			);

			// rename column
			list($r1, $r2) = $builder->table($tname,
				function ($table) {
					return $table->renameCol('text_default_not_null', 'title123');
				},
				function ($table) {
					return $table->getCols();
				});
			$test->expect(
				$r1 == true &&
					in_array('title123', $r2) == true &&
					in_array('text_default_not_null', $r2) == false,
				$this->getTime().' '.$type.'renaming column'
			);
			$builder->table($tname, function ($table) {
				return $table->renameCol('title123', 'text_default_not_null');
			});

			// remove column
			list($r1, $r2) = $builder->table($tname,
				function ($table) {
					return $table->dropCol('title');
				},
				function ($table) {
					return $table->getCols();
				});
			$test->expect(
				$r1 == true && in_array('title', $r2) == false,
				$this->getTime().' '.$type.'removng column'
			);

			// rename table
			$builder->dropTable('test123');
			$valid = $builder->table($tname,
				function ($table) {
					return $table->renameTable('test123');
				});
			$test->expect(
				$valid == true && in_array('test123', $builder->getTables()) == true && in_array($tname, $builder->getTables()) == false,
				$this->getTime().' '.$type.'renaming table'
			);
			$builder->table('test123',
				function ($table) use ($tname) {
					$table->renameTable($tname);
				});

			// drop table
			$builder->dropTable($tname);
			$test->expect(
				in_array($tname, $builder->getTables()) == false,
				$this->getTime().' '.$type.'drop table'
			);

			// adding composite primary keys
			$r1 = $builder->create($tname, function ($table) {
				$table->addCol('version', 'INT8', false, 1);
				$table->setPKs(array('id', 'version'));
				return $table->getCols(true);
			});
			$test->expect(!empty($r1) &&
				$r1['id']['primary'] == true && $r1['version']['primary'] == true,
				$this->getTime().' '.$type.'adding composite primary-keys'
			);
			$test->expect(!empty($r1) &&
				$r1['version']['default'] == '1',
				$this->getTime().' '.$type.'default value on composite primary key'
			);

			// more fields to composite primary key table
			$r1 = $builder->table($tname, function ($table) {
				$table->addCol('title', 'TEXT8');
				$table->addCol('title2', 'TEXT8');
				$table->addCol('title_notnull', 'TEXT8', false, "foo");
				return $table->getCols(true);
			});
			$test->expect(
				array_key_exists('title', $r1) == true && array_key_exists('title_notnull', $r1) == true &&
					$r1['id']['primary'] == true && $r1['version']['primary'] == true,
				$this->getTime().' '.$type.'adding more fields to composite pk table'
			);

			// testing primary keys with inserted data
			$ax = new \DB\SQL\Mapper($db, $tname);
			$ax->title = 'test1';
			$ax->save();
			$ax->reset();

			$ax->id = 1;
			$ax->title = 'null';
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
				if ($db->driver() == 'pgsql') $r = array_reverse($r);
			}

			$cpk_expected = array(
				0=>array(
					'id' => 1,
					'version' => 1,
					'title' => 'test1',
//					'title2' => NULL,
					'title2' => '',
					'title_notnull' => 'foo',
				),
				1=>array(
					'id' => 1,
					'version' => 2,
					'title' => 'null',
//					'title2' => NULL,
					'title2' => '',
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

			$test->expect(
				json_encode($result) == json_encode($cpk_expected),
				$this->getTime().' '.$type.'adding items with composite primary-keys'
			);

			$builder->dropTable($tname);

		}
		$f3->set('QUIET', false);
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