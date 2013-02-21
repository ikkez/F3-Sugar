<?php

/**
    DropBox filesystem adapter

    You need a valid app-key and app-secret in order to use this
    Get them right here: https://www.dropbox.com/developers/apps
    ================================================

    The contents of this file are subject to the terms of the GNU General
    Public License Version 3.0. You may not use this file except in
    compliance with the license. Any of the license terms and conditions
    can be waived if you get permission from the copyright holder.

        Copyright (c) 2013 by ikkez
        Christian Knuth <ikkez0n3@gmail.com>
        https://github.com/ikkez/F3-Sugar/

        @version 0.8.1
        @date 15.02.2013
 **/

namespace FAL;

class Dropbox implements FileSystem {

    protected
        $appKey,
        $appSecret,
        $authToken,
        $authSecret,
        $reqParams,
        $authParams;

    /** @var \Base */
    protected $f3;

    /** @var \Web */
    protected $web;

    const
        E_APIERROR = 'Dropbox API Error: %s',
        E_AUTHERROR = 'OAuth failed: %s',
        E_METHODNOTSUPPORTED = 'METHOD %s not supported';

    public function __construct($appKey,$appSecret) {
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
        $this->f3 = \Base::instance();
        $this->web = \Web::instance();
        $this->web->engine('curl');
        $this->authToken = $this->f3->get('SESSION.dropbox.authToken');
        $this->authSecret = $this->f3->get('SESSION.dropbox.authSecret');
        $this->reqParams = array(
            'oauth_consumer_key' => $this->appKey,
            'oauth_version' => '1.0',
            'oauth_signature' => $this->appSecret.'&',
            'oauth_signature_method' => 'PLAINTEXT',
            'oauth_timestamp' => strftime("%a, %d %b %Y %H:%M:%S %Z",time()),
        );
        $this->authParams = $this->reqParams + array('oauth_token' => $this->authToken);
        $this->authParams['oauth_signature'] .= $this->authSecret;
    }

    /**
     * set auth tokens
     * @param $token
     * @param $secret
     */
    public function setAuthToken($token, $secret) {
        $this->authToken = $token;
        $this->authSecret = $secret;
        $this->f3->set('SESSION.dropbox.authToken', $this->authToken);
        $this->f3->set('SESSION.dropbox.authSecret', $this->authSecret);
    }

    /**
     * perform external authorisation, return access token
     * @param null $callback_url
     * @return array|bool
     */
    public function login($callback_url = NULL) {
        if (!$this->f3->exists('GET.oauth_token')) {
            $tokens = $this->requestToken();
            $this->setAuthToken($tokens['oauth_token'], $tokens['oauth_token_secret']);
            if(is_null($callback_url))
                $callback_url = $this->f3->get('SCHEME').'://'.$this->f3->get('HOST').
                    $this->f3->get('URI');
            $this->authorize($callback_url);
        } else {
            return $this->accessToken();
        }
    }

    /**
     * AUTH Step 1: request a token for authorisation process
     */
    public function requestToken(){
        $url = 'https://api.dropbox.com/1/oauth/request_token';
        $params = $this->reqParams;
        $result = $this->web->request($url,array(
            'method'=>'POST',
            'content'=> http_build_query($params)
        ));
        parse_str($result['body'], $output);
        if (array_key_exists('oauth_token_secret',$output) &&
            array_key_exists('oauth_token', $output))
        {
            return $output;
        } else {
            $result = json_decode($result['body'], true);
            trigger_error(sprintf(self::E_AUTHERROR,$result['error']));
        }
    }

    /**
     * AUTH Step 2: reroute to auth page
     * @param null $callback_url
     */
    public function authorize($callback_url = NULL){
        $url = 'https://www.dropbox.com/1/oauth/authorize';
        $params = array(
            'oauth_token' => $this->authToken,
            'locale ' => $this->f3->get('LANGUAGE'),
        );
        if($callback_url) $params['oauth_callback'] = $callback_url;
        $this->f3->reroute($url.'?'.http_build_query($params));
    }

    /**
     * AUTH Step 3: request access token, used to sign all resource requests
     * @return bool|array
     */
    public function accessToken(){
        $url = 'https://api.dropbox.com/1/oauth/access_token';
        $result = $this->doOAuthCall($url,'POST');
        parse_str($result['body'], $output);
        if (!count(array_diff(array('oauth_token','oauth_token_secret','uid'),
            array_keys($output))))
        {
            $this->setAuthToken($output['oauth_token'], $output['oauth_token_secret']);
            return $output;
        } else {
            $result = json_decode($result['body'], true);
            trigger_error(sprintf(self::E_AUTHERROR, $result['error']));
            return false;
        }
    }

    /**
     * perform a signed oauth request
     * @param string $url      request url
     * @param string $method   method type
     * @param array  $params   additional params
     * @param null   $type     storage type [sandbox|dropbox]
     * @param null   $file     full file pathname
     * @param null   $content  file content
     * @return bool
     */
    protected function doOAuthCall($url, $method, $params=null,
                                   $type=NULL, $file=NULL, $content=NULL) {
        if(is_null($params)) $params = array();
        $method = strtoupper($method);
        $options = array('method' => $method);
        if ($method == 'GET') {
            if($file)
                $url .= $type.'/'.$file;
            $url .= '?'.http_build_query($this->authParams + $params);
        }
        elseif ($method == 'POST') {
            $params = $this->authParams + $params + array('root' => $type);
            $options['content'] = http_build_query($params);
        }
        elseif ($method == 'PUT') {
            $url .= $type.'/'.$file.'?'.http_build_query($this->authParams + $params);
            $options['content'] = $content;
            $options['header'] = array('Content-Type: application/octet-stream');
        }
        else {
            trigger_error(sprintf(self::E_METHODNOTSUPPORTED,$method));
            return false;
        }
        return $this->web->request($url, $options);
    }

