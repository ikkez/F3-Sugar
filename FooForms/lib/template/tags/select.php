<?php

namespace Template\Tags;

class Select extends \Template\TagHandler {

	/**
	 * build tag string
	 * @param $attr
	 * @param $content
	 * @return string
	 */
	function build($attr, $content) {
		if (array_key_exists('group', $attr)) {
			$attr['group'] = $this->template->token($attr['group']);
			$name = $this->makeInjectable($attr['name']);
			$content .= '<?php foreach('.$attr['group'].' as $key => $val) {?>'.
						$this->template->build('<option value="{{@key}}"'.
							'{{(isset(@POST['.$name.']) && @POST['.$name.']==@key)?'.
							'\' selected="selected"\':\'\'}}>{{@val}}</option>').
						'<?php } ?>';
			unset($attr['group']);
		}
		// resolve all other / unhandled tag attributes
		$attr = $this->resolveParams($attr);
		// create element and return
		return '<select'.$attr.'>'.$content.'</select>';
	}
}