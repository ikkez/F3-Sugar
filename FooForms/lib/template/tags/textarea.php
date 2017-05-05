<?php
/**
 *	Textarea TagHandler
 *
 *	The contents of this file are subject to the terms of the GNU General
 *	Public License Version 3.0. You may not use this file except in
 *	compliance with the license. Any of the license terms and conditions
 *	can be waived if you get permission from the copyright holder.
 *
 *	Copyright (c) 2015 ~ ikkez
 *	Christian Knuth <ikkez0n3@gmail.com>
 *
 *	@version: 0.1.0
 *	@date: 05.05.2017
 *
 **/

namespace Template\Tags;

class Textarea extends \Template\TagHandler {

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

		if (isset($attr['name'])) {
			$name = $this->attrExport($attr['name']);
			$content = $this->tmpl->build('{{ isset(@'.$srcKey.$name.')?@'.$srcKey.$name.':"'.$content.'"}}');
		}

		// resolve all other / unhandled tag attributes
		if ($attr!=null)
			$attr = $this->resolveParams($attr);

		// create element and return
		return '<textarea'.$attr.'>'.$content.'</textarea>';
	}
}