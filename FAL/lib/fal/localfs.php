<?php

namespace FAL;

class LocalFS implements FileSystem
{
    protected $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function exists($file)
    {
        return is_file($this->path.$file);
    }

    public function read($file)
    {
        return file_get_contents($this->path.$file);
    }

    public function write($file, $data)
    {
        return file_put_contents($this->path.$file, $data);
    }

    public function delete($file)
    {
        return @unlink($this->path.$file);
    }

    public function move($from, $to)
    {
        return @rename($this->path.$from, $this->path.$to);
    }

    public function modified($file)
    {
        return filemtime($this->path.$file);
    }

    public function size($file) {
        return filesize($this->path.$file);
    }

    public function isDir($dir)
    {
        return is_dir($this->path.$dir);
    }

    public function createDir($dir)
    {
        if(!$this->isDir($dir)) {
            $old = umask(0);
            $success = @mkdir($this->path.$dir, 0777, true);
            umask($old);
            if (!$success) {
                trigger_error(sprintf('Unable to create the directory `%s´.', $dir));
                return false;
            }
        }
        return true;
    }

    public function removeDir($dir)
    {
        return @rmdir($this->path.$dir);
    }
}