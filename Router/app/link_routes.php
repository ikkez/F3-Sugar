<?php

namespace App;

class Link_Routes extends Controller
{

    function get($f3)
    {

        if (!$f3->exists('GET.foo'))
            $f3->reroute($f3->get('PARAMS.0').'?foo=bar');

        $router = \Router::instance();
        $router->register('home', 'GET /routing-2', function () {
            echo "test";
        });
        $router->register('article-view', 'GET /article/view/@item', function ($f3, $params) {
            echo "article";
        });
        $router->register('article-action', 'GET /article/@action/@item', function ($f3, $params) {
            echo "works";
        });

        $router->register('class', 'MAP /routing-map', '\Foo\RouterMap');

        $f3->set('rr', 'article-action');
        $f3->set('t1', 'groovy');
        $f3->set('querystring', 'foo=bar&baz=narf');
        $f3->set('querystringArray', array('foo' => 'bar', 'baz' => 'narf'));
        $f3->set('section', 'anchor-2');
        $f3->set('class', 'button');
        $f3->set('param', 'rel="lightbox"');

        $template = \Template::instance();
        $test = new \Test;

        $result = $template->render('templates/link_routes.html');
        $lines = explode("\n", $result);

        $test->expect(
            trim(array_shift($lines)) == '<a href="test1">simple link</a>',
            'simple link'
        );
        $test->expect(
            trim(array_shift($lines)) == '<a href="article/view/foo2">route with param</a>',
            'route with param'
        );
        $test->expect(
            trim(array_shift($lines)) == '<a href="routing-2">simple route</a>',
            'simple route 2'
        );
        $test->expect(
            trim(array_shift($lines)) == '<a href="article/view/groovy">vars by token</a>',
            'resolve route param token'
        );
        $test->expect(
            trim(array_shift($lines)) == '<a href="article/edit/groovy">multiple params</a>',
            'multiple params'
        );
        $test->expect(
            trim(array_shift($lines)) == '<a href="article/view/$blah">inject test</a>',
            'inject test'
        );
        $test->expect(
            trim(array_shift($lines)) == '<a href="routing-map">map test</a>',
            'map test'
        );
        $test->expect(
            trim(array_shift($lines)) == '<a href="routing-2?foo=bar&amp;baz=narf">query string test</a>',
            'query test'
        );

        $test->expect(
            trim(array_shift($lines)) == '<a href="routing-2?foo=bar&amp;baz=narf">query string from string token</a>',
            'query string from string token'
        );

        $test->expect(
            trim(array_shift($lines)) == '<a href="routing-2?foo=bar&amp;baz=narf">query string from array token</a>',
            'query string from array token'
        );

        $test->expect(
            trim(array_shift($lines)) == '<a href="/routing-2">absolute path</a>',
            'absolute path'
        );

        $test->expect(
            trim(array_shift($lines)) == '<a href="'.$f3->get('SCHEME').'://'.$f3->get('HOST').
            $f3->get('BASE').'/routing-2">full absolute path with host and base</a>',
            'full absolute path with host and base'
        );

        $test->expect(
            trim(array_shift($lines)) == '<a href="routing-2?foo=bar">add existing query string to link</a>',
            'add existing query string to link'
        );

        $test->expect(
            trim(array_shift($lines)) == '<a href="routing-2#anchor-1">link with anchor section</a>',
            'link with #anchor section'
        );

        $test->expect(
            trim(array_shift($lines)) == '<a href="routing-2#anchor-2">link anchor from token</a>',
            'link #anchor from token'
        );

        $test->expect(
            trim(array_shift($lines)) == '<a href="test1" class="button">1. simple link with token</a>',
            'simple link with token'
        );

        $test->expect(
            trim(array_shift($lines)) == '<a href="test2" rel="lightbox">2. link with inline token</a>',
            'link with inline token'
        );

        $test->expect(
            trim(array_shift($lines)) == '<a href="test3" rel="lightbox" class="btn">3. link, inline token, params</a>',
            'link, inline token, params'
        );

        $test->expect(
            trim(array_shift($lines)) == '<a class="button" href="routing-2">4. route with parameter token</a>',
            'route with parameter token'
        );

        $test->expect(
            trim(array_shift($lines)) == '<a rel="lightbox" href="routing-2">5. route, inline token</a>',
            'route with inline token'
        );

        $test->expect(
            trim(array_shift($lines)) == '<a rel="lightbox" class="btn" href="routing-2">6. route, inline token, params</a>',
            'route with inline token and param'
        );

        $test->expect(
            trim(array_shift($lines)) == '<a href="test4" disabled>link with value-less parameter</a>',
            'link with value-less parameter'
        );

        $f3->set('results', $test->results());
    }

}
