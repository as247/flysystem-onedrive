<?php


namespace As247\Flysystem\OneDrive;

use As247\Flysystem\OneDrive\Exceptions\OneDriveException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use function GuzzleHttp\Psr7\stream_for;
use ArrayObject;

class Driver
{
	/** @var Graph */
	protected $graph;
	const ROOT = '/me/drive/root';
	protected $publishPermission = [
		'role' => 'read',
		'scope' => 'anonymous',
		'withLink' => true
	];
	protected $rootPath;
	/**
	 * @var Cache
	 */
	protected $cache;
	public function __construct(Graph $graph,$root)
	{
		$this->graph = $graph;
		$this->rootPath=$root;
		$this->cache = new Cache();
	}

	/**
	 * @param string $path
	 * @param $contents
	 * @param Config $config
	 * @return array
	 * @throws GraphException
	 * @throws OneDriveException
	 */
	public function upload(string $path, $contents, Config $config)
	{
		$endpoint = $this->prefixForEndpoint($path,'content');

		if (is_resource($contents)) {
			$stats = fstat($contents);
			if (empty($stats['size'])) {
				throw new OneDriveException('Empty stream');
			}
		}
		$stream = stream_for($contents);

		$response = $this->graph->createRequest('PUT', $endpoint)
			->attachBody($stream)
			->execute();

		$this->cache->update($path,$response->getBody());
		return $this->normalizeResponse($response->getBody(), $path);
	}

