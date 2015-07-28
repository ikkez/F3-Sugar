<?php
/**
 *	Abstract TagHandler for creating own Tag-Element-Renderer
 *
 *	The contents of this file are subject to the terms of the GNU General
 *	Public License Version 3.0. You may not use this file except in
 *	compliance with the license. Any of the license terms and conditions
 *	can be waived if you get permission from the copyright holder.
 *
 *	Copyright (c) 2015 ~ ikkez
 *	Christian Knuth <ikkez0n3@gmail.com>
 *
 *	@version: 0.3.0
 *	@date: 14.07.2015
 *
 **/

namespace Template;

abstract class TagHandler extends \Prefab {

	/** @var \Template */
	protected $tmpl;

	function __construct() {
		$this->tmpl = \Template::instance();
	}

	/**
	 * build tag string
	 * @param $attr
	 * @param $content
	 * @return string
	 */
	abstract function build($attr,$content);

	/**
	 * incoming call to render the given node
	 * @param $node
	 * @return string
	 */
	static public function render($node) {
		$attr = $node['@attrib'];
		unset($node['@attrib']);
		/** @var TagHandler $handler */
		$handler = static::instance();
		$content = $handler->resolveContent($node, $attr);
		return $handler->build($attr,$content);
	}

	/**
	 * render the inner content
	 * @param array $node
	 * @param array $node
	 * @return string
	 */
	protected function resolveContent($node, $attr) {
		return (isset($node[0])) ? $this->tmpl->build($node) : '';
	}

	/**
	 * general bypass for unhandled tag attributes
	 * @param array $params
	 * @return string
	 */
	protected function resolveParams(array $params) {
		$out = '';
		foreach ($params as $key => $value) {
			// build dynamic tokens
			if (preg_match('/{{(.+?)}}/s', $value))
				$value = $this->tmpl->build($value);
			if (preg_match('/{{(.+?)}}/s', $key))
				$key = $this->tmpl->build($key);
			// inline token
			if (is_numeric($key))
				$out .= ' '.$value;
			// value-less parameter
			elseif ($value == NULL)
				$out .= ' '.$key;
			// key-value parameter
			else
				$out .= ' '.$key.'="'.$value.'"';
		}
		return $out;
	}

	/**
	 * export a stringified token variable
	 * to handle mixed attribute values correctly
	 * @param $val
	 * @return string
	 */
	protected function tokenExport($val) {
		$split = preg_split('/({{.+?}})/s', $val, -1,
			PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		foreach ($split as &$part) {
			if (preg_match('/({{.+?}})/s', $part))
				$part = $this->tmpl->token($part);
			else
				$part = "'".$part."'";
			unset($part);
		}
		$val = implode('.', $split);
		return $val;
	}
} 