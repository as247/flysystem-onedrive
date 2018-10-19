<?php
/**
 * Created by PhpStorm.
 * User: alt
 * Date: 18-Oct-18
 * Time: 10:50 AM
 */

namespace As247\Flysystem\OneDrive;

class Cache
{
    const DIRMIME = 'application/vnd.google-apps.folder';


    protected $root;
    protected $files=[];
    protected $fullyCached=[];
    public function __construct($root='')
    {
        $this->root=$root;
    }
    public function missing($path){
        $this->update($path,false);
    }
    public function delete($path){
        $this->update($path,false);
    }
    public function deleteDir($path){
        $this->rename($path,false);
    }
    public function rename($from,$to){
        $from=$this->cleanPath($from);
        $remove=$to===false;
        $to=$this->cleanPath($to);
        foreach ($this->files as $key=>$file){
            if($remove) {
                if(strpos($key,$from)===0){
                    $this->files[$key]=false;
                }
            }else{
                $newKey = $this->str_replace_path($from, $to, $key);
                if ($newKey !== $key) {
                    $this->files[$newKey] = $file;
                    $this->files[$key] = false;
                }
            }
        }
        foreach ($this->fullyCached as $key=>$value){
            if($remove){
                if(strpos($key,$from)===0){
                    unset($this->fullyCached[$key]);
                }
            }else {
                $newKey = $this->str_replace_path($from, $to, $key);
                if ($newKey !== $key) {
                    $this->fullyCached[$newKey] = $value;
                    unset($this->fullyCached[$key]);
                }
            }
        }
    }
    public function setComplete($path){
        $this->fullyCached[$this->cleanPath($path)]=true;
    }
    public function isComplete($path){
        return !empty($this->fullyCached[$this->cleanPath($path)]);
    }
    public function set($path,$file){
        $path=$this->cleanPath($path);
        if(!isset($this->files[$path])){
            $this->files[$path]=$this->sanitize($file);
        }
        return $this;
    }
    public function update($path,$file){
        $path=$this->cleanPath($path);
        $this->files[$path]=$this->sanitize($file);
    }
    public function has($path){
        $path=$this->cleanPath($path);
        if($this->isComplete(dirname($path))){
            return true;
        }
        return array_key_exists($path,$this->files);
    }

    /**
     * @param $path
     * @return array|false
     */
    public function get($path){
        $path=$this->cleanPath($path);
        return isset($this->files[$path])?$this->files[$path]:false;
    }

    public function cleanPath($path){
        if(is_array($path)){
            $path = implode('/',$path);
        }
        if(!is_string($path)){
            $path='/';
        }
        $path = trim($path);
        if ($path==='' || $path === '.') {
            return '/';
        }
        $path=str_replace('\\','/',$path);
        $path='/'.ltrim($path,'\/');

        return $path;
    }

    /**
     * @param $directory
     * @return array
     */
    public function listContents($directory){
        $directory=$this->cleanPath($directory);
        $results=[];
        foreach ($this->files as $path => $file) {
            if(!$file){
                continue;
            }
            if (strpos($path, $directory) === 0 && $path!==$directory) {
                $results[$path] = $file;
            }
        }
        return $results;
    }
    public function sanitize($file){
        return $file;
    }
    public function showDebug(){
        $debug=[];
        foreach ($this->files as $path=>$file){
            if(!$file){
                $debug[$path]='Not exists';
            }else {
                $debug[$path] = $file->getMimeType() === static::DIRMIME ? 'dir' : 'file';
            }
        }
        var_dump($debug);
    }
    protected function str_replace_path($search,$replace,$subject){
        $pos = strpos($subject, $search);
        if ($pos === 0) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }
        return $subject;

    }
}