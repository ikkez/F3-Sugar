<?php
/**
    Router Plugin for the PHP Fat-Free Framework
    
    The contents of this file are subject to the terms of the GNU General
    Public License Version 3.0. You may not use this file except in
    compliance with the license. Any of the license terms and conditions
    can be waived if you get permission from the copyright holder.

    Copyright (c) 2013 by ikkez
    Christian Knuth <mail@ikkez.de>
 
        @version 0.4.0
        @date: 24.04.13 
 **/

class Router extends Prefab {

    /**
     * register a new route
     * @param     $name
     * @param     $pattern
     * @param     $handler
     * @param int $ttl
     * @param int $kbps
     */
    public function register($name,$pattern,$handler,$ttl=0,$kbps=0)
    {
        if(is_array($pattern))
            trigger_error('set multiple routes are not supported');
        $f3 = \Base::instance();
        $f3->route($pattern,$handler,$ttl,$kbps);
        $expl = explode(' ',$pattern,2);
        $f3->set('ROUTES["'.$expl[1].'"].name',$name);
    }

    /**
     * returns URL of a named route
     * @param string  $name route name
     * @param array   $params dynamic route tokens
     * @return string
     */
    public function getNamedRoute($name, $params = null)
    {
        $f3 = \Base::instance();
        $routes = $f3->get('ROUTES');
        foreach($routes as $path=>$route)
            if(array_key_exists('name',$route) && $route['name'] == $name) {
                $match = substr($path,1);
                break;
            }
        if(!empty($params)) {
            $params = array_flip($params);
            foreach($params as $val=>&$token)
                $token='@'.$token;
            $match = str_replace(array_values($params),array_keys($params),$match);
        }
        return $match;
    }

    /**
     * link parser template helper
     * @param array $node
     * @return string
     */
    static public function renderLink(array $node)
    {
        $attrib = $node['@attrib'];
        $params = '';
        if(array_key_exists('route', $attrib)) {
            if (array_key_exists('href', $attrib))
                unset($attrib['href']);
            $r_params = array();
            // find route params
            foreach ($attrib as $key => $value)
                if (is_int(strpos($key,'param-'))) {
                    $r_params[substr($key,6)] = $value;
                    unset($attrib[$key]);
                }
            $attrib['href'] = self::instance()->getNamedRoute($attrib['route'],$r_params);
            unset($attrib['route']);
        }
        foreach ($attrib as $key => $value)
            $params .= ' '.$key.'="'.$value.'"';
        return '<a'.$params.'>'.$node[0].'</a>';
    }

    /**
     * init template extension
     */
    public function __construct()
    {
        Template::instance()->extend('a', 'Router::renderLink');
    }
}