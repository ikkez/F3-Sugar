<?php

/**
    MetaFileStorage saves a json encoded meta file
    beside the regular resource file to store user defined key-value pairs

    The contents of this file are subject to the terms of the GNU General
    Public License Version 3.0. You may not use this file except in
    compliance with the license. Any of the license terms and conditions
    can be waived if you get permission from the copyright holder.

    Copyright (c) 2013 by ikkez
    Christian Knuth <ikkez0n3@gmail.com>
    https://github.com/ikkez/F3-Sugar/

        @version 1.0.0
        @date 15.02.2013
 **/

namespace FAL;

class MetaFileStorage implements MetaStorageInterface {

    protected
        $metaFileMask,
        $fs,
        $f3,
        $data;

    public function __construct(\FAL\FileSystem $fs, $metaFileMask = '%s.meta')
    {
        $this->metaFileMask = $metaFileMask;
        $this->fs = $fs;
        $this->f3 = \Base::instance();
    }

    /**
     * save meta data
     * @param $file
     * @param $data
     * @param $ttl
     * @return bool
     */
    public function save($file, $data, $ttl)
    {
        $cacheHash = $this->getCacheHash($file);
        if($this->fs->write($this->getMetaFilePath($file), json_encode($data))) {
            if ($this->f3->get('CACHE')) {
                $cache = \Cache::instance();
                if ($ttl)
                    $cache->set($cacheHash, $data, $ttl);
                elseif ($cache->exists($cacheHash))
                    $cache->clear($cacheHash);
            }
            $this->data = $data;
            return true;
        } else return false;
    }

    /**
     * return meta data
     * @param $file
     * @param $ttl
     * @return mixed
     */
    public function load($file,$ttl)
    {
        $cache = \Cache::instance();
        $cacheHash = $this->getCacheHash($file);
        if ($this->f3->get('CACHE') && $ttl && ($cached = $cache->exists(
            $cacheHash, $content)) && $cached + $ttl > microtime(TRUE)
        ) {
            $this->data = $content;
        } elseif ($this->fs->exists($metaFile = $this->getMetaFilePath($file))) {
            $this->data = json_decode($this->fs->read($metaFile), true);
            if ($this->f3->get('CACHE') && $ttl)
                $cache->set($cacheHash, $this->data, $ttl);
        }
        return $this->data;
    }

    /**
     * delete meta record
     * @param $file
     */
    public function delete($file)
    {
    	$metaFile = $this->getMetaFilePath($file);
    	if ($this->fs->exists($metaFile))
            $this->fs->delete($metaFile);
        if ($this->f3->get('CACHE')) {
            $cache = \Cache::instance();
            if ($cache->exists($cacheHash = $this->getCacheHash($file)))
                $cache->clear($cacheHash);
        }
    }

    /**
     * rename meta file
     * @param $current
     * @param $new
     */
    public function move($current,$new)
    {
        $metaFile = $this->getMetaFilePath($current);
    	if ($this->fs->exists($metaFile)) {
            $this->fs->move($metaFile,$this->getMetaFilePath($new));
            if ($this->f3->get('CACHE')) {
                $cache = \Cache::instance();
                if ($cache->exists($cacheHash = $this->getCacheHash($current)))
                    $cache->clear($cacheHash);
            }
        }
    }

    /**
     * compute meta file path
     * @param string $file
     * @return mixed
     */
    protected function getMetaFilePath($file)
    {
        $parts = pathinfo($file);
        $metaFilename = sprintf($this->metaFileMask, $parts['basename']);
        return str_replace($parts['basename'], $metaFilename, $file);
    }

    /**
     * return cache key
     * @param $file
     * @return string
     */
    protected function getCacheHash($file)
    {
        $fs_class = explode('\\', get_class($this->fs));
        return $this->f3->hash($this->f3->stringify($file)).
            '.'.strtolower(array_pop($fs_class)).'.meta';
    }

}