## F3 Currency Rate Plugin

Use this Plugin to get fetch currency rates (+90 major, minor, exotic currenies and Bitcoin), convert money values from one currency to another, determine the currency of a selected locale/language and gather some system information about available locales.


### some examples


#### get rates, based on a specified currency

``` php
$cur = Currency::instance();

print_r( $cur->getRates('EUR') ); // rates are cached for 24h by default
/*
Array
(
    [AED] => Array
        (
            [value] => 4.77131
            [name] => United Arab Emirates Dirham
            [category] => Middle East
        )

    [...]
)
*/

// for Bitcoin rates:

print_r($cur->getBitcoinRate('USD')); // cached for 1 hour by default

/*
Array
(
    [min] => 127.19900
    [max] => 146.50000
    [avg] => 135.36211
)
*/
```

#### convert a currency

``` php
echo $cur->convert(10.50,'EUR','USD'); // 13.64
```

You can also convert to an average Bitcoin rate.

``` php
echo $cur->convert(50,'EUR','BTC'); // 0.48
```


#### get currency of a locale

``` php
print_r( $cur->getLocaleCurrency('en_US') );
/*
Array
(
    [code] => USD
    [symbol] => $
)
*/
```


#### get a list of installed locales on the server

Notice: currently this does not work on Windows systems.

``` php
print_r( $cur->getInstalledLocales() );
/*
Array
(
    [0] => C
    [1] => POSIX
    [2] => de_DE
    [3] => de_DE.iso88591
    [4] => de_DE.iso885915@euro
    [5] => de_DE.utf8
    [6] => de_DE@euro
    [7] => deutsch
    [8] => en_US
    [9] => en_US.iso88591
    [10] => en_US.iso885915
    [11] => en_US.utf8
    [12] => german
)
*/
```