<?php
/**
	FooForms - a collection of Form related HTML-Tag handlers

	The contents of this file are subject to the terms of the GNU General
	Public License Version 3.0. You may not use this file except in
	compliance with the license. Any of the license terms and conditions
	can be waived if you get permission from the copyright holder.

	Copyright (c) 2014 ~ ikkez
	Christian Knuth <ikkez0n3@gmail.com>

		@version: 0.1.0
		@date: 12.03.14

 **/

namespace Template;

class FooForms {

	static public function init() {
		\Template::instance()->extend('input','\Template\Tags\Input::render');
		\Template::instance()->extend('select','\Template\Tags\Select::render');
	}

}