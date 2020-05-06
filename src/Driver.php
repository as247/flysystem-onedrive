<?php


namespace As247\Flysystem\OneDrive;

use As247\Flysystem\DriveSupport\Contracts\Driver as DriverContract;
use As247\Flysystem\DriveSupport\Exception\ApiException;
use As247\Flysystem\DriveSupport\Exception\FileNotFoundException;
use As247\Flysystem\DriveSupport\Exception\InvalidVisibilityProvided;
use As247\Flysystem\DriveSupport\Exception\UnableToCopyFile;
use As247\Flysystem\DriveSupport\Exception\UnableToCreateDirectory;
use As247\Flysystem\DriveSupport\Exception\UnableToDeleteDirectory;
use As247\Flysystem\DriveSupport\Exception\UnableToDeleteFile;
use As247\Flysystem\DriveSupport\Exception\UnableToMoveFile;
use As247\Flysystem\DriveSupport\Exception\UnableToReadFile;
use As247\Flysystem\DriveSupport\Exception\UnableToRetrieveMetadata;
use As247\Flysystem\DriveSupport\Exception\UnableToWriteFile;
use As247\Flysystem\DriveSupport\Service\OneDrive;
use As247\Flysystem\DriveSupport\Support\FileAttributes;
use As247\Flysystem\DriveSupport\Support\Path;
use As247\Flysystem\DriveSupport\Support\StorageAttributes;
use GuzzleHttp\Exception\ClientException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\FilesystemException;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use function GuzzleHttp\Psr7\stream_for;

class Driver implements DriverContract
{
	/** @var Graph */
	protected $oneDrive;

	public function __construct(Graph $graph)
	{
		$this->oneDrive = new OneDrive($graph);
	}


	/**
	 * @param string $directory
	 * @param bool $recursive
	 * @return \Generator
	 */
	public function listContents(string $directory = '', bool $recursive = false): iterable
	{
		try {
			$results = $this->oneDrive->listChildren($directory);
			foreach ($results as $id => $result) {
				$result = $this->oneDrive->normalizeMetadata($result, $directory . '/' . $result['name']);
				if ($recursive) {
					if ($result['type'] === 'dir') {
						yield from $this->listContents($result['path'], $recursive);
					}
				}
				yield $id => $result;
			}
		} catch (ClientException $e) {
			if ($e->getResponse()->getStatusCode() === 404) {
				yield from [];
			}
		}
	}


	public function write(string $path, string $contents, Config $config): void
	{
		$this->writeStream($path, stream_for($contents), $config);
	}

	public function writeStream(string $path, $contents, Config $config): void
	{
		try {
			$this->oneDrive->upload($path, $contents, $config);
		} catch (ClientException $e) {
			throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
		} catch (GraphException $e) {
			throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
		}
	}

	public function read(string $path): string
	{
		return (string)stream_get_contents($this->readStream($path));
	}

	public function readStream(string $path)
	{
		try {
			return $this->oneDrive->download($path);
		} catch (ClientException $e) {
			throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
		} catch (GraphException $e) {
			throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
		}
	}

	public function delete(string $path): void
	{
		try {
			$this->oneDrive->delete($path);
		} catch (ClientException $e) {
			if ($e->getResponse()->getStatusCode() === 404) {
				throw FileNotFoundException::create($path);
			}
			throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
		} catch (GraphException $e) {
			throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
		}
	}

	public function deleteDirectory(string $path): void
	{
		$this->delete($path);
	}

	public function createDirectory(string $path, Config $config): void
	{
		try {
			$response = $this->oneDrive->createDirectory($path);
			$file = FileAttributes::fromArray($this->oneDrive->normalizeMetadata($response, $path));
			if (!$file->isDir()) {
				throw UnableToCreateDirectory::atLocation($path, 'File already exists');
			}
		} catch (GraphException $e) {
			throw UnableToCreateDirectory::atLocation($path, $e->getMessage());
		} catch (ClientException $e) {
			throw UnableToCreateDirectory::atLocation($path, $e->getMessage());
		}
	}

	public function setVisibility(string $path, $visibility): void
	{
		if ($visibility === AdapterInterface::VISIBILITY_PUBLIC) {
			$this->oneDrive->publish($path);
		} elseif ($visibility === AdapterInterface::VISIBILITY_PRIVATE) {
			$this->oneDrive->unPublish($path);
		} else {
			throw InvalidVisibilityProvided::withVisibility($visibility, join(' or ', [AdapterInterface::VISIBILITY_PUBLIC, AdapterInterface::VISIBILITY_PRIVATE]));
		}
	}

	public function visibility(string $path): FileAttributes
	{
		return $this->getMetadata($path);
	}

	public function mimeType(string $path): FileAttributes
	{
		return $this->getMetadata($path);
	}

	public function lastModified(string $path): FileAttributes
	{
		return $this->getMetadata($path);
	}

	public function fileSize(string $path): FileAttributes
	{
		return $this->getMetadata($path);
	}

	public function move(string $source, string $destination, Config $config): void
	{
		$this->oneDrive->move($source, $destination);
	}

	public function copy(string $source, string $destination, Config $config): void
	{
		$this->oneDrive->copy($source, $destination);
	}

	public function fileExists(string $path): bool
	{
		try {
			$meta = $this->getMetadata($path);
			return $meta->isFile();
		} catch (FileNotFoundException $exception) {
			return false;
		}
	}

	public function isDirectory(string $path): bool
	{
		try {
			$meta = $this->getMetadata($path);
			return $meta->isDir();
		} catch (FileNotFoundException $exception) {
			return false;
		}
	}

	public function getMetadata($path): FileAttributes
	{
		try {
			$meta = $this->oneDrive->getItem($path, ['expand' => 'permissions']);
			$attributes = $this->oneDrive->normalizeMetadata($meta, $path);
			return FileAttributes::fromArray($attributes);
		} catch (ClientException $e) {
			if ($e->getResponse()->getStatusCode() === 404) {
				throw new FileNotFoundException($path, 0, $e);
			}
			throw UnableToRetrieveMetadata::create($path, 'metadata', '', $e);
		} catch (\Throwable $e) {
			throw UnableToRetrieveMetadata::create($path, 'metadata', '', $e);
		}
	}
}
