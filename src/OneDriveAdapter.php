<?php
/**
 * Created by PhpStorm.
 * User: alt
 * Date: 17-Oct-18
 * Time: 8:44 AM
 */

namespace As247\Flysystem\OneDrive;

use As247\CloudStorages\Storage\OneDrive;
use As247\CloudStorages\Support\GetTemporaryUrl;
use As247\CloudStorages\Support\StorageToAdapterV1;
use Microsoft\Graph\Graph;
use League\Flysystem\Adapter\AbstractAdapter;

class OneDriveAdapter extends AbstractAdapter
{
	use StorageToAdapterV1;
    use GetTemporaryUrl;
	protected $storage;
    public function __construct(Graph $graph, $options = '')
    {
        if(!is_array($options)){
            $options=['root'=>$options];
        }
    	$this->storage=new OneDrive($graph,$options);
    	$this->setPathPrefix($options['root']??'');
		$this->throwException=$options['debug']??'';
    }
	public function getTemporaryUrl($path, $expiration=null, $options=[]){
		return $this->getMetadata($path)['@downloadUrl']??'';
	}

}
