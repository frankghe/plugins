<?php
include_once(dirname(__FILE__) . "/../../../classes/PluginsClassiques.class.php");

class Tinymceplus extends PluginsClassiques
{
    const VERSION = '1.0.0';
    const MODULE = 'tinymceplus';

	public function __construct()
	{
		parent::__construct(self::MODULE);
	}
}
?>
