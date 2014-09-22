<?php

namespace FAL;

class FileStream {

	protected
		$pos,
		$stream,
		$protocol;

	public function __construct() {
		$this->protocol = 'fal';
		$this->register($this->protocol);
	}

	public function register($protocol) {
		if (!in_array($protocol, stream_get_wrappers()) &&
			!stream_wrapper_register($protocol, get_class($this))
		)
			trigger_error('Failed to register `'.$protocol.'` protocol');
	}

	public function getProtocolName() {
		return $this->protocol;
	}

	public function stream_open($path, $mode, $options, &$opened_path) {
		$url = parse_url($path);
		$f3 = \Base::instance();
		if(!$f3->exists('FileStream.'.$this->protocol.'.'.$url["host"]))
			$f3->set('FileStream.'.$this->protocol.'.'.$url["host"],'');
		$this->stream = &$f3->ref('FileStream.'.$this->protocol.'.'.$url["host"]);
		$this->pos = 0;
		if (!is_string($this->stream)) return false;
		return true;
	}

	public function stream_read($count) {
		$ret = substr($this->stream, $this->pos, $count);
		$this->pos += strlen($ret);
		return $ret;
	}

	public function stream_write($data){
		$this->stream = $data;
		return strlen($data);
	}

	public function stream_tell() {
		return $this->pos;
	}

	public function stream_eof() {
		return $this->pos >= strlen($this->stream);
	}

	public function stream_seek($offset, $whence) {
		$l = strlen($this->stream);
		switch ($whence) {
			case SEEK_SET: $newPos = $offset; break;
			case SEEK_CUR: $newPos = $this->pos + $offset; break;
			case SEEK_END: $newPos = $l + $offset; break;
			default: return false;
		}
		if ($ret = ($newPos >= 0 && $newPos <= $l)) $this->pos = $newPos;
		return $ret;
	}

	public function stream_stat() {
		$stats = array(
			'dev' => 1,
			'ino' => 0,
			'mode' => 33204,
			'nlink' => 1,
			'uid' => 0,
			'gid' => 0,
			'rdev' => 0,
			'size' => strlen($this->stream),
//            'atime'=>time(),
//            'mtime'=>time(),
//            'ctime'=>time(),
			'blksize' => -1,
			'blocks' => -1,
		);
		return array_merge(array_values($stats), $stats);
	}

	public function url_stat($path, $flag=0) {
		return $this->stream_stat();
	}

}
