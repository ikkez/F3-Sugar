# Flash

A little plugin to add simple Flash Messages and Flash Keys.


To add a message (or multiple) that should only be displayed once in your template on the next request, just do:

```php
\Flash::instance()->addMessage('You did that wrong.', 'danger');
// or 
\Flash::instance()->addMessage('It worked!', 'success');
```

And to display that in your templates do:

```html
<!-- bootstrap style-->
<F3:repeat group="{{ \Flash::instance()->getMessages() }}" value="{{ @msg }}">
<div class="alert alert-{{ @msg.status }} alert-dismissable">
  <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
  {{ @msg.text | esc }}
</div>
</F3:repeat>
```

That's it.

If you need, you could also add simple keys:


```php
$flash = \Flash::instance()
$f3->set('FLASH', $flash);
$flash->setKey('highlight','bg-success'); // with value
$flash->setKey('show-hint'); // without returns just TRUE
$flash->setKey('error','Catastrophic error occured! ');
```

for use cases like:

```html
<div class="box {{ @FLASH->getKey('highlight') }}">
  <F3:check if="{{ @FLASH->getKey('show-hint') }}">
  <p>It's new !!!</p>
  </F3:check>
  ...
</div>
```

```html
<F3:check if="{{ @@FLASH && @FLASH->hasKey('error') }}">
    <p>{{ @FLASH->getKey('error') }}</p>
</F3:check>
```
