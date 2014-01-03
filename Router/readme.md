# Router
###creates named routes for the PHP Fat-Free Framework

With this plugin, you can create named routes in your application and access them easily in your template.

Doing so gives you the possibility to change your route URLs, without touching all links in your templates again.

***
### Usage

Copy the `router.php` file into your F3 `lib/` directory.
Fetch the Router instance and register some routes before calling the `$f3->run()` method.
``` php
$router = \Router::instance();
```

#### Create Named Routes

To register a new named route, use the register method. It uses the same syntax and params as `$f3->route`, but prepends an additional `$name` argument.

``` php
$router->register( string $name, string $pattern, callback|string $handler, [ int $ttl = 0] , [ int $kbps = 0 ]);
```

Have a look at this example:

``` php
$router->register('newsletter-signin-page','GET /newsletter/subscribe/',function($f3, $params) {
    echo "hi, please fill out the form for subscribing to the newsletter";
});
$router->register('newsletter-signin-page', 'POST /newsletter/subscribe/', '\App\NewsController');
```

**Notice:** You need to use the same route name for different HTTP Verbs of the same URL.

To create a named route that is mapped to a class, just use **MAP** as HTTP Verb in your pattern. In example:

``` php
$router->register('news-map', 'MAP /news', '\App\NewsController');
```

#### Access Routes from Template

To use these named routes easily, i've added a little template extension that will parse all your `<A>`-tags, so you just need to write:

``` html
<a route="newsletter-signin-page">subscribe to the newsletter</a>
```
and it will rewrite your link to `<a href="newsletter/subscribe/">...`.

You can also call the render method by yourself:

``` html
<form method="post" action="{{Router::instance()->getNamedRoute('newsletter-submit')}}">
 <!-- ... -->
</form>
```

#### Route Params

There is also support for token parameters in your route.

``` php
$router->register('news-view','GET /view/@page','NewsController->viewPage');
```

To fill that token, use an extra attribute in your `<a>`-tag, named like your route token and prepended by `param-`, like this:

``` html
<a route="news-view" param-page="news123">read more about News123</a>
```

This will create the link `<a href="view/news123">`.


#### Template Tokens

You can also use dynamic template tokens in most of the available arguments.

``` html
<a route="{{@article_route}}" param-page="{{@article_id}}">read more</a>
```

#### Tag Parameter List

<table>
    <tr>
        <th>name</th>
        <th>value</th>
        <th>description</th>
        <th>token</th>
    </t>
    <tr>
        <td>route</td>
        <td>string</td>
        <td>the name of the registered route</td>
        <td>[x]</td>
    </tr>
    <tr>
        <td>param-{item}</td>
        <td>string</td>
        <td>the name of a token used in the route</td>
        <td>[x]</td>
    </tr>
    <tr>
        <td>query</td>
        <td>string | array</td>
        <td>adds a query string like <code>foo=bar&page=1</code> to the url</td>
        <td>[x]</td>
    </tr>
    <tr>
        <td>addQueryString</td>
        <td>boolean</td>
        <td>If this is enabled, the current SERVER.QUERY_STRING is added to the link</td>
        <td>[ ]</td>
    </tr>
    <tr>
        <td>absolute</td>
        <td>false, true or full</td>
        <td>When set to <b>TRUE</b>, the link is going to be prepended by a leading <code>/</code>, which makes the link absolute.
        If set to <b>FULL</b> the link becomes a full absolute path including the <code>http://mydomain.com/appdir/</code> part.</td>
        <td>[ ]</td>
    </tr>
    <tr>
        <td>section</td>
        <td>string</td>
        <td>This adds a section #anchor to your link</td>
        <td>[x]</td>
    </tr>
</table>
