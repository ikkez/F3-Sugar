<?php

namespace FAL;

class Dropbox implements FileSystem {

    protected
        $appKey,
        $appSecret,
        $authToken,
        $authSecret,
        $authParams;
    /** @var \Base */
    protected $f3;

    /** @var \Web */
    protected $web;

    const
        E_APIERROR = 'Dropbox API Error: %s';

    public function __construct($appKey,$appSecret) {
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
        $this->f3 = \Base::instance();
        $this->web = \Web::instance();
        $this->web->engine('curl');
        $this->authToken = $this->f3->get('SESSION.dropbox.authToken');
        $this->authSecret = $this->f3->get('SESSION.dropbox.authSecret');
        $this->authParams = array(
            'oauth_consumer_key' => $this->appKey,
            'oauth_token' => $this->authToken,
            'oauth_version' => '1.0',
            'oauth_signature' => $this->appSecret.'&'.$this->authSecret,
            'oauth_signature_method' => 'PLAINTEXT',
         // 'oauth_timestamp' => strftime("%a, %d %b %Y %H:%M:%S %Z",time()),
        );
    }

    public function login() {
        $this->requestToken();
        $this->authorize('http://localhost/web/git/fatfree_ikkez/dropbox-login-complete');
    }

    public function requestToken(){
        $url = 'https://api.dropbox.com/1/oauth/request_token';
        $params = $this->authParams;
        unset($params['oath_token']);
        $result = $this->web->request($url,array(
            'method'=>'POST',
            'content'=> http_build_query($params)
        ));
        parse_str($result['body'], $output);
        if (array_key_exists('oauth_token_secret',$output) &&
            array_key_exists('oauth_token', $output))
        {
            $this->authToken = $output['oauth_token'];
            $this->authSecret = $output['oauth_token_secret'];
            $this->f3->set('SESSION.dropbox.authToken',$this->authToken);
            $this->f3->set('SESSION.dropbox.authSecret',$this->authSecret);
        } else {
            $result = json_decode($result['body'], true);
            trigger_error('OAuth failed: '.$result['error']);
        }
    }

    public function authorize($callback_url = NULL){
        $url = 'https://www.dropbox.com/1/oauth/authorize';
        $params = array(
            'oauth_token' => $this->f3->get('SESSION.dropbox.authToken'),
            'locale ' => $this->f3->get('LANGUAGE'),
        );
        if($callback_url) $params['oauth_callback'] = $callback_url;
        header('Location: '.$url.'?'.http_build_query($params));
        exit();
    }

    public function accessToken(){
        $url = 'https://api.dropbox.com/1/oauth/access_token';

        $result = $this->web->request($url, array(
             'method' => 'POST',
             'content' => http_build_query($this->authParams)
        ));
        parse_str($result['body'], $output);
        if (!count(array_diff(array('oauth_token','oauth_token_secret','uid'),
            array_keys($output))))
        {
            $this->authToken = $output['oauth_token'];
            $this->authSecret = $output['oauth_token_secret'];
            $this->uid = $output['uid'];
            $this->f3->set('SESSION.dropbox.authToken', $this->authToken);
            $this->f3->set('SESSION.dropbox.authSecret', $this->authSecret);
            return true;
        } else {
            $result = json_decode($result['body'], true);
            trigger_error('OAuth failed: '.$result['error']);
            return false;
        }
    }

    public function getAccountInfo() {
        $url = 'https://api.dropbox.com/1/account/info';
        $result = $this->web->request($url, array(
         'method' => 'POST',
         'content' => http_build_query($this->authParams)
        ));
        $result_body = json_decode($result['body'], true);
        if (!array_key_exists('error', $result_body)) {
            return $result_body;
        } else {
            trigger_error(sprintf(self::E_APIERROR,$result_body['error']));
            return false;
        }
    }

    /**
     * return file content
     * @param        $file
     * @param null   $rev
     * @param string $type
     * @return mixed
     */
    public function read($file, $rev=NUll, $type='sandbox')
    {
        $url = 'https://api-content.dropbox.com/1/files/'.$type.'/'.$file;
        $params = $this->authParams;
        if($rev) $params['rev'] = $rev;
        $url .= '?'.http_build_query($params);
        $result = $this->web->request($url, array(
             'method' => 'GET',
        ));
        if(!$result_body = json_decode($result['body'], true))
            return $result['body'];
        else {
            trigger_error(sprintf(self::E_APIERROR, $result_body['error']));
            return false;
        }
    }

