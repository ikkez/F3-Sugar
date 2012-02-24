<?php

/**
Pagination class for the PHP Fat-Free Framework

The contents of this file are subject to the terms of the GNU General
Public License Version 3.0. You may not use this file except in
compliance with the license. Any of the license terms and conditions
can be waived if you get permission from the copyright holder.

Copyright (c) 2012 by ikkez
Christian Knuth <mail@ikkez.de>

@version 1.2.0
 **/

class Pagination {

    private $items_count;
    private $items_per_page;
    private $range = 2;
    private $current_page;
    private $template = 'paginator.html';
    private $routeKey;
    private $linkPath;

    /**
     * create new pagination
     * @param $items array|integer max items or array to count
     * @param $limit int max items per page
     * @param $routeKey string the key for pagination in your routing
     */
    public function __construct( $items, $limit = 10, $routeKey = 'page' ) {
        $this->items_count = is_array($items)?count($items):$items;
        $this->routeKey = $routeKey;
        $this->setLimit($limit);
        $this->setCurrent( F3::exists('PARAMS.'.$routeKey) ? F3::get('PARAMS.'.$routeKey) : 1);
    }

    /**
     * set maximum items shown on one page
     * @param $limit int
     */
    public function setLimit($limit) {
        if(is_numeric($limit)) $this->items_per_page = $limit;
    }

    /**
     * set path for the template file
     * @param $template string
     */
    public function setTemplate($template) {
        $this->template = $template;
    }

    /**
     * set the range of pages, that are displayed prev and next to current page
     * @param $range int
     */
    public function setRange($range) {
        if(is_numeric($limit)) $this->range = $range;
    }

    /**
     * set the current page number
     * @param $current int
     */
    public function setCurrent($current) {
        if(!is_numeric($current)) return;
        if($current <= $this->getMax()) $this->current_page = $current;
        else $this->current_page = $this->getMax();
    }

    /**
     * set path to current routing for link building
     * @param $linkPath
     */
    public function setLinkPath($linkPath) {
        $this->linkPath = (substr($linkPath,0,1) != '/') ? '/'.$linkPath:$linkPath;
        if(substr($this->linkPath,-1) != '/') $this->linkPath .= '/';
    }

    /**
     * returns the current page number
     * @return int
     */
    public function getCurrent() {
        return $this->current_page;
    }

    /**
     * returns the maximum count of items to display in pages
     * @return int
     */
    public function getItemCount() {
        return $this->items_count;
    }

    /**
     * get maximum pages needed to display all items
     * @return int
     */
    public function getMax() {
        return ceil($this->items_count / $this->items_per_page);
    }

    /**
     * get next page number
     * @return int|bool
     */
    public function getNext() {
        $nextPage = $this->current_page + 1;
        if( $nextPage > $this->getMax() ) return false;
        return $nextPage;
    }

    /**
     * get previous page number
     * @return int|bool
     */
    public function getPrev() {
        $prevPage = $this->current_page - 1;
        if( $prevPage < 1 ) return false;
        return $prevPage;
    }

    /**
     * return last page number, if current page is not in range
     * @return bool|int
     */
    public function getLast() {
        return ($this->current_page < $this->getMax() - $this->range ) ? $this->getMax() : false;
    }

    /**
     * return first page number, if current page is not in range
     * @return bool|int
     */
    public function getFirst() {
        return ($this->current_page > 3) ? 1 : false;
    }

    /**
     * return all page numbers within the given range
     * @param $range int
     * @return array page numbers in range
     */
    public function getInRange($range) {
        $current_range = array(($this->current_page-$range < 1 ? 1 : $this->current_page-$range), ($this->current_page+$range > $this->getMax() ? $this->getMax() : $this->current_page+$range));
        for($x = $current_range[0]; $x <= $current_range[1]; ++$x) {
            $rangeIDs[] = $x;
        }
        return $rangeIDs;
    }

    /**
     * returns the number of items left behind for current page
     * @return int
     */
    public function getItemOffset() {
        return ($this->current_page - 1) * $this->items_per_page;
    }

    /**
     * generates the pagination output
     * @return string
     */
    public function serve() {
        if(is_null($this->linkPath)) {
            $route = F3::get('PARAMS.0');
            if(F3::exists('PARAMS.'.$this->routeKey)) {
                $route = str_replace(F3::get('PARAMS.'.$this->routeKey),'',$route);
            } else if(substr($route,-1) != '/') { $route.= '/'; }
        } else $route = $this->linkPath;
        F3::set('pg.route',$route);
        F3::set('pg.currentPage',$this->current_page);
        F3::set('pg.nextPage',$this->getNext());
        F3::set('pg.prevPage',$this->getPrev());
        F3::set('pg.firstPage',$this->getFirst());
        F3::set('pg.lastPage',$this->getLast());
        F3::set('pg.rangePages',$this->getInRange($this->range) );
        $output = Template::serve($this->template);
        F3::clear('pg');
        return $output;
    }
}