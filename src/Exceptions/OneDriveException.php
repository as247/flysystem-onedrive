<?php


namespace As247\Flysystem\OneDrive\Exceptions;


class OneDriveException extends \Exception
{
	protected $data;
	public function setData($data){
		$this->data=$data;
	}
	public function getData(){
		return $this->data;
	}
}
