<?php

const VERSION = '5.1.8';


/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */

chdir(__DIR__);

// Setup Application Constants
defined('ROOT_PATH')
    || define('ROOT_PATH', realpath(dirname(__FILE__) . "/../../"));

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



const SAMPLE_STATUS_ON_HOLD = 1;
const SAMPLE_STATUS_LOST_OR_MISSING = 2;
const SAMPLE_STATUS_REORDERED_FOR_TESTING = 3;
const SAMPLE_STATUS_REJECTED = 4;
const SAMPLE_STATUS_TEST_FAILED = 5;
const SAMPLE_STATUS_RECEIVED_AT_TESTING_LAB = 6;
const SAMPLE_STATUS_ACCEPTED = 7;
const SAMPLE_STATUS_PENDING_APPROVAL = 8;
const SAMPLE_STATUS_RECEIVED_AT_CLINIC = 9;
const SAMPLE_STATUS_EXPIRED = 10;
const SAMPLE_STATUS_NO_RESULT = 11;
