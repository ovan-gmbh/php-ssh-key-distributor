<?php

require 'vendor/autoload.php';

if (php_sapi_name() !== 'cli')
{
	die;
}

error_reporting(E_ALL & ~E_DEPRECATED);

new KeyDistributor($argv);