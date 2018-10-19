<?php
/**
 * Created by PhpStorm.
 * User: alt
 * Date: 19-Oct-18
 * Time: 9:20 AM
 */

namespace As247\Flysystem\OneDrive;


class TempCache
{
    protected $cacheDir;
    function __construct($key)
    {
        $name=md5(serialize(func_get_args()));
        $this->cacheDir=sys_get_temp_dir().'/'.$name;

    }
    function get($key){
        return $this->getPayload($key)['data'] ?? null;
    }
    function put($key,$value,$expires=0){
        $path=$this->path($key);
        if($this->ensureCacheDir()) {
            file_put_contents($path, serialize(['data' => $value, 'expire' => $expires]));
        }
    }
    function ensureCacheDir(){
        if(file_exists($this->cacheDir) && is_dir($this->cacheDir)){
            return true;
        }
        @$created=mkdir($this->cacheDir, 0777, true);
        if(!$created){
            unlink($this->cacheDir);
            @$created=mkdir($this->cacheDir, 0777, true);
        }
        return $created;
    }
    /**
     * Retrieve an item and expiry time from the cache by key.
     *
     * @param  string  $key
     * @return array
     */
    protected function getPayload($key)
    {
        $path = $this->path($key);
        $payload=[];
        if(file_exists($path) && is_file($path)){
            $content=file_get_contents($path);
            if($content) {
                $payload = unserialize($content);
            }
        }
        return $payload;

    }
    protected function path($key){
        $key=md5($key);
        return $this->cacheDir.'/'.$key;
    }

}