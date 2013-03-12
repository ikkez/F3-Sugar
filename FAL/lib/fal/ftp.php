<?php

/**
    FTP filesystem adapter

    The contents of this file are subject to the terms of the GNU General
    Public License Version 3.0. You may not use this file except in
    compliance with the license. Any of the license terms and conditions
    can be waived if you get permission from the copyright holder.

        Copyright (c) 2013 by ikkez
        Christian Knuth <ikkez0n3@gmail.com>
        https://github.com/ikkez/F3-Sugar/

        @version 0.9.1
        @date 08.02.2013
 **/
 
namespace FAL;

class FTP implements FileSystem
{
    protected
        $path,
        $host, $port, $user, $pass,
        $passive,
        $mode,
        $cn;

    const
        TEXT_CONNECT = 'Unable to connect to `%s [%s]´.',
        TEXT_LOGIN_FAILED = 'Fail to login as %s.',
        TEXT_PASSIVE_MODE = 'Failed to turn on passive mode.',
        TEXT_MOUNT_DIR = 'Unable to find or create directory to mount on.',
        TEXT_CHANGE_DIR = 'Could not switch directory to `%s´.',
        TEXT_CREATE_DIR = 'Unable to create directory `%s´.',
        TEXT_CONNECTION_RESOURCE = 'Connection problem. Enable PASSIVE mode to solve that.',
        TEXT_EXIST_DIR = 'Connection problem. Enable PASSIVE mode to solve that.';

    public function __construct($path,$host,$user='anonymous',$pass='',
                                $port=21,$passive=false, $mode=FTP_BINARY)
    {
        $this->path = ($path[0]!='/')?'/'.$path:$path;
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->port = $port;
        $this->mode = $mode;
        $this->passive = $passive;
    }

    protected function connect()
    {
        // open connection
        if (!$this->cn = ftp_connect($this->host, $this->port))
            trigger_error(sprintf(self::TEXT_CONNECT, $this->host, $this->port));
        // login
        if (!ftp_login($this->getConnection(), $this->user, $this->pass)) {
            $this->disconnect();
            trigger_error(sprintf(self::TEXT_LOGIN_FAILED, $this->user));
        }
        // passive mode
        if ($this->passive && !ftp_pasv($this->getConnection(), true)) {
            $this->disconnect();
            trigger_error(self::TEXT_PASSIVE_MODE);
        }
        // check path
        if ($this->path != '/') {
            if(!$this->isDir($this->path) && !$this->createDir($this->path)) {
                $this->disconnect();
                trigger_error(self::TEXT_MOUNT_DIR);
            }
            // switch directory
            if (!ftp_chdir($this->getConnection(), $this->path)) {
                $this->disconnect();
                trigger_error(sprintf(self::TEXT_CHANGE_DIR, $this->path));
            }
        }
    }

    public function disconnect() {
        if (is_resource($this->cn))
            ftp_close($this->cn);
    }

    public function getConnection() {
        if (!is_resource($this->cn))
            $this->connect();
        return $this->cn;
    }

    public function exists($file)
    {
        $res = ftp_size($this->getConnection(), $file);
        return ($res != -1) ? true : false;
    }

    public function read($file)
    {
        $handle = fopen('php://temp', 'r+');
        if(!is_resource($this->getConnection()))
            trigger_error(self::TEXT_CONNECTION_RESOURCE);
        if (!ftp_fget($this->getConnection(), $handle, $file, $this->mode))
            return false;
        rewind($handle);
        $content = stream_get_contents($handle);
        return $content;
    }

    public function write($file, $data)
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $data);
        rewind($handle);
        if (!is_resource($this->getConnection()))
            trigger_error(self::TEXT_CONNECTION_RESOURCE);
        return ftp_fput($this->getConnection(), $file, $handle, $this->mode);
    }

    public function delete($file)
    {
        return ftp_delete($this->getConnection(), $file);
    }

    public function move($from, $to)
    {
        return ftp_rename($this->getConnection(),$from, $to);
    }

    public function modified($file)
    {
        return ftp_mdtm($this->getConnection(), $file);
    }

    public function size($file)
    {
        return ftp_size($this->getConnection(), $file);
    }

    public function isDir($dir)
    {
        if ($dir == '/')
            return true;
        if (!@ftp_chdir($this->getConnection(), $dir))
            return false;
        ftp_chdir($this->getConnection(), $this->path);
        return true;
    }
    
    public function listDir($dir=null, $filter=null, $recursive=false)
    {
        if (is_null($dir) || $dir == '/') $dir = $this->path;
        if (!$this->isDir($dir))
            trigger_error(sprintf(self::TEXT_EXIST_DIR, $dir));
        if (is_array($rawlist = @ftp_rawlist($this->getConnection(), $dir, $recursive))) {
            ftp_chdir($this->getConnection(), $this->path);
            $list = array();
            $subdir = '';
            foreach ($rawlist as $node) {
                if(empty($node)) continue;
                if(substr($node,-1)==':') {
                    $subdir = substr($node,0,-1).'/';
                    continue;
                }
                $chunks = preg_split("/\s+/", $node);
                list($item['rights'], $item['num'], $item['owner'], $item['group'],
                     $item['size'], $item['month'], $item['day'], $item['time']) = $chunks;
                $item['type'] = ($chunks[0]{0} === 'd') ? 'dir' : 'file';
                array_splice($chunks, 0, 8);
                $name = implode(" ", $chunks);
                if($name != '.' && $name != '..') {
                    if($filter && !preg_match($filter,$name)) continue;
                    $item['filename'] = $name;
                    $ext = explode('.',$name);
                    $item['extension'] = (count($ext)>1) ? array_pop($ext) : null;
                    $item['basename'] = implode('.',$ext);
                    $relpath = $subdir;
                    if (strpos($relpath, $dir) === 0)
                        $relpath = substr($relpath, strlen($dir));
                    $item['path'] = $this->path.$dir.$relpath.$name;
                    $list[$relpath.$name] = $item;
                }
            }
            return $list;
        }
    }

    public function createDir($dir)
    {
        if(!$this->isDir($this->path.$dir)) {
            $success = ftp_mkdir($this->getConnection(), $dir);
            if (!$success) {
                trigger_error(sprintf(self::TEXT_CREATE_DIR, $dir));
                return false;
            }
        }
        return true;
    }

    public function removeDir($dir)
    {
        if ($this->isDir($this->path.$dir))
            return ftp_rmdir($this->getConnection(), $dir);
        else
            return false;
    }

    public function __destruct() {
        $this->disconnect();
    }
}