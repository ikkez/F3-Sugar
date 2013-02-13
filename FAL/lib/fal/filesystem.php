<?php

namespace FAL;

interface FileSystem
{
    /**
     * determine if the file exists
     * @param $file
     * @return mixed
     */
    public function exists($file);

    /**
     * return file content
     * @param $file
     * @return mixed
     */
    public function read($file);

    /**
     * write file content
     * @param $file
     * @param $content
     * @return mixed
     */
    public function write($file, $content);

    /**
     * delete a file
     * @param $file
     * @return mixed
     */
    public function delete($file);

    /**
     * rename a file or directory
     * @param $from
     * @param $to
     * @return mixed
     */
    public function rename($from, $to);

    /**
     * get last modified date
     * @param $file
     * @return mixed
     */
    public function modified($file);

    /**
     * get filesize in bytes
     * @param $file
     * @return mixed
     */
    public function size($file);

    /**
     * return whether the item is a directory
     * @param $dir
     * @return mixed
     */
    public function isDir($dir);

    /**
     * create new directory
     * @param $dir
     * @return mixed
     */
    public function createDir($dir);

    /**
     * remove a directory
     * @param $dir
     * @return mixed
     */
    public function removeDir($dir);
}