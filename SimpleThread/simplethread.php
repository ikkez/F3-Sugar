<?php
/**
 * SimpleThread - simply spawn background threads used as "async" workers for the PHP Fat-Free Framework
 *
 * The contents of this file are subject to the terms of the GNU General
 * Public License Version 3.0. You may not use this file except in
 * compliance with the license. Any of the license terms and conditions
 * can be waived if you get permission from the copyright holder.
 *
 * Copyright (c) 2018 ~ ikkez
 * Christian Knuth <ikkez0n3@gmail.com>
 * https://github.com/ikkez/
 *
 * @version 0.9.2
 * @date: 06.01.2018
 **/

class SimpleThread extends \Prefab {

	protected
		$f3,
	 	$job_route = 'worker',
		$tasks = [];

	const
		STATUS_SPAWNING = 1,
		STATUS_ACTIVE = 2;

	/**
	 * SimpleWorker constructor.
	 */
	function __construct() {
		$this->f3 = \Base::instance();
		$this->job_route .= '-'.$this->f3->hash($this->f3->SEED);
		$cache = \Cache::instance();
		$this->f3->route('POST /'.$this->job_route.'/@key',
			function(\Base $f3, $args) use ($cache) {
			$f3->abort();
			$name=$args['key'];
			if (isset($this->tasks[$args['key']])) {
				if (!$f3->exists('POST.workerId',$id))
					$id = FALSE;
				else $f3->clear('POST.workerId');
				if ($id && $f3->CACHE){
					$cache->set('worker_'.$id,[$name,self::STATUS_ACTIVE]);
					$f3->copy('UNLOAD','UNLOAD2');
					$f3->UNLOAD = function() use ($id, $f3,$cache) {
						$cache->clear('worker_'.$id);
						$f3->call('UNLOAD2');
					};
				}
				$args=$f3->get('POST');
				$f3->call($this->tasks[$name],[$f3,$args]);
			}
		});
	}

	/**
	 * register a new background task
	 * @param $name
	 * @param $callable
	 */
	function add($name,$callable) {
		$this->tasks[$name]=$callable;
	}

	/**
	 * spawn a new background worker and run a job
	 * @param $name
	 * @param array $data
	 * @return bool
	 */
	function run($name, $data=[]) {
		$url = $this->f3->SCHEME.'://'.$this->f3->HOST.
			(($port=$this->f3->PORT) && !in_array($port,[80,443])?(':'.$port):'').
			$this->f3->BASE.'/'.$this->job_route.'/'.$name;

		$id = $name.'_'.$this->f3->hash(uniqid($this->f3->SEED,true));
		$data['workerId'] = $id;

		if ($this->f3->CACHE && ($cache = \Cache::instance()))
			$cache->set('worker_'.$id,[$name,self::STATUS_SPAWNING]);

		$options = array(
			'method'  => 'POST',
			'header' => ['X-Requested-With: XMLHttpRequest'],
			'timeout' => 0.3,
			'content' => http_build_query($data),
		);
		if ($cookies=$this->f3->get('COOKIE')) {
			$cookie_raw=[];
			foreach ($cookies as $k=>$v)
				$cookie_raw[]=$k.'='.rawurlencode($v);
			$options['header'][]='Cookie: '.implode(';',$cookie_raw);
		}
		$web = \Web::instance();
		$web->engine('socket');
		$response = $web->request($url,$options);

		// only works for cURL, but socket can timeout faster ( < 1s)
//		$rs = 0;
//		$status = preg_grep('/HTTP\/\d(?:\.\d)?/',$response['headers']);
//		if ($status) {
//			$status = explode(' ',$status[0],3);
//			$rs = $status[1];
//		}
//		return $rs == 200;

		return $id;
	}


	/**
	 * find cached worker entries
	 * @param array $workers
	 * @return array
	 */
	function getWorkers($workers=[]) {
		$out = [];
		if ($this->f3->CACHE && !empty($workers)) {
			$cache = \Cache::instance();
			foreach ($workers as $id)
				if ($cache->exists('worker_'.$id,$val))
					$out[] = ['name'=>$val[0],'state'=>$val[1]];
		}
		return $out;
	}
}