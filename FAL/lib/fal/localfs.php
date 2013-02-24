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

    public function listDir($dir=null,$filter=null,$recursive=false) {
        if(is_null($dir) || $dir = '/') $dir = '';
        if (!$this->isDir($dir))
            trigger_error('Scan path is not a valid directory');

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
                'extension' => $node->getExtension(),
                'size' => $node->getSize(),
            );
        }
        return $list;
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


class FileFilter extends \FilterIterator {

    protected
        $recursive,
        $pattern;

    public function __construct($dir='.', $pattern=null, $recursive=false)
    {
        $this->recursive = $recursive;
        $this->pattern = $pattern;
        if (is_string($dir))
            $dir = ($recursive)
                ? new \RecursiveDirectoryIterator($dir, \FilesystemIterator::UNIX_PATHS)
                : new \FilesystemIterator($dir, \FilesystemIterator::UNIX_PATHS);
        if ($dir instanceof \RecursiveIterator)
                parent::__construct(new \RecursiveIteratorIterator($dir));
        else    parent::__construct($dir);
    }

    public function accept()
    {
        if ($this->isDot()) return false;
        if ($this->pattern) return preg_match($this->pattern, $this->getFilename());
        return true;
    }
}