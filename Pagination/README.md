## F3 Pagination

create quick and easy Pagination for your F3 appication.

***

### Usage

1.	First of all, be sure that the class is loaded by F3's *autoloader*. Simply put the file into one of your include paths, you defined like `F3::set('AUTOLOAD', 'app/;app/classes');`.

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

Of course you can define another token key in your route, instead of `@page`. Therefor just set it as third argument on instantiation.

	$pages = new Pagination($article_count, $items_per_page, 'paginationToken');

if your template is within another subdirectory, or you want to use different templates, you can change the template path with:

	$pages->setTemplate('templates/pagebrowser.html');

