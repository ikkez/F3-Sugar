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
    
    public function listDir($dir=null, $pattern=null, $recursive=false)
    {
        if (is_null($dir) || $dir == '/') $dir = $this->path;
        if (!$this->isDir($dir))
            trigger_error('Scan path is not a valid directory');

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
                    if(!preg_match($pattern,$name)) continue;
                    $item['filename'] = $name;
                    $ext = explode('.',$name);
                    $item['basename'] = $ext[0];
                    $item['extension'] = array_key_exists(1,$ext) ? $ext[1] : '';
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
                trigger_error(sprintf('Unable to create directory `%s´.', $dir));
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