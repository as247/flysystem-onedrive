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
	 * @param string $path
	 * @param string $contents
	 * @param Config $config
	 * @return array|bool|false
	 */
	public function write($path, $contents, Config $config=null)
	{
		try {
			$this->driver->write($path, $contents, $config);
			return $this->getMetadata($path);
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @param string $path
	 * @param resource $resource
	 * @param Config $config
	 * @return array|bool|false
	 */
	public function writeStream($path, $resource, Config $config)
	{
		try {
			$this->driver->writeStream($path, $resource, $config);
			return $this->getMetadata($path);
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @param string $path
	 * @param string $contents
	 * @param Config $config
	 * @return array|bool|false
	 */
	public function update($path, $contents, Config $config)
	{
		return $this->write($path,$contents,$config);
	}

	/**
	 * @param string $path
	 * @param resource $resource
	 * @param Config $config
	 * @return array|bool|false
	 */
	public function updateStream($path, $resource, Config $config)
	{
		return $this->writeStream($path,$resource,$config);
	}

	/**
	 * @param string $path
	 * @param string $newpath
	 * @return bool
	 */
	public function rename($path, $newpath)
	{
		try {
			$this->driver->move($path, $newpath);
			return true;
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @param string $path
	 * @param string $newpath
	 * @return bool
	 */
	public function copy($path, $newpath)
	{
		try {
			$this->driver->copy($path, $newpath);
			return true;
		}catch (OneDriveException $exception){
			return false;
		}
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	public function delete($path)
	{
		try {
			$this->driver->delete($path);
			return true;
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @param string $dirname
	 * @return bool
	 */
	public function deleteDir($dirname)
	{
		try {
			$this->driver->deleteDirectory($dirname);
			return true;
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @param string $dirname
	 * @param Config $config
	 * @return array|bool|false
	 */
	public function createDir($dirname, Config $config)
	{
		try {
			$this->driver->createDirectory($dirname, $config);
			return true;
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @param string $path
	 * @param string $visibility
	 * @return array|bool|false
	 */
	public function setVisibility($path, $visibility)
	{
		try {
			$this->driver->setVisibility($path, $visibility);
			return true;
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	public function has($path)
	{
		try {
			return $this->driver->has($path);
		}catch (OneDriveException $e){
			return false;
		}

	}

	/**
	 * @param string $path
	 * @return array|bool|false
	 */
	public function read($path)
	{
		try {
			return ['contents'=>$this->driver->read($path)];
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @param string $path
	 * @return array|bool|false
	 */
	public function readStream($path)
	{
		try {
			return ['stream'=>$this->driver->readStream($path)];
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @param string $directory
	 * @param bool $recursive
	 * @return array
	 */
	public function listContents($directory = '', $recursive = false)
	{
		try {
			return iterator_to_array($this->driver->listContents($directory, $recursive),false);
		}catch (OneDriveException $e){
			return [];
		}
	}


	/**
	 * @param string $path
	 * @return array|bool|false
	 */
	public function getMetadata($path)
	{
		try {
			return $this->driver->getMetadata($path);
		} catch (OneDriveException $e) {
			return false;
		}
	}

	/**
	 * @param string $path
	 * @return array|bool|false
	 */
	public function getSize($path){
		return $this->getMetadata($path);
	}

	/**
	 * @param string $path
	 * @return array|bool|false
	 */
	public function getMimetype($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * @param string $path
	 * @return array|bool|false
	 */
	public function getTimestamp($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * @param string $path
	 * @return array|false
	 */
	public function getVisibility($path)
	{
		try {
			return $this->driver->getMetadata($path,true);
		} catch (OneDriveException $e) {
			return false;
		}
	}
}
