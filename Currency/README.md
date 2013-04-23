## F3 Currency Rate Plugin

Use this Plugin to get fetch currency rates (+90 major, minor and exotic currenies), convert money values from one currency to another, determine the currency of a selected locale/language and gather some system information about available locales.


some examples:

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



echo $cur->convert(10.50,'EUR','USD'); // 13.64



print_r( $cur->getLocaleCurrency('en_US') );
/*
Array
(
    [code] => USD
    [symbol] => $
)
*/


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