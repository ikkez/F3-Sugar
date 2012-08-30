## F3 Pagination

create quick and easy Pagination for your F3 application.

***

### Usage

1.	Copy Pagination.php into your lib/ folder OR put the file into one of your include paths, you defined in *AUTOLOAD* ( like `F3::set('AUTOLOAD', 'app/;app/classes');` ).

2.	Pagination uses a *template file* to generate the pagebrowser. Put this template in your GUI folder, you defined like `F3::set('GUI','templates/')`

3.	To make the *routing* work, you have to add a new route, containing a token at the end, that could be used by Pagination. 
	
	e.g. if you use a route like `F3::route('GET /list' , 'Blog->listAllArticles');` 
	you have to add this route `F3::route('GET /list/@page' , 'Blog->listAllArticles');`


Within your Controller you can use the following basic code to let the magic begin ;)

	// set maximus items to be shown per page
	$items_per_page = 20;

	// load your itemset and count it all
	$article = new Axon('artikel');
	$article_count = $article->found();

	// build page links
	$pages = new Pagination($article_count, $items_per_page);
	
	// serve generated Pagination to template
	F3::set('paginator', $pages->serve());

	// get items for current page
	$articleList = $article->afind(NULL,'headline asc', $items_per_page, $pages->getItemOffset() );
	
	// do something more with your fetched data
	// and serve it to template
	
	F3::set('artikel',$articleList );

	
Is that easy?!

### Configuration

Of course you can define another token key in your route, instead of `@page`. Therefor just set it as third argument on instantiation (here without @-symbol).

	$pages = new Pagination($article_count, $items_per_page, 'paginationToken');

If your template is within another subdirectory, or you want to use different templates, you can change the template path with:

	$pages->setTemplate('templates/pagebrowser.html');
	
The Paginator builds links, depending on the currenct route. But sometimes you maybe want to serve other pagebrowser link. You can set another link path like this:

	$pages->setLinkPath('search/results/');
	
It will now build URLs like `search/results/1`, `search/results/2`, `search/results/3`.


### Using <pagination> TAG in Templates

Since F3 v2.0.13 it is possible to setup own template tags. You can use that shortcut for Pagination Plugin too. Just register the plugin in the responsible controller like this:
	
	Template::extend('pagination','Pagination::renderTag');
	
You can now use `<pagination items="{{@maxItems}}"></pagination>` to generate the pagination directly form the inside of your template file.
There are also some more options you can pass through the arguments of that tag to the plugin. A fully configured tag could look like this:
`<pagination items="253" limit="15" range="5" src="tmpl/foo/bar/pagination.html" token="articlePage"></pagination>`

Of course your can pass variables to all of those arguments, like `range="{{@range}}"`, too.	


### Styling

With a bit of basic CSS styling, the output could look like this:

![pagebrowser](http://img7.imagebanana.com/img/4j8o59n4/pagebrowser.png)

	div.paginator {
		overflow: hidden;
		margin-bottom: 10px;
	}
		div.paginator a {
			display: block;
			width: 30px;
			height: 20px;
			line-height: 20px;
			margin: 2px;
			border: 1px solid #aaa;
			float: left;
			text-align: center;
			-webkit-border-radius: 4px; 
			-moz-border-radius: 4px; 
			border-radius: 4px;  
			font-size: 0.8em;
		}			
			div.paginator a:hover {
				background: #821109;
				color: #fff !important;
				text-decoration: none;
			}
			
		div.paginator a.active {
			background: #ddd;
		}
			div.paginator a.active:hover {
				color: #821109 !important;			
			}


### some more tuning

-	`setRange($range)`
	based on your current page, it will display the next and previous pages within `$range`. Default is 2. 


### Future

Some TO-DOs for the future

- maybe add tailing slash option for URLs

