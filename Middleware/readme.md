## Middleware Router

It's a little router, which is based on the core F3 router, that can be called independently before or after the main routing cycle.
This can be useful if you want to hook into a group of other routes and want to do something right before processing the main route handler.

```php
$f3 = require('lib/base.php');

// imagine you have some admin routes
$f3->route('GET|POST /admin','Controller\Admin->login');
$f3->route('POST /admin','Controller\Admin->login');
// and these actions should be protected
$f3->route('GET|POST /admin/@action','Controller\Admin->@action');
$f3->route('GET|POST /admin/@action/@type','Controller\Admin->@action');
$f3->route('PUT /admin/upload','Controller\Files->upload');

// so just add a global pre-route to all at once
\Middleware::instance()->before('GET|POST /admin/*', function(\Base $f3, $params) {
	// do auth checks
});

\Middleware::instance()->run();
$f3->run();
```

Of course you could also use the `beforeroute` and `afterroute` events in your controller to add that auth check functionality. But in case your controller structure isn't ready yet for easy implementation or you have things you strictly want to separate from your controllers, like settings. Here the Middleware Router will aid you.

```php
// enable the CORS settings only for your API routes:
\Middleware::instance()->before('GET|HEAD|POST|PUT|OPTIONS /api/*', function(\Base $f3) {
	$f3->set('CORS.origin','*');
});
```