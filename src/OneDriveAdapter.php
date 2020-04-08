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
use Microsoft\Graph\Exception\GraphException;
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
	 * @throws GraphException
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
	 * @param string $path
	 * @param resource $resource
	 * @param Config $config
	 * @return array|bool|false
	 * @throws GraphException
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
	 * @param string $path
	 * @param string $contents
	 * @param Config $config
	 * @return array|bool|false
	 * @throws GraphException
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
	 * @param string $path
	 * @param resource $resource
	 * @param Config $config
	 * @return array|bool|false
	 * @throws GraphException
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
	 * @param string $path
	 * @param string $newpath
	 * @return bool
	 * @throws GraphException
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
	 * @param string $path
	 * @param string $newpath
	 * @return bool
	 * @throws GraphException
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
	 * @param string $path
	 * @return bool
	 * @throws GraphException
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
	 * @param string $dirname
	 * @return bool
	 * @throws GraphException
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
	 * @param string $dirname
	 * @param Config $config
	 * @return array|bool|false
	 * @throws GraphException
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
	 * @param string $path
	 * @param string $visibility
	 * @return array|bool|false
	 * @throws GraphException
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
	 * @param string $path
	 * @return bool
	 * @throws GraphException
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
	 * @throws GraphException
	 */
	public function read($path)
	{
		try {
			return $this->driver->read($path);
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @param string $path
	 * @return array|bool|false
	 * @throws GraphException
	 */
	public function readStream($path)
	{
		try {
			return $this->driver->readStream($path);
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @param string $directory
	 * @param bool $recursive
	 * @return array
	 * @throws GraphException
	 */
	public function listContents($directory = '', $recursive = false)
	{
		try {
			return $this->driver->listContents($directory, $recursive);
		}catch (OneDriveException $e){
			return [];
		}
	}

	/**
	 * @param string $path
	 * @return array|bool|false
	 * @throws GraphException
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
	 * @throws GraphException
	 */
	public function getSize($path){
		try {
			return $this->driver->getMetadata($path);
		}catch (OneDriveException $e){
			return false;
		}

	}

	/**
	 * @param string $path
	 * @return array|bool|false
	 * @throws GraphException
	 */
	public function getMimetype($path)
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
	 * @throws GraphException
	 */
	public function getTimestamp($path)
	{
		try {
			return $this->driver->getMetadata($path);
		}catch (OneDriveException $e){
			return false;
		}
	}

	/**
	 * @param string $path
	 * @return array|false
	 * @throws GraphException
	 */
	public function getVisibility($path)
	{
		try {
			return ['path' => $path, 'visibility' => $this->driver->getVisibility($path)];
		}catch (OneDriveException $e){
			return false;
		}
	}
}
