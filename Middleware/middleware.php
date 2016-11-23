<?php
/**
 *	Middleware Router for the PHP Fat-Free Framework
 *
 *	The contents of this file are subject to the terms of the GNU General
 *	Public License Version 3.0. You may not use this file except in
 *	compliance with the license. Any of the license terms and conditions
 *	can be waived if you get permission from the copyright holder.
 *
 *	Copyright (c) 2016 ~ ikkez
 *	Christian Knuth <ikkez0n3@gmail.com>
 *
 *	@version: 1.1.0
 *	@date: 23.11.2016
 *
 **/

class Middleware extends \Prefab {
	
	/** @var \Base */
	protected $f3;
	
	protected $routes;

	public function __construct() {
		$this->f3 = \Base::instance();
		$this->routes=array();
	}
	
	public function on($event,$pattern,$handler) {
		$bak = $this->f3->ROUTES;
		$this->f3->ROUTES=array();
		$this->f3->route($pattern,$handler);
		$this->routes[$event] = $this->f3->ROUTES;
		$this->f3->ROUTES=$bak;
	}

	public function before($pattern,$handler) {
		$this->on('before',$pattern,$handler);
	}

	public function after($pattern,$handler) {
		$this->on('after',$pattern,$handler);
	}
	
	public function run($event='before') {
		if (!isset($this->routes[$event]))
			return true;
		$paths=[];
		foreach ($keys=array_keys($this->routes[$event]) as $key) {
			$path=preg_replace('/@\w+/','*@',$key);
			if (substr($path,-1)!='*')
				$path.='+';
			$paths[]=$path;
		}
		$vals=array_values($this->routes[$event]);
		array_multisort($paths,SORT_DESC,$keys,$vals);
		$this->routes[$event]=array_combine($keys,$vals);
		// Convert to BASE-relative URL
		$req=$this->f3->rel(urldecode($this->f3->URI));
		foreach ($this->routes[$event] as $pattern=>$routes) {
			if (!$args=$this->f3->mask($pattern,$req))
				continue;
			ksort($args);
			$route=NULL;
			$ptr=$this->f3->CLI?Base::REQ_CLI:$this->f3->AJAX+1;
			if (isset($routes[$ptr][$this->f3->VERB]) ||
				isset($routes[$ptr=0]))
				$route=$routes[$ptr];
			if (!$route)
				continue;
			if ($this->f3->VERB!='OPTIONS' &&
				isset($route[$this->f3->VERB])) {
				if ($this->f3['VERB']=='GET' &&
					preg_match('/.+\/$/',$this->f3['PATH']))
					$this->reroute(substr($this->f3['PATH'],0,-1).
						($this->f3['QUERY']?('?'.$this->f3['QUERY']):''));
				$handler=$route[$this->f3->VERB][0];
				$alias=$route[$this->f3->VERB][3];
				if (is_string($handler)) {
					// Replace route pattern tokens in handler if any
					$handler=preg_replace_callback('/({)?@(\w+\b)(?(1)})/',
						function($id) use($args) {
							$pid=count($id)>2?2:1;
							return isset($args[$id[$pid]])?
								$args[$id[$pid]]:
								$id[0];
						},
						$handler
					);
					if (preg_match('/(.+)\h*(?:->|::)/',$handler,$match) &&
						!class_exists($match[1]))
						$this->f3->error(500,'PreRoute handler not found');
				}
				// Call route handler
				return $this->f3->call($handler,array($this->f3,$args,$alias),
					'beforeroute,afterroute') !== FALSE;
			}
		}
		return true;
	}
}
