<?php


/**
    FileUnit - a plugin with file-system operations for the PHP Fat-Free Framework

    The contents of this file are subject to the terms of the GNU General
    Public License Version 3.0. You may not use this file except in
    compliance with the license. Any of the license terms and conditions
    can be waived if you get permission from the copyright holder.

    Copyright (c) 2012 by ikkez
    Christian Knuth <mail@ikkez.de>

    @version 1.1.2
 **/


class FileUnit extends Base {

    const
        TEXT_NotDir = 'source path is no directory: %s',
        TEXT_CantRecursiveIntoDir = 'Directory %s contained a directory we can not recurse into.',
        TEXT_DeleteFileFailed = 'Deleting file failed:  %s',
        TEXT_RemoveDirFailed = 'Removing Directory failed: %s';

    /**
     * copy a whole directory and its content, recursive
     *
     * @return array|bool $array copy log, or false
     * @param $source string
     * @param $target string
     */
    static function copyDir($source, $target) {
        $log=array();
        if(!is_dir($source)) {
            trigger_error(sprintf(self::TEXT_NotDir,$source));
            return false;
        }
        if(!is_dir($target)) self::mkdir($target);
        $dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
        foreach($dir as $node_path => $node)
            if($node->getFilename()!='..' && $node->getFilename()!='.'){
                $t_path=substr_replace($node_path, $target, 0, strlen($source));
                if($node->isDir()){
                    if (!is_dir($t_path)) self::mkdir($t_path);
                    $log['dir'][] = substr($t_path,strlen($target));
                } else
                if($node->isFile()){
                    copy($node_path,$t_path);
                    $log['file'][] = substr($t_path,strlen($target));
                } else
                    $log['skipped'][] = substr($node_path,strlen($source));
            }
        return $log;
    }

    /**
     * get deep structured array of all files within a directory, recursive
     *
     * @param $sdir string $dir path to scan
     * @param bool $flat return a flar array of filepaths
     * @return array|bool file tree array
     */
    static public function listDir($sdir,$flat=false){
        if(!is_dir($sdir)){
            trigger_error(sprintf(self::TEXT_NotDir,$sdir));
            return false;
        }
        $list = array();
        $dir = (!$flat) ?
            new DirectoryIterator($sdir) :  new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sdir),
                RecursiveIteratorIterator::CHILD_FIRST
            );
        try {
            if(!$flat){
                foreach($dir as $node_path=>$node)
                    if($node->isDir() && !$node->isDot())
                        $list[$node->getFilename()] = self::listDir($node->getPathname(),false);
                    else if($node->isFile())
                        $list[$node->getFilename()] = $node->getFilename();
            } else {
                foreach( $dir as $node_path => $node ) {
                    $fileName = $node->getFilename();
                    if ($fileName != '.' && $fileName != '..')
                        $list[] = substr($node_path,strlen($sdir));
                }
            }
        }
        catch (UnexpectedValueException $e) {
            trigger_error(sprintf(self::TEXT_NotDir,$sdir));
        }
        return $list;
    }


    /**
     * delete all files and folders within target path
     * 
     * @param $dir string path to clean
     * @return bool
     */
    static function cleanDir($dir) {
        if(!is_dir($dir)) {
            trigger_error(sprintf(self::TEXT_NotDir,$dir));
            return false;
        }
        $dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($dir as $node)
            if($node->getFilename() != '..' && $node->getFilename() != '.' )
                if($node->isDir()) {
                	if(!@rmdir($node->__toString()))
                		trigger_error(sprintf(self::TEXT_RemoveDirFailed,$node->__toString()));                		
                } elseif(!@unlink($node->__toString())) 
                	trigger_error(sprintf(self::TEXT_DeleteFileFailed,$node->__toString()));
        return true;
    }


    /**
     * copy uploaded file to target destination
     *
     * @param $input_name   name of input form field
     * @param $target_path  target directory
     * @param bool $slug    rename filename to URL & filesystem-friendly version
     * @return array|bool   file info on success, otherwise false
     */
    static function saveUploaded($input_name,$target_path,$slug = FALSE) {
        $file = $_FILES[$input_name];
        if($file['size'] == 0 && $file['error'] == 0)
            $file['error'] = 8;
        if ($file["error"] > 0) {
            $upload_errors = array(
                "No errors.",
                "Larger than upload_max_filesize.",
                "Larger than form MAX_FILE_SIZE.",
                "Partial upload.",
                "No file.",
                "No temporary directory.",
                "Can't write to disk.",
                "File upload stopped by extension.",
                "File is empty."
            );
            trigger_error('Error: '.$upload_errors[$file["error"]]);
            return false;
        } else {
            $fileBase = basename($file['name']);
            if($slug)
                if(strstr($fileBase,'.')){
                    list($fileName,$fileExt) = explode('.',$fileBase);
                    $copyFile = Web::slug($fileName).'.'.$fileExt;
                } else
                    $copyFile = Web::slug($fileBase);
            else $copyFile = $fileBase;
            if(@move_uploaded_file($file['tmp_name'], $target_path.$copyFile)) {
                $fileInfo = pathinfo($target_path.$copyFile);
                $fileInfo['type'] = $file["type"];
                $fileInfo['size'] = ceil(filesize($target_path.$copyFile)/1024);
                return $fileInfo;
            } else return false;
        }
    }


}
