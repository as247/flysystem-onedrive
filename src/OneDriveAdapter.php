<?php
/**
 * Created by PhpStorm.
 * User: alt
 * Date: 17-Oct-18
 * Time: 8:44 AM
 */

namespace As247\Flysystem\OneDrive;

use ArrayObject;
use As247\Flysystem\OneDrive\Concerns\Adapter;
use As247\Flysystem\OneDrive\Concerns\Read;
use Exception;
use Microsoft\Graph\Graph;

use League\Flysystem\Adapter\AbstractAdapter;
use function GuzzleHttp\Psr7\stream_for;

class OneDriveAdapter extends AbstractAdapter
{
    use Adapter, Read;

    /** @var Graph */
    protected $graph;
    const ROOT = '/me/drive/root';
    protected $publishPermission = [
        'role' => 'read',
        'scope' => 'anonymous',
        'withLink' => true
    ];
    /**
     * @var Cache
     */
    protected $cache;

    public function __construct(Graph $graph, string $prefix = '')
    {
        $this->graph = $graph;
        $this->cache = new Cache();
        $this->setPathPrefix($prefix);
    }
    public function getCache(){
        return $this->cache;
    }

    function setPathPrefix($prefix)
    {
        if ($prefix == '.') {
            $prefix = '';
        }
        $this->pathPrefix = trim($prefix, '\/');
    }

    /**
     * {@inheritdoc}
     */
    public function applyPathPrefix($path): string
    {
        $path = trim($path, '\/');
        if ($path === '.' || $path === '..') {
            $path = '';
        }
        $paths = [];
        if ($this->pathPrefix || $path) {
            $paths[] = $this->pathPrefix;
            if ($path && $path !== '.') {
                $paths[] = $path;
            }
        }
        $paths = array_filter($paths);
        $path = join('/', $paths);
        $prefixedPath = static::ROOT;
        if ($path) {
            $prefixedPath = $prefixedPath . ':/' . $path . ':';
        }
        return $prefixedPath;

    }

    public function removePathPrefix($path)
    {
        $path = substr($path, strlen(static::ROOT));
        $path = trim($path, ':\/');
        if ($this->pathPrefix) {
            return parent::removePathPrefix($path);
        }
        return $path;
    }

    public function getGraph(): Graph
    {
        return $this->graph;
    }

    /**
     * @param string $path
     * @param resource|string $contents
     *
     * @return array|false file metadata
     */
    protected function upload(string $path, $contents)
    {
        $endpoint = $this->prefixForEndpoint($path,'content');

        try {
            if (is_resource($contents)) {
                $stats = fstat($contents);
                if (empty($stats['size'])) {
                    throw new Exception('Empty stream');
                }
            }
            $stream = stream_for($contents);

            $response = $this->graph->createRequest('PUT', $endpoint)
                ->attachBody($stream)
                ->execute();
        } catch (Exception $e) {
            return false;
        }
        $this->cache->update($path,$response->getBody());
        return $this->normalizeResponse($response->getBody(), $path);
    }

    function mkdir($path, $recurse = true)
    {
        $parent = dirname($path);
        if (!$this->has($parent) && !$recurse) {//no parent
            return false;
        }
        if ($this->isFile($parent)) {//parent path is file...
            return false;
        }
        if (!$this->has($parent)) {
            if (!$this->mkdir($parent)) {
                return false;
            }
        }
        $name = basename($path);
        $endpoint = $this->prefixForEndpoint($parent, 'children');
        try {
            $response = $this->graph->createRequest('POST', $endpoint)
                ->attachBody([
                    'name' => $name,
                    'folder' => new ArrayObject(),
                ])->execute();
        } catch (Exception $e) {
            return false;
        }
        $this->cache->update($path,$response->getBody());
        return $this->normalizeResponse($response->getBody(), $path);
    }
    function publish($path){
        $endpoint=$this->prefixForEndpoint($path,'createLink');
        $body=['type'=>'view','scope'=>'anonymous'];
        try{
            $response = $this->graph->createRequest('POST', $endpoint)
                ->attachBody($body)->execute();
        }catch (Exception $e){
            return false;
        }
        return $response->getBody();
    }
    function unPublish($path){
        $permissions=$this->getPermissions($path);
        $idToRemove='';
        foreach ($permissions as $permission){
            if(in_array($this->publishPermission['role'],$permission['roles'])
                && $permission['link']['scope']==$this->publishPermission['scope']){
                $idToRemove=$permission['id'];
                break;
            }
        }
        if(!$idToRemove){
            return false;
        }
        $endpoint=$this->prefixForEndpoint($path,'permissions/'.$idToRemove);
        try{
            $this->graph->createRequest('DELETE', $endpoint)->execute();
        }catch (Exception $e){
            return false;
        }
        return true;
    }

    protected function normalizeResponse(array $response, string $path): array
    {
        $path = trim($this->removePathPrefix($path), '/');

        return [
            'path' => $path,
            'timestamp' => strtotime($response['lastModifiedDateTime']),
            'size' => $response['size'],
            'bytes' => $response['size'],
            'type' => isset($response['file']) ? 'file' : 'dir',
            'mimetype' => isset($response['file']) ? $response['file']['mimeType'] : null,
            'link' => isset($response['webUrl']) ? $response['webUrl'] : null,
            'downloadUrl' => isset($response['@microsoft.graph.downloadUrl']) ? $response['@microsoft.graph.downloadUrl'] : null,
        ];
    }

    protected function prefixForEndpoint($path, $action = '')
    {
        $path = $this->applyPathPrefix($path);
        if ($action) {
            return $path . '/' . $action;
        }
        return rtrim($path);
    }

    protected function prefixForPath($path)
    {
        $path = $this->applyPathPrefix($path);
        if ($path === static::ROOT) {
            return $path . ':';
        }
        return rtrim($path, ':');
    }
}
