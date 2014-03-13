<?php

namespace App;


class FooForms {

	function get(\Base $f3) {

		\Template\FooForms::init();

		$f3->set('fruits', array(
			'apple',
			'banana',
			'peach',
		));

		$f3->set('colors', array(
			'#f00'=>'red',
			'#0f0'=>'green',
			'#00f'=>'blue',
		));

		$f3->set('days', array(
			'mo'=>'monday',
			'tu'=>'tuesday',
			'we'=>'wednesday',
			'th'=>'thursday',
			'fr'=>'friday',
			'sa'=>'saturday',
			'su'=>'sunday'
		));

		echo \Template::instance()->render('templates/fooforms.html');

	}

	function post($f3) {
		$this->get($f3);
	}
} 