    /**
     * gather user account information
     * @return bool|mixed
     */
    public function getAccountInfo() {
        $url = 'https://api.dropbox.com/1/account/info';
        $result = $this->doOAuthCall($url,'POST');
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
        $url = 'https://api-content.dropbox.com/1/files/';
        $params = array();
        if ($rev) $params['rev'] = $rev;
        $result = $this->doOAuthCall($url,'GET',$params, $type, $file);
        // if file not found, response is json, otherwise just file contents
        if(!in_array('HTTP/1.1 404 Not Found', $result['headers']))
            return $result['body'];
        else {
            $result_body = json_decode($result['body'], true);
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
        $result = $this->metadata($file, true, false, $hidden, $rev, $type);
        return $result['contents'];
    }

    /**
     * get file information
     * @param        $file
     * @param null   $rev
     * @param string $type
     * @return bool|mixed
     */
    public function fileInfo($file,$rev = NUll, $type = 'sandbox')
    {
        return $this->metadata($file,false,false,true,$rev,$type);
    }

    /**
     * perform meta request
     * @param        $file          full file pathname
     * @param bool   $list          include file list, if $file is a dir
     * @param bool   $existCheck    return bool instead of json or error
     * @param bool   $hidden        include deleted files
     * @param null   $rev           select file version
     * @param string $type          storage type [sandbox|dropbox]
     * @return bool|mixed
     */
    protected function metadata($file,$list=true,$existCheck=false,
                                $hidden=false,$rev=NULL, $type='sandbox')
    {
        $url = 'https://api.dropbox.com/1/metadata/';
        $params = array();
        $params['list'] = $list;
        if ($rev) $params['rev'] = $rev;
        if ($list) $params['include_deleted'] = 'false';
        $result = $this->doOAuthCall($url, 'GET', $params, $type,$file);
        $result_body = json_decode($result['body'], true);
        if (!array_key_exists('error', $result_body)) {
            if($existCheck) {
                if(array_key_exists('is_deleted',$result_body) && $result_body['is_deleted'])
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
        $url = 'https://api-content.dropbox.com/1/files_put/';
        $result = $this->doOAuthCall($url,'PUT',null,$type,$file,$content);
        $result_body = json_decode($result['body'],true);
        if (!array_key_exists('error', $result_body)) {
            return $result_body;
        } else {
            trigger_error(sprintf(self::E_APIERROR, $result_body['error']));
            return false;
        }
    }

    /**
     * delete a file or dir
     * @param        $file
     * @param string $type
     * @return mixed
     */
    public function delete($file,$type='sandbox')
    {
        $url = 'https://api.dropbox.com/1/fileops/delete';
        $result = $this->doOAuthCall($url,'POST',array('path' => $file),$type);
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
     * @param        $from
     * @param        $to
     * @param string $type
     * @return mixed
     */
    public function move($from, $to, $type='sandbox')
    {
        $url = 'https://api.dropbox.com/1/fileops/move';
        $params = array('from_path' => $from,'to_path'=>$to);
        $result = $this->doOAuthCall($url, 'POST', $params, $type);
        $result_body = json_decode($result['body'], true);
        if (!array_key_exists('error', $result_body)) {
            return $result_body;
        } else {
            trigger_error(sprintf(self::E_APIERROR, $result_body['error']));
            return false;
        }
    }

    /**
     * get last modified date
     * @param        $file
     * @param null   $rev
     * @param string $type
     * @return mixed
     */
    public function modified($file, $rev = NULL, $type = 'sandbox')
    {
        $result = $this->metadata($file, false, false, true, $rev, $type);
        return strtotime($result['modified']);
    }

    /**
     * get filesize in bytes
     * @param        $file
     * @param null   $rev
     * @param string $type
     * @return mixed
     */
    public function size($file, $rev = NULL, $type = 'sandbox')
    {
        $result = $this->metadata($file, false, false, true, $rev, $type);
        return strtotime($result['bytes']);
    }

    /**
     * return whether the item is a directory
     * @param        $dir
     * @param null   $rev
     * @param string $type
     * @return mixed
     */
    public function isDir($dir, $rev = NULL, $type = 'sandbox')
    {
        $result = $this->metadata($dir, false, true, false, $rev, $type);
        return (bool)$result;
    }

    /**
     * create new directory
     * @param        $dir
     * @param string $type
     * @return mixed
     */
    public function createDir($dir,$type='sandbox')
    {
        $url = 'https://api.dropbox.com/1/fileops/create_folder';
        $result = $this->doOAuthCall($url, 'POST', array('path'=>$dir), $type);
        $result_body = json_decode($result['body'], true);
        if (!array_key_exists('error', $result_body)) {
            return $result_body;
        } else {
            trigger_error(sprintf(self::E_APIERROR, $result_body['error']));
            return false;
        }
    }

    /**
     * remove a directory
     * @param        $dir
     * @param string $type
     * @return mixed
     */
    public function removeDir($dir,$type='sandbox')
    {
        $this->delete($dir,$type);
    }
}
