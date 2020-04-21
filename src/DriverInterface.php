<?php


namespace As247\Flysystem\OneDrive;


use As247\Flysystem\OneDrive\Exceptions\OneDriveException;
use League\Flysystem\Config;

interface DriverInterface
{
	/**
	 * @throws OneDriveException
	 */
	public function fileExists(string $path): bool;

	/**
	 * @throws OneDriveException
	 * @throws OneDriveException
	 */
	public function write(string $path, string $contents, Config $config): void;

	/**
	 * @param resource $contents
	 * @throws OneDriveException
	 */
	public function writeStream(string $path, $contents, Config $config): void;

	/**
	 * @throws OneDriveException
	 */
	public function read(string $path): string;

	/**
	 * @return resource
	 * @throws OneDriveException
	 */
	public function readStream(string $path);

	/**
	 * @throws OneDriveException
	 */
	public function delete(string $path): void;

	/**
	 * @throws OneDriveException
	 */
	public function deleteDirectory(string $path): void;

	/**
	 * @throws OneDriveException
	 */
	public function createDirectory(string $path, Config $config): void;

	/**
	 * @param mixed $visibility
	 * @throws OneDriveException
	 */
	public function setVisibility(string $path, $visibility): void;

	/**
	 * @throws OneDriveException
	 */
	public function visibility(string $path): array ;

	/**
	 * @throws OneDriveException
	 */
	public function mimeType(string $path): array ;

	/**
	 * @throws OneDriveException
	 */
	public function lastModified(string $path): array ;

	/**
	 * @throws OneDriveException
	 */
	public function fileSize(string $path): array ;

	/**
	 * @param string $path
	 * @param bool   $deep
	 * @return iterable
	 * @throws OneDriveException
	 */
	public function listContents(string $path, bool $deep): iterable;

	/**
	 * @throws OneDriveException
	 */
	public function move(string $source, string $destination, Config $config): void;

	/**
	 * @throws OneDriveException
	 */
	public function copy(string $source, string $destination, Config $config): void;
}
