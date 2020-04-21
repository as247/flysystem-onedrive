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

class Driver implements DriverInterface
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
	protected $fields='';
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
	 * @param $path
	 * @param $newPath
	 * @throws OneDriveException
	 */
	public function move(string $path, string $newPath, Config $config=null):void
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
		} catch (GraphException $e){
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}
		$this->cache->rename($path,$newPath);
	}

	/**
	 * @param $path
	 * @param $newPath
	 * @throws OneDriveException
	 */
	public function copy(string $path, string $newPath, Config $config=null):void
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
		}catch (GraphException $e){
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}
	}

	/**
	 * @param $path
	 * @throws OneDriveException
	 */
	public function delete(string $path):void
	{
		$endpoint = $this->prefixForEndpoint($path);
		if($endpoint===$this->prefixForEndpoint('')){
			throw new OneDriveException("Root directory cannot be deleted");
		}
		try {
			$this->graph->createRequest('DELETE', $endpoint)->execute();
		} catch (ClientException $e) {
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		} catch (GraphException $e){
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}
		$this->cache->deleteDir($path);//assume that this is dir because we using path
	}

	/**
	 * @param $dirname
	 * @throws OneDriveException
	 */
	public function deleteDirectory(string $dirname):void
	{
		$this->delete($dirname);
	}




	/**
	 * @param $path
	 * @param $visibility
	 * @throws OneDriveException
	 */
	public function setVisibility(string $path, $visibility, Config $config=null):void
	{
		($visibility === AdapterInterface::VISIBILITY_PUBLIC) ? $this->publish($path) : $this->unPublish($path);
	}

	/**
	 * @param $path
	 * @return mixed
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
		}catch (GraphException $e){
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}
		return $response->getBody();
	}

	/**
	 * @param $path
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
			return ;
		}
		$endpoint=$this->prefixForEndpoint($path,'permissions/'.$idToRemove);
		try{
			$this->graph->createRequest('DELETE', $endpoint)->execute();
		}catch (ClientException $e){
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}catch (GraphException $e){
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}
	}

	/**
	 * @param array $response
	 * @param string $path
	 * @return array
	 */
	protected function normalizeMetadata(array $response, string $path): array
	{
		$permissions=$response['permissions']??[];
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

		return [
			'path' => $path,
			'timestamp' => strtotime($response['lastModifiedDateTime']),
			'size' => $response['size'],
			'bytes' => $response['size'],
			'type' => isset($response['file']) ? 'file' : 'dir',
			'mimetype' => $response['file']['mimeType'] ?? null,
			'link' => isset($response['webUrl']) ? $response['webUrl'] : null,
			'visibility'=>$visibility,
			'downloadUrl' => isset($response['@microsoft.graph.downloadUrl']) ? $response['@microsoft.graph.downloadUrl'] : null,
		];
	}





	protected function prefixForEndpoint($path, $action = '', $params=[]){
		$path = $this->prefixForPath($path);
		if ($action) {
			$path= rtrim($path,':') . ':/' . $action; // /me/drive/root:/path:/action, /me/drive/root:/action
		}
		if($params){
			$path.='?'.http_build_query($params);
		}
		return $path;
	}

	protected function prefixForPath($path)
	{
		$path=Util::cleanPath($path);
		$path=$this->rootPath.'/'.ltrim($path,'\\/');
		$path=trim($path,'\\/');
		$prefixedPath = static::ROOT.':';///me/drive/root:
		if ($path) {
			$prefixedPath = $prefixedPath . '/' . $path . '';// /me/drive/root:/path
		}
		return $prefixedPath;
	}

	/**
	 * @param $path
	 * @return array
	 * @throws OneDriveException
	 */
	protected function getPermissions($path){
		$endpoint=$this->prefixForEndpoint($path,'permissions');
		try {
			$response = $this->graph->createRequest('GET', $endpoint)->execute();
		} catch (ClientException $e) {
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}catch (GraphException $e){
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
	 * @throws OneDriveException
	 * @return array
	 */
	public function visibility(string $path):array {
		return $this->getMetadata($path);
	}

	/**
	 * @param $path
	 * @return mixed
	 * @throws OneDriveException
	 */
	public function getUrl($path){
		$meta=$this->getMetadata($path);
		if(!empty($meta['link'])) {
			return $meta['link'];
		}
		throw new OneDriveException("No metadata for $path");
	}

	/**
	 * @param $path
	 * @param int $expire
	 * @param array $options
	 * @return mixed
	 * @throws OneDriveException
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
	 * @param bool $withPermissions fetch permissions
	 * @return array|bool
	 * @throws OneDriveException
	 */
	public function getMetadata($path,$withPermissions=false)
	{
		$path=Util::cleanPath($path);

		if($this->cache->has($path)){
			if($data=$this->cache->get($path)) {
				if($withPermissions && !isset($data['permissions'])){
					$data['permissions']=$this->getPermissions($path);
				}
				return $this->normalizeMetadata($data, $path);
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
			if($e->getResponse()->getStatusCode()===404){//404 is not an error just file not exists
				$this->cache->missing($path);
				return false;
			}else{
				throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
			}
		}catch (GraphException $e){
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}
		$data=$response->getBody();
		if($withPermissions && !isset($response['permissions'])){
			$data['permissions']=$this->getPermissions($path);
		}
		$this->cache->update($path,$data);
		return $this->normalizeMetadata($this->cache->get($path), $path);
	}

	/**
	 * @param $path
	 * @return bool
	 * @throws OneDriveException
	 */
	public function isDirectory($path){
		$meta=$this->getMetadata($path);
		return isset($meta['type'])&& $meta['type']==='dir';
	}

	/**
	 * @param $path
	 * @return bool
	 * @throws OneDriveException
	 */
	public function isFile($path){
		$meta=$this->getMetadata($path);
		return isset($meta['type'])&& $meta['type']==='file';
	}


	/**
	 * @param $path
	 * @return bool
	 * @throws OneDriveException
	 */
	public function has($path)
	{
		$path=Util::cleanPath($path);
		return (bool)$this->getMetadata($path);
	}


	/**
	 * @param $path
	 * @return string
	 * @throws OneDriveException
	 */
	public function read(string $path):string
	{
		$stream=$this->readStream($path);
		return (string)stream_get_contents($stream);
	}

	/**
	 * @param $path
	 * @return resource
	 * @throws OneDriveException
	 */
	public function readStream(string $path){
		$downloadUrl=$this->getTemporaryUrl($path);
		if($downloadUrl){
			$client=new Client();
			try {
				$response = $client->get($downloadUrl, ['stream' => true]);
				return $response->getBody()->detach();

			}catch (ClientException $e){
				throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
			}catch (GraphException $e){
				throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
			}
		}
		throw new OneDriveException('Failed to read '.$path);

	}


	/**
	 * @param string $directory
	 * @param bool $recursive
	 * @return \Generator
	 * @throws OneDriveException
	 */
	public function listContents(string $directory = '', bool $recursive = false):iterable
	{
		$results=$this->fetchDirectory($directory);
		if ($recursive) {
			foreach ($results as $id=>$result) {
				if ($result['type'] === 'dir') {
					yield from $this->fetchDirectory($directory) ;

				}
				yield $id=>$result;
			}
		}else {
			yield from $results;
		}
	}

	/**
	 * @param $directory
	 * @return \Generator
	 * @throws OneDriveException
	 */
	protected function fetchDirectory($directory){
		$endpoint = $this->prefixForEndpoint($directory,'children',[]);
		if($this->cache->isComplete($directory)){
			foreach ($this->cache->listContents($directory) as $path => $file) {
				if(!$file){
					continue;
				}
				yield $this->normalizeMetadata($file, $path);

			}
			return null;
		}
		$nextPage=null;
		do {
			try {
				if($nextPage){
					$endpoint=$nextPage;
				}
				$response = $this->graph->createRequest('GET', $endpoint)
					->execute();
				$items = $response->getBody()['value'];
				$nextPage = $response->getBody()['@odata.nextLink']??null;

				if (!count($items)) {
					yield from [];
				}

				foreach ($items as $item) {
					$result=$this->normalizeMetadata($item, $directory . '/' . $item['name']);
					$this->cache->update($result['path'],$item);
					yield $result;
				}
			} catch (ClientException $e) {
				throw new OneDriveException($e->getMessage(), $e->getCode(), $e);
			} catch (GraphException $e) {
				throw new OneDriveException($e->getMessage(), $e->getCode(), $e);
			}
		}while($nextPage);

		$this->cache->setComplete($directory);

	}


	/**
	 * @inheritDoc
	 */
	public function fileExists(string $path): bool
	{
		return $this->isFile($path);
	}

	/**
	 * @inheritDoc
	 */
	public function write(string $path, string $contents, Config $config=null): void
	{
		$this->writeStream($path,$contents,$config);
	}

	/**
	 * @inheritDoc
	 */
	public function writeStream(string $path, $contents, Config $config=null): void
	{
		$endpoint = $this->prefixForEndpoint($path,'content');

		if (is_resource($contents)) {
			$stats = fstat($contents);
			if (empty($stats['size'])) {
				throw new OneDriveException('Empty stream');
			}
		}
		$stream = stream_for($contents);
		try {
			$response=$this->graph->createRequest('PUT', $endpoint)
				->attachBody($stream)
				->execute();
			$this->cache->update($path,$response->getBody());
		}catch (ClientException $e){
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}catch (GraphException $e){
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}



	}

	/**
	 * @param $path
	 * @return array|bool
	 * @throws OneDriveException
	 */
	protected function ensureDirectory($path)
	{
		$path=Util::cleanPath($path);
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
		}catch (GraphException $e){
			throw new OneDriveException($e->getMessage(),$e->getCode(),$e);
		}
		$this->cache->update($path,$response->getBody());
		return $this->normalizeMetadata($response->getBody(), $path);
	}
	/**
	 * @inheritDoc
	 */
	public function createDirectory(string $path, Config $config=null): void
	{
		$this->ensureDirectory($path);
	}

	/**
	 * @inheritDoc
	 */
	public function mimeType(string $path): array
	{
		return $this->getMetadata($path);
	}

	/**
	 * @inheritDoc
	 */
	public function lastModified(string $path): array
	{
		return $this->getMetadata($path);
	}

	/**
	 * @inheritDoc
	 */
	public function fileSize(string $path): array
	{
		return $this->getMetadata($path);
	}
}
