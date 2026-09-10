<?php
use App\Registries\AppRegistry;
use App\Services\PageUsageService;
use App\Registries\ContainerRegistry;
use Psr\Http\Message\ServerRequestInterface;
// Visible seconds reported by the browser. The body carries a page path and ass
// number of seconds, and nothing else. The service adds the time to a row this
// user's own session already opened, or does nothing.
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());
/** @var PageUsageService $pageUsage */
$pageUsage = ContainerRegistry::get(PageUsageService::class);
$pageUsage->recordTime((string) ($_POST['page'] ?? ''),(int) ($_POST['seconds'] ?? 0));
http_response_code(204);