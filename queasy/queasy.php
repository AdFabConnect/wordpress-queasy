<?php
/*
Plugin Name: Queasy
Description: Quiz Plugin
Author: Arnaud Hours
Version: 1.0.0
Author URI: http://www.adfab.fr/
*/

defined('ABSPATH') || die();

define('QUEASY_VERSION', '1.0.0');

class Queasy
{

	public function __construct()
	{
	    require_once 'queasy_group.php';
	    require_once 'queasy_question.php';
	}

}

new Queasy();
