<?php

const VERSION = '5.1.3';


/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */

chdir(__DIR__);

// Setup Application Constants
defined('ROOT_PATH')
    || define('ROOT_PATH', realpath(dirname(__FILE__)));

defined('WEB_ROOT')
    || define('WEB_ROOT', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');

defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');

defined('UPLOAD_PATH')
    || define('UPLOAD_PATH', WEB_ROOT . DIRECTORY_SEPARATOR . 'uploads');

defined('TEMP_PATH')
    || define('TEMP_PATH', WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary');

defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'production');
