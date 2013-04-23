<?php
/**
    Currency Rate Converter for the PHP Fat-Free Framework

    The contents of this file are subject to the terms of the GNU General
    Public License Version 3.0. You may not use this file except in
    compliance with the license. Any of the license terms and conditions
    can be waived if you get permission from the copyright holder.

        Copyright (c) 2013 by ikkez
        Christian Knuth <ikkez0n3@gmail.com>
        https://github.com/ikkez/F3-Sugar/

        @version 0.6.1
        @date 10.04.2013
 **/

class Currency extends Prefab {

    /** @var \Base */
    protected $f3;

    const
        TEXT_RequestRatesFailed = 'Unable to fetch the currency rates feed.',
        TEXT_AmountMustBeNumeric = 'The given amount to convert must be numeric.',
        TEXT_LocaleNotAvailable = 'The given locale `%s` is not installed on this system.';

    /**
     * fetch currency rates based on given base currency
     * @param string $base currency ISO code
     * @param int $ttl caching time in seconds
     * @return array
     */
    public function getRates($base='USD', $ttl=86400) {
        if ($this->f3->exists('RATES.'.$base))
            return $this->f3->get('RATES.'.$base);
        $feed_url = 'http://themoneyconverter.com/rss-feed/'.$base.'/rss.xml';
        $response = \Web::instance()->request($feed_url);
        $file = '<?xml version="1.0" encoding="UTF-8" ?> '.$response['body'];
        @$feed=simplexml_load_string($file);
        if (!$feed) {
            trigger_error(self::TEXT_RequestRatesFailed);
            return false;
        }
        $rates = array();
        foreach($feed->channel->item as $rate) {
            $tc = explode('/',$rate->title);
            $ex = explode(' = ',$rate->description);
            list($val,$name) = explode(' ',$ex[1],2);
            $rates[$tc[0]] = array(
                'value'=>$val,
                'name'=>$name,
                'category'=>(string)$rate->category
            );
        }
        return $this->f3->set('RATES.'.$base,$rates,$ttl);
    }

    /**
     * @param int|float $amount
     * @param string    $from    currency ISO code
     * @param string    $to      currency ISO code
     * @param int|bool  $decimal precision
     * @return float|bool
     */
    public function convert($amount, $from, $to, $decimal = 2) {
        $rates = $this->getRates($from);
        if (!is_float($amount) && !is_numeric($amount)) {
            trigger_error(self::TEXT_AmountMustBeNumeric);
            return false;
        }
        $result = $rates[$to]['value'] * $amount;
        return $decimal ? round($result,$decimal) : $result;
    }

    /**
     * get the currency ISO code and symbol of the given locale
     * e.g. en_US, array( code => USD, symbol => $ )
     * @param null $locale
     * @return array|bool
     */
    public function getLocaleCurrency($locale = null) {
        $current_lang = $this->f3->split($this->f3->get('LANGUAGE'));
        if ($locale) {
            if (is_array($locales = $this->getInstalledLocales()) 
                && !in_array($locale, $locales))
                trigger_error(sprintf(self::TEXT_LocaleNotAvailable,$locale));
            setlocale(LC_MONETARY, $locale);
        } else
            setlocale(LC_MONETARY, $current_lang[0]);
        $conv = localeconv();
        if (array_key_exists('int_curr_symbol',$conv))
            $currency_code = trim($conv['int_curr_symbol']);
        if (array_key_exists('currency_symbol',$conv))
            $currency_symbol = trim($conv['currency_symbol']);
        if ($locale)
            setlocale(LC_MONETARY, $current_lang[0]);
        if (empty($currency_code) && empty($currency_symbol))
            return false;
        return array('code'=>$currency_code,'symbol'=>$currency_symbol);
    }

    /**
     * returns a list of available locales on this server
     * does not work on Windows, or with activated SAFE mode
     * @return array|bool
     */
    public function getInstalledLocales() {
        if (function_exists('shell_exec') && !stristr(PHP_OS, 'WIN'))
            return explode("\n",trim(shell_exec('locale -a')));
        else return false;
    }

    public function __construct() {
        $this->f3 = \Base::instance();
    }
}