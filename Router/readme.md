# Router
###creates named routes for the PHP Fat-Free Framework

With this plugin, you can create named routes in your application and access them easily in your template.

Doing so gives you the possibility to change your route URLs, without touch all links in your template again.

***
### Usage

Copy the `router.php` file in your F3 `lib/` directory.

#### Create Named Routes

To register a new named route, use the register method. It used the same syntax and params like `$f3->route` did, but prepends an additional `$name` argument.

``` php
$router = \Router::instance();

$router->register('newsletter-signin-page','GET /newsletter/subscribe/',function() {
    echo "hi, please fill out the form for subscribing to the newsletter";
});
```

#### Access Route Template

To used these named routes easiely, i've added a little template extension that will parse all your A-Tags. So you just need to write:

``` html
<a route="newsletter-signin-page">subscribe to the newsletter</a>
```
and it will rewrite your link to `<a href="/newsletter/subscribe/">...`.

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

In your html template, use an extra tag attribute, named like your route token and prepend it by `param-` like this:

``` html
<a route="news-view" param-page="news123">read more about News123</a>
```
This will create the link `<a href="/view/news123">`


#### Template Tokens

And last but not least you can use dynamic template tokens too:

``` html
<a route="{{@article_route}}" param-page="{{@article_id}}">read more</a>
```