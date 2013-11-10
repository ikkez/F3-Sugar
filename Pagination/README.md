# F3 Pagination

With this plugin you can create a quick and easy to use pagebrowser for your F3 application. 

Requires [PHP Fat-Free Framework](https://github.com/bcosca/fatfree) 3.1.2.

***

## Preview

The pagebrowser ships with default [bootstrap](http://getbootstrap.com/components/#pagination) class usage, which can look like this:

![pagebrowser](https://dl.dropboxusercontent.com/u/3077539/_linked/pagebrowser.jpg)

## Usage

### 1. Install

Copy pagination.php into your `lib/` folder OR put the file into one of your include paths, you have defined in `AUTOLOAD` (like `$f3->set('AUTOLOAD', 'app/;app/includes');` ).

The Pagination plugin uses a **template file** to generate the pagebrowser. Put this template (`pagebrowser.html`) in your UI folder. (i.e. if you've defined an UI dir like `$f3->set('UI','templates/')`, then move the pagebrowser template in there)

### 2. Routing

To make the routing between pages work, you need to add a new route, containing a token, that could be used by our Pagebrowser.

e.g. if you already use a route like `$f3->route('GET /list', 'Blog->listAllArticles');`
you have to add this route too: `$f3->route('GET /list/@page', 'Blog->listAllArticles');`

You can also group them into a single route statement like this:

``` php
$f3->route(array(
    'GET /list',
    'GET /list/@page'
    ), 'Blog->listAllArticles');
```

### 3. Paginate your Records

Within your controller you need to paginate over your records. The most easiest way to do so, is to use a data mapper and its [paginate](http://fatfreeframework.com/cursor#paginate) method.

``` php
$article = new \DB\SQL\Mapper($f3->get('DB'),'article');

$limit = 10;
$page = \Pagination::findCurrentPage();
$filter = array('active = ?',1);
$option = array('order' => 'datetime DESC');

$subset = $article->paginate($page-1, $limit, $filter, $option);

$f3 = \Base::instance();
$f3->set('articleList', $subset);
```

Now you got the subset of your records in the `articleList` f3 hive key. It's an array that contains information about the pagination state and the subset itself with all records as data mapper objects.
Have a look at the [paginate](http://fatfreeframework.com/cursor#paginate) method for detailed description. 

But basically, you can loop through the list by using this snippet:

``` html
<F3:repeat group="{{ @articleList.subset }}" value="{{ @article }}">
  <h2>{{ @article.title }}</h2>
  <p>{{ @article.text }}</p>
</F3:repeat>
```


### 4. Create the PageBrowser

#### Method A: Custom Tag

The easiest way to create the pagebrowser now, is to use the custom template tag renderer. This generates the pagebrowser directly from the inside of your template.
Therefor just register the pagebrowser right at the start in your `index.php`:

``` php
\Template::instance()->extend('pagebrowser','\Pagination::renderTag');
```

Now you can use this view helper in your HTML template:

``` html
<F3:pagebrowser items="{{ @articleList.total }}" limit="{{ @articleList.limit }}"/>
```

And you're done! Additional configuration can be done by adding more tag parameters (see below).

#### Method B: render it yourself within your controller

``` php
// [...]
$f3->set('articleList', $subset);
// we continue after the previous example about setting up the record pagination

// build page links
$pages = new Pagination($subset['total'], $subset['limit']);
// add some configuration if needed
$pages->setTemplate('templates/pagebrowser-advanced.html');
// for template usage, serve generated pagebrowser to the hive
$f3->set('pagebrowser', $pages->serve());
```

Now you can use `{{ @pagebrowser | raw }}` in your template, to insert the pagebrowser.


### Configuration

Of course you can define another token key in your route, instead of `@page`. Therefor just set it as third argument on instantiation (here without @-symbol).

``` php
$pages = new Pagination($article_count, $items_per_page, 'paginationToken');
```

If your template is within another sub-directory, or you want to use different templates, you can change the template path with:

``` php
$pages->setTemplate('templates/pagebrowser.html');
```

The Paginator builds links, depending on the current route. But sometimes you maybe want to serve other pagebrowser link. You can set another link path like this:

``` php
$pages->setLinkPath('search/results/');
```

It will now build URLs like `search/results/1`, `search/results/2`, `search/results/3`.

You can also prefix your page links for a better visual and SEO experience:

``` php
$pages->setRouteKeyPrefix('page-');
```

Now your page links will look like these: `list/page-1`, `list/page-2`, `list/page-3`

You can also alter the range of next and previous pages, based on the current page (Default is 2):

``` php
$pages->setRange(5);
```

***

Of course you can set all of these options in the custom tag too. Just have a look at this fully configured example tag:

``` html
<F3:pagebrowser items="{{@articleList.total}}" limit="{{ @articleList.limit }}" src="templates/pagebrowser.html" range="5" link-path="/search/results/" token="articlePage" token-prefix="page-" />
```

You can also pass template variables to all of those arguments, like `range="{{@range}}"`.
