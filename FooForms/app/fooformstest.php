<?php

class FooFormsTest extends \Prefab {

	protected $repoPath;

	function __construct($repoPath='sugar/FooForms/') {
		$this->repoPath = $repoPath;
	}

	static public function init() {
		/** @var \Base $f3 */
		$f3 = \Base::instance();
		$f3->route('GET|POST /fooforms','\FooFormsTest->run');
		$f3->menu['/fooforms'] = 'FooForms';
	}

	function run(\Base $f3) {

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
		$f3->UI = $this->repoPath.'ui/';
		echo \Template::instance()->render('templates/fooforms.html');

	}

} 