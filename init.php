<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Module dbsetting.
 * Database management module for Firebird ODBC.
 */

// Module version
define('DBSETTING_VERSION', '1.0.5');

Kohana::$config->load('menu')
    ->set('dbsetting', array(
        'title' => 'База данных',
        'url' => 'dbsetting',
        'icon' => 'fa-cog',
        'order' => 110,
       
    ));