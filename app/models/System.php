<?php

namespace App\Models;

class System
{

    protected $db = null;

    public function __construct($db = null)
    {
        $this->db = $db ?? \MysqliDb::getInstance();
    }

    public function setDb($db)
    {
        $this->db = $db;
    }

    public function setupTranslation($domain = "messages")
    {
        $general = new \App\Models\General($this->db);
        $locale = $_SESSION['APP_LOCALE'] = $_SESSION['APP_LOCALE'] ??
            $general->getGlobalConfig('app_locale') ?? 'en_US';

        putenv('LC_ALL=' . $locale);
        putenv('LANGUAGE=' . $locale);
        setlocale(LC_ALL, $locale);
        bindtextdomain($domain, APPLICATION_PATH . DIRECTORY_SEPARATOR . 'locales');
        bind_textdomain_codeset($domain, 'UTF-8');
        textdomain($domain);
    }

    public static function getActiveTestModules(): array
    {
        $response = array();

        if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {
            $response[] = 'vl';
        }

        if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
            $response[] = 'eid';
        }

        if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
            $response[] = 'covid19';
        }

        if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) {
            $response[] = 'hepatitis';
        }

        if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) {
            $response[] = 'tb';
        }

        return $response;
    }
}
