## SimpleThread

This plugin can be used to spawn pseudo workers than can run async tasks in the background.
It's using a socket loop to the F3 application with a hidden route to perform any tasks that are time consuming and can be skipped in the normal application flow.
This is probably not the best solution, but as least it's a pretty good workaround when `pthreads` is not available on the server.

### Usage

First add a background task:

```php
SimpleThread::instance()->add('log',function(\Base $f3, $args) {
	$name = 'default';
	if (isset($args['name']))
		$name = $args['name'];

	for ($i = 1; $i<=5;$i++) {
		sleep(rand(3,5));
		$f3->LOGGER->write($name.' - '.$i);
	}
});
```

Now run the task with one or more workers:

````php
$f3->route('GET /test-async', function(\Base $f3) {
	$workers = [];
	$workers[] = SimpleThread::instance()->run('foo');
	$workers[] = SimpleThread::instance()->run('foo', ['name' => 'bar']);
	$workers[] = SimpleThread::instance()->run('foo', ['name' => 'narf']);
	$f3->set('SESSION.workers',$workers);
	echo "New workers spawned: ".var_export($workers,true);
});
````

Watch the results how the tasks are handled asynchronously:

```
Mon, 12 Mar 2018 18:00:58 +0100 [::1] bar - 1
Mon, 12 Mar 2018 18:00:58 +0100 [::1] narf - 1
Mon, 12 Mar 2018 18:01:00 +0100 [::1] default - 1
Mon, 12 Mar 2018 18:01:02 +0100 [::1] bar - 2
Mon, 12 Mar 2018 18:01:03 +0100 [::1] default - 2
Mon, 12 Mar 2018 18:01:03 +0100 [::1] narf - 2
Mon, 12 Mar 2018 18:01:06 +0100 [::1] default - 3
Mon, 12 Mar 2018 18:01:07 +0100 [::1] bar - 3
Mon, 12 Mar 2018 18:01:08 +0100 [::1] narf - 3
Mon, 12 Mar 2018 18:01:09 +0100 [::1] default - 4
Mon, 12 Mar 2018 18:01:10 +0100 [::1] bar - 4
Mon, 12 Mar 2018 18:01:13 +0100 [::1] default - 5
Mon, 12 Mar 2018 18:01:13 +0100 [::1] bar - 5
Mon, 12 Mar 2018 18:01:13 +0100 [::1] narf - 4
Mon, 12 Mar 2018 18:01:17 +0100 [::1] narf - 5
```

While the workers are running, you can check their state with something like this:

````php
$f3->route('GET /check-worker', function($f3) {
	$workers = SimpleThread::instance()->getWorkers($f3->get('SESSION.workers'));
	echo "Your workers are active: ".var_export($workers,true);
	if (empty($workers))
		$f3->clear('SESSION.workers');
});
````

Resulting in

```php
Your workers are active: array ( 
	0 => array ( 'name' => 'foo', 'state' => 2, ), 
	1 => array ( 'name' => 'foo', 'state' => 2, ), 
	2 => array ( 'name' => 'foo', 'state' => 2, ),
)
```

### Notice

This is still experimental and using real [Threads](http://php.net/manual/de/class.thread.php) with the php `thread` module is the way to go, if available.
