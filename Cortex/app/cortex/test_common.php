<?php

use App\Controller;

class Test_Common {

	function run()
	{
		$test = new \Test();
		/** @var \Base $f3 */
		$f3 = \Base::instance();

		$news = new NewsModel();
		$news->load();

		$dummy = array(
			'title'=>'copy test',
			'text'=>'Lorem ipsum dolor sit amet.',
			'author'=>1,
			'tags'=>array(3)
		);
		$f3->set('record1', $dummy);
		$news->copyto('record2');

		$test->expect(
			$f3->exists('record2'),
			'copyto: raw record copied to hive'
		);

		$news->reset();

		$news->copyfrom('record1');

		$test->expect(
			$news->title = 'copy test' &&
			$news->text = 'Lorem ipsum dolor sit amet.',
			'copyfrom: hydrate from hive key'
		);
		$test->expect(
			$news->author instanceof AuthorModel
			&& !$news->author->dry() &&
			$news->tags instanceof \DB\CortexCollection,
			'copyfrom: relations hydrated successful'
		);

		$test->expect(
			$news->get('author',true) == 1,
			'get raw data from relational field'
		);

		$news->reset();
		$news->copyfrom('record2','title;author');

		$test->expect(
			$news->title = 'Responsive Images' &&
			$news->get('author',true) == 2 &&
			$news->text == NULL,
			'copyfrom: limit fields with split-able string'
		);

		$news->reset();
		$news->copyfrom('record2',array('title'));

		$test->expect(
			$news->title = 'Responsive Images' && $news->text == NULL,
			'copyfrom: limit fields by array'
		);

		$news->reset();
		$news->copyfrom($dummy,function($fields) {
			return array_intersect_key($fields,array_flip(array('title')));
		});

		$test->expect(
			$news->title = 'copy test',
			'copyfrom: copy from array instead of hive key'
		);

		$test->expect(
			$news->title = 'copy test' && $news->text == NULL,
			'copyfrom: limit fields by callback function'
		);



		///////////////////////////////////
		return $test->results();
	}
}