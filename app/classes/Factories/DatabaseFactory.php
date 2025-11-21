<?php

namespace App\Factories;

use Throwable;
use App\Services\DatabaseService;
use Psr\Container\ContainerInterface;

class DatabaseFactory
{
    public function __invoke(ContainerInterface $c): DatabaseService
    {
        $dbConfig = $c->get('applicationConfig')['database'] ?? [];

        try {
            return new DatabaseService($dbConfig);
        } catch (Throwable $e) {
            // Always log the full message for debugging
            error_log('[DB INIT ERROR] ' . $e->getMessage());

            // Detect CLI vs Web early
            $isCli = (php_sapi_name() === 'cli');

            if ($isCli) {
                fwrite(STDERR, "❌ Database connection failed: {$e->getMessage()}\n");
                exit(1);
            }

            // For web, send a clean 500 response before app stack loads
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');

            echo $this->getErrorHtml();
            exit;
        }
    }

    private function getErrorHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>System Error</title>
<style>
body {
  font-family: system-ui, sans-serif;
  background: #fafafa;
  color: #333;
  margin: 4rem;
}
h1 { color: #c00; }
p  { max-width: 600px; }
</style>
</head>
<body>
<h1>Database Connection Failed</h1>
<p>The application could not connect to its database. Please check your configuration file or database server.</p>
</body>
</html>
HTML;
    }
}