	/**
	 * @param $path
	 * @param $newPath
	 * @return bool
	 * @throws GraphException
	 * @throws OneDriveException
	 */
	public function rename($path, $newPath)
	{
		$endpoint = $this->prefixForEndpoint($path);
		$name=basename($newPath);
		$this->ensureDirectory(dirname($newPath));
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
		} catch (ClientException $e) {
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}
		$this->cache->rename($path,$newPath);
		return true;
	}

	/**
	 * @param $path
	 * @param $newPath
	 * @return bool
	 * @throws GraphException
	 * @throws OneDriveException
	 */
	public function copy($path, $newPath)
	{
		$this->ensureDirectory(dirname($newPath));
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
		} catch (ClientException $e) {
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}
		//Nothing to cache, since it not response new object

		return true;
	}

	/**
	 * @param $path
	 * @return bool
	 * @throws GraphException
	 * @throws OneDriveException
	 */
	public function delete($path)
	{
		$endpoint = $this->prefixForEndpoint($path);
		if($endpoint===$this->prefixForEndpoint('')){
			throw new OneDriveException("Root directory cannot be deleted");
		}
		try {
			$this->graph->createRequest('DELETE', $endpoint)->execute();
		} catch (ClientException $e) {
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}
		$this->cache->deleteDir($path);//assume that this is dir because we using path

		return true;
	}

	/**
	 * @param $path
	 * @return array|bool
	 * @throws GraphException
	 * @throws OneDriveException
	 */
	protected function ensureDirectory($path)
	{
		$path=$this->cleanPath($path);
		if($this->isDirectory($path)){
			return true;
		}
		$parent = dirname($path);

		if ($this->isFile($parent)) {//parent path is file...
			throw new OneDriveException("Could not create directory $path, $parent is a file");
		}
		if (!$this->has($parent)) {
			$this->ensureDirectory($parent);
		}
		$name = basename($path);
		$endpoint = $this->prefixForEndpoint($parent, 'children');
		try {
			$response = $this->graph->createRequest('POST', $endpoint)
				->attachBody([
					'name' => $name,
					'folder' => new ArrayObject(),
				])->execute();
		}catch (ClientException $e){
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}
		$this->cache->update($path,$response->getBody());
		return $this->normalizeResponse($response->getBody(), $path);
	}

	/**
	 * @param $dirname
	 * @return bool
	 * @throws OneDriveException
	 * @throws GraphException
	 */
	public function deleteDir($dirname)
	{
		return $this->delete($dirname);
	}

	/**
	 * @param $path
	 * @param Config $config
	 * @return array|bool
	 * @throws OneDriveException
	 * @throws GraphException
	 */
	public function createDir($path, Config $config)
	{
		return $this->ensureDirectory($path);
	}

	/**
	 * @param $path
	 * @param $visibility
	 * @return array|bool
	 * @throws GraphException
	 * @throws OneDriveException
	 */
	public function setVisibility($path, $visibility)
	{
		$result = ($visibility === AdapterInterface::VISIBILITY_PUBLIC) ? $this->publish($path) : $this->unPublish($path);
		if ($result) {
			return compact('path', 'visibility');
		}
		return false;
	}

	/**
	 * @param $path
	 * @return mixed
	 * @throws GraphException
	 * @throws OneDriveException
	 */
	function publish($path){
		$endpoint=$this->prefixForEndpoint($path,'createLink');
		$body=['type'=>'view','scope'=>'anonymous'];
		try{
			$response = $this->graph->createRequest('POST', $endpoint)
				->attachBody($body)->execute();
		}catch (ClientException $e){
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}
		return $response->getBody();
	}

	/**
	 * @param $path
	 * @return bool
	 * @throws GraphException
	 * @throws OneDriveException
	 */
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
		}catch (ClientException $e){
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}
		return true;
	}

	protected function normalizeResponse(array $response, string $path): array
	{
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


	protected function cleanPath($path){
		$path = trim($path, '\\/');
		if ($path === '.' || $path === '..') {
			$path = '';
		}
		$path=str_replace('\\','/',$path);

		$paths=array_filter(explode('/',$path),function($v){
			if(strlen($v)===0 || $v=='.' || $v=='..' || $v=='/'){
				return false;
			}
			return true;
		});
		$path = '/'.join('/', $paths);
		return $path;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function applyRootPrefix($path)
	{
		$path=$this->cleanPath($path);
		$path=$this->rootPath.'/'.ltrim($path,'\\/');
		$path=trim($path,'\\/');
		$prefixedPath = static::ROOT;
		if ($path) {
			$prefixedPath = $prefixedPath . ':/' . $path . ':';
		}
		return $prefixedPath;

	}

	protected function prefixForEndpoint($path, $action = '')
	{
		$path = $this->applyRootPrefix($path);
		if ($action) {
			return $path . '/' . $action;
		}
		return rtrim($path);
	}

	protected function prefixForPath($path)
	{
		$path = $this->applyRootPrefix($path);
		if ($path === static::ROOT) {
			return $path . ':';
		}
		return rtrim($path, ':');
	}

	/**
	 * @param $path
	 * @return array|bool
	 * @throws GraphException
	 * @throws OneDriveException
	 */
	protected function getPermissions($path){
		$endpoint=$this->prefixForEndpoint($path,'permissions');
		try {
			$response = $this->graph->createRequest('GET', $endpoint)->execute();
		} catch (ClientException $e) {
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}

		$result=$response->getBody();
		$permissions=[];
		if(isset($result['value'])){
			$permissions=$result['value'];
		}
		return $permissions;
	}

	/**
	 * @param $path
	 * @return string
	 * @throws GraphException
	 * @throws OneDriveException
	 */
	public function getVisibility($path){
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

	/**
	 * @param $path
	 * @return mixed
	 * @throws OneDriveException
	 * @throws GraphException
	 */
	public function getUrl($path){
		if($meta=$this->getMetadata($path)) {
			return $meta['link'];
		}
		return null;
	}

	/**
	 * @param $path
	 * @param int $expire
	 * @param array $options
	 * @return mixed
	 * @throws OneDriveException
	 * @throws GraphException
	 */
	public function getTemporaryUrl($path,$expire=3600,$options=[]){
		$meta=$this->getMetadata($path);
		if(!empty($meta['downloadUrl'])){
			return $meta['downloadUrl'];
		}
		return null;
	}

	/**
	 * @param $path
	 * @return array|bool
	 * @throws OneDriveException
	 * @throws GraphException
	 */
	public function getMetadata($path)
	{
		$path=$this->cleanPath($path);

		if($this->cache->has($path)){
			if($response=$this->cache->get($path)) {
				return $this->normalizeResponse($response, $path);
			}
			return false;
		}
		$endpoint = $this->prefixForEndpoint($path);
		try {
			$response = $this->graph->createRequest('GET', $endpoint)->execute();
		} catch (ClientException $e) {
			if($path==='/'){
				return ['type'=>'dir','path'=>'/'];
			}
			if($e->getResponse()->getStatusCode()===404){
				$this->cache->missing($path);
				return false;
			}else{
				throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
			}
		}
		$this->cache->update($path,$response->getBody());
		return $this->normalizeResponse($this->cache->get($path), $path);
	}

	/**
	 * @param $path
	 * @return bool
	 * @throws GraphException
	 * @throws OneDriveException
	 */
	public function isDirectory($path){
		$meta=$this->getMetadata($path);
		return isset($meta['type'])&& $meta['type']==='dir';
	}

	/**
	 * @param $path
	 * @return bool
	 * @throws GraphException
	 * @throws OneDriveException
	 */
	public function isFile($path){
		$meta=$this->getMetadata($path);
		return isset($meta['type'])&& $meta['type']==='file';
	}


	/**
	 * @param $path
	 * @return bool
	 * @throws GraphException
	 * @throws OneDriveException
	 */
	public function has($path)
	{
		$path=$this->cleanPath($path);
		return (bool)$this->getMetadata($path);
	}


	/**
	 * @param $path
	 * @return array|bool
	 * @throws OneDriveException
	 * @throws GraphException
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

	/**
	 * @param $path
	 * @return array|bool
	 * @throws OneDriveException
	 * @throws GraphException
	 */
	public function readStream($path){
		$downloadUrl=$this->getTemporaryUrl($path);
		if($downloadUrl){
			$client=new Client();
			$stream=null;
			try {
				$response = $client->get($downloadUrl, ['stream' => true]);
				$stream = $response->getBody()->detach();

			}catch (ClientException $e){
				throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
			}
			if($stream!==null) {
				return compact('stream');
			}
		}
		return false;

	}


	/**
	 * @param string $directory
	 * @param bool $recursive
	 * @return array
	 * @throws OneDriveException
	 * @throws GraphException
	 */
	public function listContents($directory = '', $recursive = false)
	{
		$endpoint = $this->applyRootPrefix($directory).'/children';
		try {
			$results = [];
			$response = $this->graph->createRequest('GET', $endpoint)->execute();
			$items = $response->getBody()['value'];

			if (! count($items)) {
				return [];
			}

			foreach ($items as &$item) {
				$results[] = $this->normalizeResponse($item, $directory.'/'.$item['name']);

				if ($recursive && isset($item['folder'])) {
					$results = array_merge($results, $this->listContents($directory.'/'.$item['name'], true));
				}
			}
			return $results;
		} catch (ClientException $e) {
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}


	}


}