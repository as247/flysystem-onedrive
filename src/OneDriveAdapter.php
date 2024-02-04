<?php
/**
 * Created by PhpStorm.
 * User: alt
 * Date: 17-Oct-18
 * Time: 8:44 AM
 */

namespace As247\Flysystem\OneDrive;


use As247\CloudStorages\Storage\OneDrive;
use As247\CloudStorages\Support\StorageToAdapter;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use Microsoft\Graph\Graph;

class OneDriveAdapter implements FilesystemAdapter, TemporaryUrlGenerator
{
	use StorageToAdapter;
	protected $storage;
    protected $prefixer;
    public function __construct(Graph $graph, $options = '')
    {
        if(!is_array($options)){
            $options=['root'=>$options];
        }
        if(!isset($options['root']) && isset($options['prefix'])){
            $options['root']=$options['prefix'];
        }
    	$this->storage=new OneDrive($graph,$options);
        $this->prefixer = new PathPrefixer($options['root']??'', DIRECTORY_SEPARATOR);
    }
}
