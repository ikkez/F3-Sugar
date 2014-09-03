<?php

namespace FAL;

interface MetaStorageInterface {

	function save($file, $data, $ttl);

	function load($file,$ttl);

	function delete($file);

}