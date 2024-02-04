<?php


namespace As247\Flysystem\OneDrive\Tests;


use As247\CloudStorages\Support\OneDriveOauth;
use As247\Flysystem\OneDrive\OneDriveAdapter;
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\FilesystemAdapter;
use Microsoft\Graph\Graph;

class FlysystemOneDriveTest extends FilesystemAdapterTestCase
{
    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        $graph = new Graph();
        $token=new OneDriveOauth();
        $token->setClientId($_ENV['odClientId']);
        $token->setClientSecret($_ENV['odClientSecret']);
        $token->setRefreshToken($_ENV['odRefreshToken']);
        $graph->setAccessToken($token->getAccessToken());
        $options=[
            'root'=>$_ENV['odRoot'],
            //'debug'=>true,
            //'log'=>true,
        ];
        return new OneDriveAdapter($graph, $options);
    }
}
