<?php
/**
 * Created by PhpStorm.
 * User: alt
 * Date: 11-Oct-18
 * Time: 8:43 PM
 */

namespace As247\Flysystem\OneDrive\Concerns;


use As247\Flysystem\OneDrive\Cache;
use GuzzleHttp\Client;
use League\Flysystem\AdapterInterface;
use Microsoft\Graph\Graph;
/**
 * Trait Read
 * @property Graph $graph
 * @property Cache $cache
 */
trait Read
{
    public function isDirectory($path){
        $meta=$this->getMetadata($path);
        return isset($meta['type'])&& $meta['type']==='dir';
    }
    public function isFile($path){
        $meta=$this->getMetadata($path);
        return isset($meta['type'])&& $meta['type']==='file';
    }
    /**
     * {@inheritdoc}
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function has($path)
    {
        return (bool)$this->getMetadata($path);
    }


    /**
     * {@inheritdoc}
     */
    public function read($path)
    {
        if (! $object = $this->readStream($path)) {
            return false;
        }

        $object['contents'] = (string)stream_get_contents($object['stream']);
        fclose($object['stream']);
        unset($object['stream']);

        return $object;
    }

    public function readStream($path){
        $downloadUrl=$this->getTemporaryUrl($path);
        if($downloadUrl){
            $client=new Client();
            $stream=null;
            try {
                $response = $client->get($downloadUrl, ['stream' => true]);
                $stream = $response->getBody()->detach();

            }catch (\Exception $exception){

            }
            if($stream!==null) {
                return compact('stream');
            }
        }
        return false;

    }


    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false): array
    {
        $endpoint = $this->applyPathPrefix($directory).'/children';
        try {
            $results = [];
            $response = $this->graph->createRequest('GET', $endpoint)->execute();
            $items = $response->getBody()['value'];

            if (! count($items)) {
                return [];
            }

            foreach ($items as &$item) {
                $results[] = $this->normalizeResponse($item, $this->applyPathPrefix($directory.'/'.$item['name']));

                if ($recursive && isset($item['folder'])) {
                    $results = array_merge($results, $this->listContents($directory.'/'.$item['name'], true));
                }
            }
        } catch (\Exception $e) {
            return [];
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        if($this->cache->has($path)){
            if($response=$this->cache->get($path)) {
                return $this->normalizeResponse($response, $path);
            }
            return false;
        }
        $endpoint = $this->prefixForEndpoint($path);
        try {
            $response = $this->graph->createRequest('GET', $endpoint)->execute();
        } catch (\Exception $e) {
            $this->cache->missing($path);
            return false;
        }
        //echo 'get new: '.$path.PHP_EOL;
        $this->cache->update($path,$response->getBody());
        return $this->normalizeResponse($this->cache->get($path), $path);
    }

    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    public function getVisibility($path){
        return ['visibility'=>$this->getRawVisibility($path),'path'=>$path];
    }
    protected function getPermissions($path){
        $endpoint=$this->prefixForEndpoint($path,'permissions');
        try {
            $response = $this->graph->createRequest('GET', $endpoint)->execute();
        } catch (\Exception $e) {
            return false;
        }

        $result=$response->getBody();
        $permissions=[];
        if(isset($result['value'])){
            $permissions=$result['value'];
        }
        return $permissions;
    }
    protected function getRawVisibility($path){
        $permissions=$this->getPermissions($path);
        $visibility = AdapterInterface::VISIBILITY_PRIVATE;
        foreach ($permissions as $permission) {
            if(!isset($permission['link']['scope']) || !isset($permission['roles'])){
                continue;
            }
            if(in_array($this->publishPermission['role'],$permission['roles'])
                && $permission['link']['scope']==$this->publishPermission['scope']){
                $visibility = AdapterInterface::VISIBILITY_PUBLIC;
                break;
            }
        }
        return $visibility;
    }
    public function getUrl($path){
        if($meta=$this->getMetadata($path)) {
            return $meta['link'];
        }
    }
    public function getTemporaryUrl($path,$expire=3600,$options=[]){
        $meta=$this->getMetadata($path);
        if(!empty($meta['downloadUrl'])){
            return $meta['downloadUrl'];
        }
    }
}