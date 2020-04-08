<?php
/**
 * Created by PhpStorm.
 * User: alt
 * Date: 17-Oct-18
 * Time: 8:44 AM
 */

namespace As247\Flysystem\OneDrive;

use As247\Flysystem\OneDrive\Exceptions\OneDriveException;
use League\Flysystem\Config;
use Microsoft\Graph\Graph;

use League\Flysystem\Adapter\AbstractAdapter;


class OneDriveAdapter extends AbstractAdapter
{
	protected $driver;
    public function __construct(Graph $graph, string $root = '')
    {
    	$this->driver=new Driver($graph,$root);
    }


	/**
	 * @inheritDoc
	 */
	public function write($path, $contents, Config $config)
	{
		try {
			return $this->driver->upload($path, $contents, $config);
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function writeStream($path, $resource, Config $config)
	{
		try {
			return $this->driver->upload($path, $resource, $config);
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function update($path, $contents, Config $config)
	{
		try {
			return $this->driver->upload($path, $contents, $config);
		}catch (OneDriveException $e){
return false;
}
	}

	/**
	 * @inheritDoc
	 */
	public function updateStream($path, $resource, Config $config)
	{
		try {
			return $this->driver->upload($path, $resource, $config);
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function rename($path, $newpath)
	{
		try {
			return $this->driver->rename($path, $newpath);
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function copy($path, $newpath)
	{
		try {
			return $this->driver->copy($path, $newpath);
		}catch (OneDriveException $exception){
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function delete($path)
	{
		try {
			return $this->driver->delete($path);
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function deleteDir($dirname)
	{
		try {
			return $this->driver->deleteDir($dirname);
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function createDir($dirname, Config $config)
	{
		try {
			return $this->driver->createDir($dirname, $config);
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function setVisibility($path, $visibility)
	{
		try {
			return $this->driver->setVisibility($path, $visibility);
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function has($path)
	{
		return $this->driver->has($path);
	}

	/**
	 * @inheritDoc
	 */
	public function read($path)
	{
		return $this->driver->read($path);
	}

	/**
	 * @inheritDoc
	 */
	public function readStream($path)
	{
		return $this->driver->readStream($path);
	}

	/**
	 * @inheritDoc
	 */
	public function listContents($directory = '', $recursive = false)
	{
		return $this->driver->listContents($directory,$recursive);
	}

	/**
	 * @inheritDoc
	 */
	public function getMetadata($path)
	{
		return $this->driver->getMetadata($path);
	}

	/**
	 * @inheritDoc
	 */
	public function getSize($path)
	{
		return $this->driver->getMetadata($path);
	}

	/**
	 * @inheritDoc
	 */
	public function getMimetype($path)
	{
		return $this->driver->getMetadata($path);
	}

	/**
	 * @inheritDoc
	 */
	public function getTimestamp($path)
	{
		return $this->driver->getMetadata($path);
	}

	/**
	 * @inheritDoc
	 */
	public function getVisibility($path)
	{
		return ['path'=>$path,'visibility'=>$this->driver->getVisibility($path)];
	}
}
