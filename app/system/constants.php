<?php

// Application version
const VERSION = '5.2.4';

// Application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'production');

// Application paths
chdir(__DIR__);

defined('ROOT_PATH')
    || define('ROOT_PATH', realpath(dirname(__FILE__) . "/../../"));

const WEB_ROOT = ROOT_PATH . DIRECTORY_SEPARATOR . 'public';
const CACHE_PATH = ROOT_PATH . DIRECTORY_SEPARATOR . 'cache';
const APPLICATION_PATH = ROOT_PATH . DIRECTORY_SEPARATOR . 'app';
const UPLOAD_PATH = WEB_ROOT . DIRECTORY_SEPARATOR . 'uploads';
const TEMP_PATH = WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary';


require_once(APPLICATION_PATH . '/system/app.constants.php');
