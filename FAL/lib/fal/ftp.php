<?php

namespace FAL;

class FTP implements FileSystem
{
    protected
        $path,
        $host, $port, $user, $pass,
        $passive,
        $mode,
        $cn;

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
            trigger_error(sprintf('Unable to connect to `%s [%s]´.',
                $this->host, $this->port));
        // login
        if (!ftp_login($this->getConnection(), $this->user, $this->pass)) {
            $this->disconnect();
            trigger_error(sprintf('Fail to login as %s.', $this->user));
        }
        // passive mode
        if ($this->passive && !ftp_pasv($this->getConnection(), true)) {
            $this->disconnect();
            trigger_error('Failed to turn on passive mode.');
        }
        // check path
        if ($this->path != '/') {
            if(!$this->isDir($this->path) && !$this->createDir($this->path)) {                
                $this->disconnect();
                trigger_error('unable to find or create directory');                
            }
            // switch directory
            if (!ftp_chdir($this->getConnection(), $this->path)) {
                $this->disconnect();
                trigger_error(sprintf('Could not switch directory to `%s´',
                    $this->path));
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
        if(!is_resource($this->getConnection())) die('narf');
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
        return ftp_fput($this->getConnection(), $file, $handle, $this->mode);
    }

    public function delete($file)
    {
        return ftp_delete($this->getConnection(), $file);
    }

    public function rename($from, $to)
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

    public function createDir($dir)
    {
        if(!$this->isDir($dir)) {
            $success = ftp_mkdir($this->getConnection(), $dir);
            if (!$success) {
                trigger_error(sprintf('Unable to create directory `%s´.', $dir));
                return false;
            }
        }
        return true;
    }

    public function removeDir($dir)
    {
        if ($this->isDir($dir))
            return ftp_rmdir($this->getConnection(), $dir);
        else
            return false;
    }

    public function __destruct() {
        $this->disconnect();
    }
}