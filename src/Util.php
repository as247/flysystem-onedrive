<?php


namespace As247\Flysystem\OneDrive;


class Util
{
	public static function cleanPath($path){
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
}
