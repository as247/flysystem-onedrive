<?php
/**
 * Created by PhpStorm.
 * User: alt
 * Date: 17-Oct-18
 * Time: 8:44 AM
 */

namespace As247\Flysystem\OneDrive;

use As247\Flysystem\DriveSupport\Support\DriverForAdapter;
use Microsoft\Graph\Graph;
use League\Flysystem\Adapter\AbstractAdapter;

class OneDriveAdapter extends AbstractAdapter
{
	use DriverForAdapter;
	protected $driver;
    public function __construct(Graph $graph, string $root = '')
    {
    	$this->driver=new Driver($graph);
    	$this->setPathPrefix($root);
    }


}