    /**
     * determine if the file exists
     * @param        $file
     * @param bool   $hidden
     * @param null   $rev
     * @param string $type
     * @return mixed
     */
    public function exists($file, $hidden = false, $rev = NULL, $type = 'sandbox')
    {
        return $this->metadata($file, false, true, $hidden, $rev, $type);
    }

    /**
     * list directory contents
     * @param string $file
     * @param bool   $hidden
     * @param null   $rev
     * @param string $type
     * @return bool|mixed
     */
    public function listDir($file='', $hidden=false, $rev = NUll, $type = 'sandbox')
    {
        return $this->metadata($file, true, false, $hidden, $rev, $type);
    }

    /**
     * get file information
     * @param        $file
     * @param null   $rev
     * @param string $type
     * @return bool|mixed
     */
    public function fileInfo($file,$rev = NUll, $type = 'sandbox') {
        return $this->metadata($file,false,false,true,$rev,$type);
    }

    /**
     * perform meta request
     * @param        $file
     * @param bool   $list
     * @param bool   $existCheck
     * @param bool   $hidden
     * @param null   $rev
     * @param string $type
     * @return bool|mixed
     */
    protected function metadata($file,$list=true,$existCheck=false,
                                $hidden=false,$rev=NULL, $type='sandbox')
    {
        $url = 'https://api.dropbox.com/1/metadata/'.$type.'/'.$file;
        $params = $this->authParams;
        $params['list'] = $list;
        if ($rev) $params['rev'] = $rev;
        if ($list) $params['include_deleted'] = 'false';
        $url .= '?'.http_build_query($params);
        $result = $this->web->request($url, array('method' => 'GET'));
        $result_body = json_decode($result['body'], true);
        if (!array_key_exists('error', $result_body)) {
            if($existCheck) {
                if($result_body['is_deleted'])
                    return ($hidden) ? true : false;
                else return true;
            }
            else return $result_body;
        } else {
            if($existCheck) return false;
            trigger_error(sprintf(self::E_APIERROR, $result_body['error']));
            return false;
        }
    }

    /**
     * write file content
     * @param        $file      file path
     * @param        $content   file content
     * @param string $type      sandbox or dropbox
     * @return mixed
     */
    public function write($file, $content, $type='sandbox')
    {
        $url = 'https://api-content.dropbox.com/1/files_put/'.$type.'/'.$file.
               '?'.http_build_query($this->authParams);
        $result = $this->web->request($url, array(
            'method' => 'PUT',
            'content' => $content,
            'header' => array( 'Content-Type: application/octet-stream' ),
        ));
        $result_body = json_decode($result['body'],true);
        if (!array_key_exists('error', $result_body)) {
            return $result_body;
        } else {
            trigger_error(sprintf(self::E_APIERROR, $result_body['error']));
            return false;
        }
    }

    /**
     * delete a file
     * @param        $file
     * @param string $type
     * @return mixed
     */
    public function delete($file,$type='sandbox')
    {
        $url = 'https://api.dropbox.com/1/fileops/delete';
        $params = $this->authParams + array('root'=>$type,'path'=>$file);
        $result = $this->web->request($url, array(
             'method' => 'POST',
             'content' => http_build_query($params),
        ));
        $result_body = json_decode($result['body'], true);
        if (!array_key_exists('error', $result_body)) {
            return $result_body;
        } else {
            trigger_error(sprintf(self::E_APIERROR, $result_body['error']));
            return false;
        }

    }

    /**
     * rename a file or directory
     * @param $from
     * @param $to
     * @return mixed
     */
    public function rename($from, $to)
    {
        // TODO: Implement rename() method.
    }

    /**
     * get last modified date
     * @param $file
     * @return mixed
     */
    public function modified($file)
    {
        // TODO: Implement modified() method.
    }

    /**
     * get filesize in bytes
     * @param $file
     * @return mixed
     */
    public function size($file)
    {
        // TODO: Implement size() method.
    }

    /**
     * return whether the item is a directory
     * @param $dir
     * @return mixed
     */
    public function isDir($dir)
    {
        // TODO: Implement isDir() method.
    }

    /**
     * create new directory
     * @param $dir
     * @return mixed
     */
    public function createDir($dir)
    {
        // TODO: Implement createDir() method.
    }

    /**
     * remove a directory
     * @param $dir
     * @return mixed
     */
    public function removeDir($dir)
    {
        // TODO: Implement removeDir() method.
    }
}
