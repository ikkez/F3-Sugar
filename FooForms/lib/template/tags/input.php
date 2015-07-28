<?php
/**
 *	Input TagHandler
 *
 *	The contents of this file are subject to the terms of the GNU General
 *	Public License Version 3.0. You may not use this file except in
 *	compliance with the license. Any of the license terms and conditions
 *	can be waived if you get permission from the copyright holder.
 *
 *	Copyright (c) 2015 ~ ikkez
 *	Christian Knuth <ikkez0n3@gmail.com>
 *
 *	@version: 0.2.0
 *	@date: 14.04.2015
 *
 **/

namespace Template\Tags;

class Input extends \Template\TagHandler {

	function __construct() {
		/** @var \Base $f3 */
		$f3 = \Base::instance();
		if (!$f3->exists('template.form.srcKey'))
			$f3->set('template.form.srcKey','POST');
		parent::__construct();
	}

	/**
	 * build tag string
	 * @param $attr
	 * @param $content
	 * @return string
	 */
	function build($attr, $content) {
		$srcKey = \Base::instance()->get('template.form.srcKey');
		if (isset($attr['type']) && isset($attr['name'])) {
			$name = $this->tokenExport($attr['name']);
			if ($attr['type'] == 'checkbox') {
				$value = $this->tokenExport(isset($attr['value'])?$attr['value']:'on');
				// basic match
				$str = '(isset(@'.$srcKey.'['.$name.']) && @'.$srcKey.'['.$name.']=='.$value.')';
				// dynamic array match
				if (preg_match('/({{.+?}})/s', $attr['name'])) {
					$str.= ' || (isset(@'.$srcKey.'[substr('.$name.',0,-2)]) && is_array(@'.$srcKey.'[substr('.$name.',0,-2)])'.
						' && in_array('.$value.',@'.$srcKey.'[substr('.$name.',0,-2)]))';
				}
				// static array match
				elseif (preg_match('/(\[\])/s', $attr['name'])) {
					$name=substr($attr['name'],0,-2);
					$str='(isset(@'.$srcKey.'['.$name.']) && is_array(@'.$srcKey.'['.$name.'])'.
						' && in_array('.$value.',@'.$srcKey.'['.$name.']))';
				}
				$str = '{{'.$str.'?\'checked="checked"\':\'\'}}';
				$attr[] = $this->tmpl->build($str);

			} elseif ($attr['type'] == 'radio' && isset($attr['value'])) {
				$value = $this->tokenExport(isset($attr['value'])?$attr['value']:'on');
				$attr[] = $this->tmpl->build('{{ isset(@'.$srcKey.'['.$name.']) && '.
					'@'.$srcKey.'['.$attr['name'].']=='.$value.'?\'checked="checked"\':\'\'}}');
			} elseif($attr['type'] != 'password' && !array_key_exists('value',$attr)) {
				// all other types, except password fields
				$ar_name = preg_replace('/\'*(\w+)(\[.*\])\'*/i','[$1]$2',$name,-1,$i);
				$name = $i ? $ar_name : '['.$name.']';
				$attr['value'] = $this->tmpl->build('{{ isset(@'.$srcKey.''.$name.')?@'.$srcKey.''.$name.':\'\'}}');
			}
		}
		// resolve all other / unhandled tag attributes
		$attr = $this->resolveParams($attr);
		// create element and return
		return '<input'.$attr.' />';
	}
}