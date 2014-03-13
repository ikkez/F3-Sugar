<?php

namespace Template\Tags;

class Input extends \Template\TagHandler {

	/**
	 * build tag string
	 * @param $attr
	 * @param $content
	 * @return string
	 */
	function build($attr, $content) {
		if (isset($attr['type']) && isset($attr['name'])) {
			if ($attr['type'] == 'text' || $attr['type'] == 'email') {
				if (!(isset($attr['value']) && !empty($attr['value']))) {
					$name = $attr['name'];
					$name = $this->makeInjectable($name);
					$attr['value'] = $this->template->build('{{ isset(@POST['.$name.'])?@POST['.$name.']:\'\'}}');
				}
			} elseif ($attr['type'] == 'checkbox') {
				$value = $this->makeInjectable(isset($attr['value'])?$attr['value']:'on');
				$name = $this->makeInjectable($attr['name']);
				// basic match
				$str = '(isset(@POST['.$name.']) && @POST['.$name.']=='.$value.')';
				// dynamic array match
				if (preg_match('/({{.+?}})/s', $attr['name'])) {
					$str.= ' || (isset(@POST[substr('.$name.',0,-2)]) && is_array(@POST[substr('.$name.',0,-2)])'.
						' && in_array('.$value.',@POST[substr('.$name.',0,-2)]))';
				}
				// static array match
				elseif (preg_match('/(\[\])/s', $attr['name'])) {
					$name=substr($attr['name'],0,-2);
					$str='(isset(@POST['.$name.']) && is_array(@POST['.$name.'])'.
						' && in_array('.$value.',@POST['.$name.']))';
				}
				$str = '{{'.$str.'?\'checked="checked"\':\'\'}}';
				$attr[] = $this->template->build($str);

			} elseif ($attr['type'] == 'radio' && isset($attr['value'])) {
				$value = $this->makeInjectable(isset($attr['value'])?$attr['value']:'on');
				$name = $this->makeInjectable($attr['name']);
				$attr[] = $this->template->build('{{ isset(@POST['.$name.']) && '.
					'@POST['.$attr['name'].']=='.$value.'?\'checked="checked"\':\'\'}}');
			}
		}
		// resolve all other / unhandled tag attributes
		$attr = $this->resolveParams($attr);
		// create element and return
		return '<input'.$attr.' />';
	}
}