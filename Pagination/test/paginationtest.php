<?php

/**
 *    Pagination Test
 */
class PaginationTest extends \App\Controller {

	static public function init() {
		/** @var \Base $f3 */
		$f3 = \Base::instance();
		$f3->route(array(
				'GET /paginate',
				'GET /paginate/page-@page',
			),	'\PaginationTest->run');
		$f3->menu['/paginate'] = 'Pagination';
	}

	function run(\Base $f3) {
		// db connection
		$db = new \DB\SQL('sqlite:tmp/pagination_test.db');
		$table_name = 'paginate_test_news';
		// create dummy data
		$schema = new \DB\SQL\Schema($db);
		if (!in_array($table_name, $schema->getTables())) {
			$table = $schema->createTable($table_name);
			$table->addColumn('title')->type_varchar();
			$table->addColumn('randomText')->type_varchar();
			$table->build();
			$article = new \DB\SQL\Mapper($db, $table_name);
			for ($i = 1; $i <= 50; $i++) {
				$article->title = \Web::instance()->filler(1,3,false);
				$article->randomText = \Web::instance()->filler(3,5,false);
				$article->save();
				$article->reset();
			}
		}

		$f3->set('UI', 'sugar/Pagination/test/');

		// test pagination
		$mapper = new \DB\SQL\Mapper($db, $table_name);
		$itemsMaxPage = 6;
		$pagebrowser = new \Pagination($mapper->count(), $itemsMaxPage);
		$pagebrowser->setRouteKeyPrefix('page-');

		// load records from db mapper
		$results = $mapper->paginate($pagebrowser->getCurrent()-1, $itemsMaxPage);

		$f3->set('pagebrowser', $pagebrowser->serve());

		// enable custom TAG, if you want to use to
		\Template::instance()->extend('pagebrowser', 'Pagination::renderTag');

		// serve results to template
		$f3->set('results', $results);
	}
	function afterroute() {
		echo \Template::instance()->render('layout.htm');
	}

}