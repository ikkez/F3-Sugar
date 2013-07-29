<?php
/**
    Router Plugin for the PHP Fat-Free Framework
    
    The contents of this file are subject to the terms of the GNU General
    Public License Version 3.0. You may not use this file except in
    compliance with the license. Any of the license terms and conditions
    can be waived if you get permission from the copyright holder.

    Copyright (c) 2013 by ikkez
    Christian Knuth <mail@ikkez.de>
 
        @version 0.5.0
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
        $expl = explode(' ', $pattern, 2);
        if ($expl[0] === 'MAP')
            $f3->map($expl[1], $handler, $ttl, $kbps);
        else
            $f3->route($pattern,$handler,$ttl,$kbps);
        $f3->set('ROUTES["'.$expl[1].'"].name', $name);
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
        if (!isset($match))
            return false;
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
        $queryString = '';
        if(array_key_exists('route', $attrib)) {
            if (array_key_exists('href', $attrib))
                unset($attrib['href']);
            $r_params = array();
            // process tokens
            $tmp = \Template::instance();
            $route_name = $attrib['route'];
            $absolute = 0;
            $addQueryString = false;
            // find dynamic route token
            if (preg_match('/{{(.+?)}}/s', $route_name))
                $dyn_route_name = $tmp->token($route_name);
            foreach ($attrib as $key => $value) {
                // fetch route token parameters
                if (is_int(strpos($key, 'param-'))) {
                    if (isset($dyn_route_name)) {
                        if (preg_match('/{{(.+?)}}/s', $value))
                            $value = $tmp->token($value);
                        else
                            $value = var_export($value, true);
                        $r_params[] = "'".substr($key, 6)."'=>$value";
                    } else {
                        if (preg_match('/{{(.+?)}}/s', $value))
                            $value = "<?php echo ".$tmp->token($value).";?>";
                        $r_params[substr($key, 6)] = $value;
                    }
                    unset($attrib[$key]);
                }
                // fetch query string
                elseif ($key == 'query') {
                    if (preg_match('/{{(.+?)}}/s', $value))
                        $queryString .= '<?php $qvar = '.$tmp->token($value).'; '.
                            'echo (is_array($qvar)?htmlentities(http_build_query($qvar)):$qvar);?>';
                    else
                        $queryString .= htmlentities($value);
                    unset($attrib[$key]);
                }
                // reuse existing query string in URL
                elseif ($key == 'addQueryString' && strtoupper($value) == 'TRUE') {
                    $addQueryString = true;
                    unset($attrib[$key]);
                }
                // absolute path option
                elseif ($key == 'absolute') {
                    switch(strtoupper($value)) {
                        case 'TRUE':
                            $absolute = 1;
                            break;
                        case 'FULL':
                            $absolute = 2;
                            break;
                        default:
                            $absolute = 0;
                    }
                    unset($attrib[$key]);
                }
                elseif ($key == 'section') {
                    if (preg_match('/{{(.+?)}}/s', $value))
                        $section = '<?php echo '.$tmp->token($value).';?>';
                    else
                        $section = htmlentities($value);
                    unset($attrib[$key]);
                }
            }
            // route url
            if (isset($dyn_route_name)) {
                $r_params = 'array('.implode(',', $r_params).')';
                $attrib['href'] = '<?php echo \Router::instance()->getNamedRoute('.$dyn_route_name.','.
                    $r_params.'); ?>';
            } else
                $attrib['href'] = self::instance()->getNamedRoute($attrib['route'], $r_params);
            // absolute path
            if ($absolute > 0) {
                $attrib['href'] = '/'.$attrib['href'];
                if($absolute > 1) {
                    $f3 = \Base::instance();
                    $attrib['href'] = $f3->get('SCHEME').'://'.$f3->get('HOST').
                        $f3->get('BASE').$attrib['href'];
                }
            }
            // query string
            if($addQueryString && !empty($_SERVER['QUERY_STRING'])) {
                if(!empty($queryString))
                    $queryString = '&'.$queryString;
                $queryString = '<?php echo $_SERVER["QUERY_STRING"];?>'.$queryString;
            }
            if (!empty($queryString))
                $attrib['href'] .= '?'.$queryString;
            if (!empty($section))
                $attrib['href'] .= '#'.$section;
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