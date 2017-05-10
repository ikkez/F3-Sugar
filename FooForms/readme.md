# FooForms

This is a little plugin that offers additional form-related HTML tag handlers for easy creating and working with form elements.

check out the demo page at [f3.ikkez.de/fooforms](http://f3.ikkez.de/fooforms)


### Testsuite

To add the tests to the fatfree-dev testing bench:

```php
// FooForms Demo
// *************************************************** //
$f3->concat('AUTOLOAD',',sugar/FooForms/lib/,sugar/FooForms/app/');
\FooFormsTest::instance()->init('sugar/FooForms/');
```