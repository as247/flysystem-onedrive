<?php
/**
 * Created by PhpStorm.
 * User: alt
 * Date: 11-Oct-18
 * Time: 8:24 PM
 */

namespace As247\Flysystem\OneDrive\Concerns;
use As247\Flysystem\OneDrive\Cache;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use ArrayObject;
use Microsoft\Graph\Graph;

/**
 * Trait Adapter
 * @package As247\Flysystem\OneDrive\Concerns
 * @property Graph $graph
 * @property Cache $cache
 */
trait Adapter
{
    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, Config $config)
    {
        return $this->upload($path, $contents);
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->upload($path, $resource);
    }

    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, Config $config)
    {
        return $this->upload($path, $contents);
    }

    /**
     * {@inheritdoc}
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->upload($path, $resource);
    }

    /**
     * {@inheritdoc}
     */
    public function rename($path, $newPath): bool
    {
        $endpoint = $this->prefixForEndpoint($path);
        $name=basename($newPath);
        $this->mkdir(dirname($newPath));
        $newPathParent=$this->prefixForPath(dirname($newPath));
        $body=[
            'name' => $name,
            'parentReference' => [
                'path' => $newPathParent,
            ],
        ];
        try {
            $this->graph->createRequest('PATCH', $endpoint)
                ->attachBody($body)
                ->execute();
        } catch (\Exception $e) {
            return false;
        }
        $this->cache->rename($path,$newPath);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function copy($path, $newPath): bool
    {
        $this->mkdir(dirname($newPath));
        $endpoint = $this->prefixForEndpoint($path,'copy');
        $name=basename($newPath);
        $newPathParent=$this->prefixForPath(dirname($newPath));
        $body=[
            'name' => $name,
            'parentReference' => [
                'path' => $newPathParent,
            ],
        ];


        try {
            $this->graph->createRequest('POST', $endpoint)
                ->attachBody($body)
                ->execute();
        } catch (\Exception $e) {
            return false;
        }
        //Nothing to cache, since it not response new object

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path): bool
    {
        $endpoint = $this->prefixForEndpoint($path);
        if($endpoint===$this->prefixForEndpoint('')){
            return false;
        }
        try {
            $this->graph->createRequest('DELETE', $endpoint)->execute();
        } catch (\Exception $e) {
            return false;
        }
        $this->cache->deleteDir($path);//assume that this is dir because we using path

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($dirname): bool
    {
        return $this->delete($dirname);
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($path, Config $config)
    {
        return $this->mkdir($path);
    }

    public function setVisibility($path, $visibility)
    {
        $result = ($visibility === AdapterInterface::VISIBILITY_PUBLIC) ? $this->publish($path) : $this->unPublish($path);
        if ($result) {
            return compact('path', 'visibility');
        }
        return false;
    }
}