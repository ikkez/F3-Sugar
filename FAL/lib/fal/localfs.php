<?php

/**
	Local filesystem adapter

	The contents of this file are subject to the terms of the GNU General
	Public License Version 3.0. You may not use this file except in
	compliance with the license. Any of the license terms and conditions
	can be waived if you get permission from the copyright holder.

		Copyright (c) 2014 by ikkez
		Christian Knuth <ikkez0n3@gmail.com>
		https://github.com/ikkez/F3-Sugar/

		@version 0.9.5
		@date 03.09.2014
 **/

namespace FAL;

class LocalFS implements FileSystem
{
	protected $path;

	const
		TEXT_ScanPathNotValid = 'Scan path is not a valid directory: %s',
		TEXT_DeleteFileFailed = 'Deleting file failed: %s',
		TEXT_RemoveDirFailed = 'Removing Directory failed: %s';

	public function __construct($path) {
		$this->path = $path;
	}

	public function exists($file) {
		return is_file($this->path.$file);
	}

	public function read($file) {
		return file_get_contents($this->path.$file);
	}

	public function write($file, $data) {
		return file_put_contents($this->path.$file, $data);
	}

	public function delete($file) {
		return @unlink($this->path.$file);
	}

	public function move($from, $to) {
		return @rename($this->path.$from, $this->path.$to);
	}

	public function modified($file) {
		return filemtime($this->path.$file);
	}

	public function size($file) {
		if (!$this->isDir($file))
			return filesize($this->path.$file);
		else {
			$size = 0;
			foreach (new \RecursiveIteratorIterator(
					 new \RecursiveDirectoryIterator($this->path.$file)) as $node)
				if ($node->isFile())
					$size += $node->getSize();
			return $size;
		}
	}

	public function isDir($dir) {
		return is_dir($this->path.$dir);
	}

	public function listDir($dir=null, $filter=null, $recursive=false) {
		if(is_null($dir) || $dir == '/') $dir = '';
		if (!$this->isDir($dir))
			trigger_error(sprintf(self::TEXT_ScanPathNotValid,$dir));
		$list = array();
		$it = new FileFilter($this->path.$dir, $filter, $recursive);
		foreach ($it as $node) {
			$path = $node->getPathname();
			if(strpos($path,$this->path.$dir) === 0)
				$path = substr($path, strlen($this->path.$dir));
			$list[$path] = array(
				'filename' => $node->getFilename(),
				'basename' => $node->getBasename(),
				'path' => $node->getPathname(),
				'type' => $node->getType(),
				'extension' => (strnatcmp(phpversion(),'5.3.6') >= 0)
							   ? $node->getExtension()
							   : pathinfo($node->getFilename(), PATHINFO_EXTENSION),
				'size' => $node->getSize(),
			);
		}
		return $list;
	}

	public function createDir($dir, $mode=0777) {
		if(!$this->isDir($dir)) {
			$old = umask(0);
			$success = @mkdir($this->path.$dir, $mode, true);
			umask($old);
			if (!$success) {
				trigger_error(sprintf('Unable to create the directory `%s´.', $dir));
				return false;
			}
		}
		return true;
	}

	public function removeDir($dir) {
		return @rmdir($this->path.$dir);
	}

	public function cleanDir($dir, $filter=null, $recursive=false) {
		if (is_null($dir) || $dir == '/') $dir = '';
		if (!$this->isDir($dir))
			trigger_error(sprintf(self::TEXT_ScanPathNotValid, $dir));
		$it = new FileFilter($this->path.$dir, $filter, $recursive);
		foreach ($it as $node) {
			if ($node->isDir()) {
				if (!@rmdir($node->__toString()))
					trigger_error(sprintf($this::TEXT_RemoveDirFailed, $node->__toString()));
			} elseif (!@unlink($node->__toString()))
				trigger_error(sprintf($this::TEXT_DeleteFileFailed, $node->__toString()));
		}
	}

	public function copyDir($src, $dst, $filter=null, $recursive=true) {
		if (is_null($src) || $src == '/') $src = '';
		if (!$this->isDir($src))
			trigger_error(sprintf(self::TEXT_ScanPathNotValid, $src));
		if (!$this->isDir($dst))
			$this->createDir($dst,\Base::MODE);
		$it = new FileFilter($this->path.$src, $filter, $recursive);
		$log=array();
		foreach ($it as $node_path=>$node) {
			$path = $node->getPathname();
			if (strpos($path, $this->path.$src) === 0)
				$path = substr($path, strlen($this->path.$src));
			$t_path = $dst.$path;
			if ($node->isDir()) {
				if (!is_dir($t_path))
					$this->createDir($t_path, \Base::MODE);
				$log['dir'][] = substr($t_path, strlen($dst));
			} else
				if ($node->isFile()) {
					copy($node_path, $t_path);
					$log['file'][] = substr($t_path, strlen($dst));
				} else
					$log['skipped'][] = substr($node_path, strlen($dst));
		}
		return $log;
	}
}


class FileFilter extends \FilterIterator {

	protected
		$recursive,
		$pattern;

	public function __construct($dir='.', $pattern=null, $recursive=false) {
		$this->recursive = $recursive;
		$this->pattern = $pattern;
		if (is_string($dir))
			$dir = ($recursive)
				? new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($dir, \FilesystemIterator::UNIX_PATHS),
					\RecursiveIteratorIterator::SELF_FIRST)
				: new \FilesystemIterator($dir, \FilesystemIterator::UNIX_PATHS);
		if ($dir instanceof \RecursiveIterator)
				parent::__construct(new \RecursiveIteratorIterator($dir));
		else    parent::__construct($dir);
	}

	public function accept() {
		if ($this->isDot()) return false;
		if ($this->pattern) return preg_match($this->pattern, $this->getFilename());
		return true;
	}
}