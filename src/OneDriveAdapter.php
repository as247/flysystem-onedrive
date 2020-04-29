<?php
/**
 * Created by PhpStorm.
 * User: alt
 * Date: 17-Oct-18
 * Time: 8:44 AM
 */

namespace As247\Flysystem\OneDrive;

use As247\Flysystem\DriveSupport\Exception\InvalidStreamProvided;
use As247\Flysystem\DriveSupport\Exception\UnableToCopyFile;
use As247\Flysystem\DriveSupport\Exception\UnableToCreateDirectory;
use As247\Flysystem\DriveSupport\Exception\UnableToDeleteDirectory;
use As247\Flysystem\DriveSupport\Exception\UnableToDeleteFile;
use As247\Flysystem\DriveSupport\Exception\UnableToMoveFile;
use As247\Flysystem\DriveSupport\Exception\UnableToReadFile;
use As247\Flysystem\DriveSupport\Exception\UnableToWriteFile;
use As247\Flysystem\DriveSupport\Support\DriverForAdapter;
use As247\Flysystem\DriveSupport\Support\Path;
use As247\Flysystem\OneDrive\Exceptions\OneDriveException;
use League\Flysystem\Config;
use Microsoft\Graph\Graph;

use League\Flysystem\Adapter\AbstractAdapter;


class OneDriveAdapter extends AbstractAdapter
{
	use DriverForAdapter;
	protected $driver;
    public function __construct(Graph $graph, string $root = '')
    {
    	$this->driver=new Driver($graph);
    	$this->setPathPrefix('');
    }
    public function applyPathPrefix($path)
	{
		return Path::clean(parent::applyPathPrefix($path));
	}

}
