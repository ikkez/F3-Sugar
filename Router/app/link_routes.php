<?php

namespace App;

class Link_Routes extends Controller {

	function get($f3) {

        if(!$f3->exists('GET.foo'))
            $f3->reroute($f3->get('PARAMS.0').'?foo=bar');

        $f3->set('rr', 'artikel-action');
        $f3->set('t1', 'groovy');
        $f3->set('querystring', 'foo=bar&baz=narf');
        $f3->set('querystringArray', array('foo'=>'bar','baz'=>'narf'));

        $template = \Template::instance();
        $test = new \Test;

        $result = $template->render('templates/link_routes.html');
        $lines = explode("\n",$result);

		$test->expect(
			trim(array_shift($lines)) == '<a href="test1">simple route</a><br/>',
			'simple route'
		);
		$test->expect(
			trim(array_shift($lines)) == '<a href="artikel/view/foo2">route with param</a><br/>',
			'route with param'
		);
		$test->expect(
			trim(array_shift($lines)) == '<a href="routing-2">simple route</a><br/>',
			'simple route 2'
		);
		$test->expect(
			trim(array_shift($lines)) == '<a href="artikel/view/groovy">vars by token</a><br/>',
			'resolve route param token'
		);
		$test->expect(
			trim(array_shift($lines)) == '<a href="artikel/edit/groovy">multiple params</a><br/>',
			'multiple params'
		);
		$test->expect(
			trim(array_shift($lines)) == '<a href="artikel/view/$blah">inject test</a><br/>',
			'inject test'
		);
		$test->expect(
			trim(array_shift($lines)) == '<a href="routing-map">map test</a><br/>',
			'map test'
		);
		$test->expect(
			trim(array_shift($lines)) == '<a href="routing-2?foo=bar&amp;baz=narf">query string test</a><br/>',
			'query test'
		);

        $test->expect(
			trim(array_shift($lines)) == '<a href="routing-2?foo=bar&amp;baz=narf">query string from string token</a><br/>',
			'query string from string token'
		);

        $test->expect(
			trim(array_shift($lines)) == '<a href="routing-2?foo=bar&amp;baz=narf">query string from array token</a><br/>',
			'query string from array token'
		);

        $test->expect(
			trim(array_shift($lines)) == '<a href="/routing-2">absolute path</a><br/>',
			'absolute path'
		);

        $test->expect(
			trim(array_shift($lines)) == '<a href="'.$f3->get('SCHEME').'://'.$f3->get('HOST').
            $f3->get('BASE').'/routing-2">full absolute path with host and base</a><br/>',
			'full absolute path with host and base'
		);

        $test->expect(
			trim(array_shift($lines)) == '<a href="routing-2?foo=bar">add existing query string to link</a><br/>',
			'add existing query string to link'
		);

		$f3->set('results',$test->results());
	}

}
