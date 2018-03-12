<?php
/**
 * Simple Single User Basic Auth
 *
 * Usage:
 * $auth = new \BasicAuth('admin','e9bGrU8n');
 * if ($auth->basic()) { ... }
 *
 **/

class BasicAuth extends Auth {

	function __construct($user,$pass) {
		parent::__construct('basic', array('id'=>$user, 'pw'=>$pass));
	}

	protected function _basic ($id,$pw,$realm) {

		return (bool) ($this->args['id'] == $id && $pw ==$this->args['pw']);
	}
}