<?php

declare(strict_types=1);

use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Utilities\ArchiveUtility;
use App\Utilities\LoggerUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

// ---------- Resolve and validate the track row ----------
$id = isset($_GET['id']) && $_GET['id'] !== '' ? MiscUtility::desqid((string)$_GET['id']) : null;
if ($id === null) {
    http_response_code(400);
    throw new SystemException('Invalid or missing id', 400);
}

$db->where('api_track_id', $id);
$result = $db->getOne('track_api_requests');

if (empty($result)) {
    http_response_code(404);
    throw new SystemException('Transaction not found', 404);
}

$transactionId = (string)($result['transaction_id'] ?? '');
if ($transactionId === '') {
    http_response_code(500);
    throw new SystemException('Transaction row is corrupt: missing transaction_id', 500);
}

// ---------- Locate archives ----------
$baseFolder = realpath(VAR_PATH . DIRECTORY_SEPARATOR . 'track-api') ?: (VAR_PATH . DIRECTORY_SEPARATOR . 'track-api');
$reqDir     = $baseFolder . DIRECTORY_SEPARATOR . 'requests';
$resDir     = $baseFolder . DIRECTORY_SEPARATOR . 'responses';
$reqName    = "$transactionId.json";
$resName    = "$transactionId.json";

// ---------- Load & decode helpers ----------
/**
 * @return array{decoded: mixed|null, raw: string|null, error: string|null}
 */
$load = function (string $dir, string $filename): array {
    $out = ['decoded' => null, 'raw' => null, 'error' => null];
    try {
        $raw = ArchiveUtility::findAndDecompressArchive($dir, $filename); // string
        // Validate + decode with JsonUtility (UTF-8 tolerant)
        if (!JsonUtility::isJSON($raw, logError: true, checkUtf8Encoding: true)) {
            throw new RuntimeException('Invalid JSON in file');
        }
        $decoded = JsonUtility::decodeJson($raw, true);
        if ($decoded === null) {
            throw new RuntimeException('JSON decode returned null');
        }
        $out['decoded'] = $decoded;
        $out['raw']     = $raw;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
        LoggerUtility::logError("Viewer load error for {$filename}: " . $e->getMessage());
    }
    return $out;
};

// ---------- Load request/response ----------
$req = $load($reqDir, $reqName);
$res = $load($resDir, $resName);

$isParamsOnly  = !empty($result['api_params']);
$bothMissing   = ($req['decoded'] === null && $res['decoded'] === null);

// Clean values for header
$apiUrl        = (string)($result['api_url'] ?? 'N/A');
$method        = strtoupper((string)($result['request_method'] ?? 'POST'));
$requestedOn   = isset($result['requested_on']) ? date('Y-m-d H:i:s', strtotime((string)$result['requested_on'])) : 'N/A';
$statusCode    = $result['status_code'] ?? null;
$responseTime  = $result['response_time'] ?? null;

// Precompute status class
$statusClass = null;
if ($statusCode !== null) {
    $statusClass = $statusCode < 300 ? 'status-success' : ($statusCode < 400 ? 'status-warning' : 'status-error');
}

// Safe JS embedding (objects or null)
$jsRequest  = $req['decoded'] !== null ? json_encode($req['decoded'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : 'null';
$jsResponse = $res['decoded'] !== null ? json_encode($res['decoded'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : 'null';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>API Request Viewer</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/font-awesome.min.css">
    <style>
        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #1e1e1e;
            color: #d4d4d4;
            overflow: hidden
        }

        .container {
            height: 100vh;
            display: flex;
            flex-direction: column
        }

        .header {
            background: #252526;
            border-bottom: 1px solid #3e3e42;
            padding: 12px 20px;
            flex-shrink: 0
        }

        .api-url {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #4ec9b0;
            margin: 0 0 8px;
            display: flex;
            gap: 8px;
            align-items: center;
            word-break: break-all
        }

        .method-badge {
            background: #0e639c;
            color: #fff;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            flex-shrink: 0
        }

        .meta-info {
            display: flex;
            gap: 20px;
            font-size: 12px;
            color: #858585;
            flex-wrap: wrap
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px
        }

        .toolbar {
            background: #2d2d30;
            border-bottom: 1px solid #3e3e42;
            padding: 8px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0
        }

        .toolbar-group {
            display: flex;
            gap: 6px;
            flex-wrap: wrap
        }

        .toolbar-btn {
            background: #3e3e42;
            border: 1px solid #555;
            color: #ccc;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            transition: .2s;
            display: flex;
            gap: 6px;
            align-items: center
        }

        .toolbar-btn:hover {
            background: #505050;
            border-color: #777
        }

        .toolbar-btn.active {
            background: #0e639c;
            border-color: #0e639c;
            color: #fff
        }

        .toolbar-btn:disabled {
            opacity: .5;
            cursor: not-allowed
        }

        .search-box {
            background: #3e3e42;
            border: 1px solid #555;
            color: #d4d4d4;
            padding: 6px 10px;
            border-radius: 3px;
            font-size: 12px;
            width: 220px
        }

        .search-box:focus {
            outline: none;
            border-color: #0e639c
        }

        .content {
            flex: 1;
            display: flex;
            overflow: hidden
        }

        .panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #1e1e1e;
            border-right: 1px solid #3e3e42
        }

        .panel:last-child {
            border-right: none
        }

        .panel.panel-missing {
            background: #2a2a2a
        }

        .panel-header {
            background: #2d2d30;
            padding: 10px 15px;
            border-bottom: 1px solid #3e3e42;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0
        }

        .panel-header.has-error {
            background: #3d2626;
            border-bottom-color: #5e3838
        }

        .panel-title {
            font-size: 13px;
            font-weight: 600;
            display: flex;
            gap: 8px;
            align-items: center
        }

        .request-icon {
            color: #4ec9b0
        }

        .response-icon {
            color: #ce9178
        }

        .error-icon {
            color: #f48771
        }

        .panel-actions {
            display: flex;
            gap: 6px
        }

        .action-btn {
            background: transparent;
            border: 1px solid #555;
            color: #ccc;
            padding: 4px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            transition: .2s
        }

        .action-btn:hover {
            background: #3e3e42;
            border-color: #777
        }

        .action-btn:disabled {
            opacity: .5;
            cursor: not-allowed
        }

        .panel-content {
            flex: 1;
            overflow: auto;
            padding: 15px
        }

        .error-message {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #858585;
            text-align: center;
            padding: 20px
        }

        .error-icon-large {
            font-size: 48px;
            color: #f48771;
            margin-bottom: 15px
        }

        .error-title {
            font-size: 16px;
            font-weight: 600;
            color: #d4d4d4;
            margin-bottom: 8px
        }

        .error-detail {
            font-size: 12px;
            color: #858585;
            font-family: 'Courier New', monospace;
            background: #2a2a2a;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            max-width: 520px;
            word-break: break-word
        }

        .both-missing-alert {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 40px;
            text-align: center
        }

        .both-missing-alert .error-icon-large {
            font-size: 64px;
            margin-bottom: 20px
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600
        }

        .status-success {
            background: #107c10;
            color: #fff
        }

        .status-warning {
            background: #ff8c00;
            color: #fff
        }

        .status-error {
            background: #e81123;
            color: #fff
        }

        .json-tree {
            font-family: 'Courier New', 'Consolas', monospace;
            font-size: 13px;
            line-height: 1.8
        }

        .json-line {
            display: flex;
            align-items: flex-start;
            min-height: 1.8em
        }

        .json-toggle {
            width: 20px;
            cursor: pointer;
            user-select: none;
            color: #858585;
            flex-shrink: 0;
            text-align: center
        }

        .json-toggle:hover {
            color: #ccc
        }

        .json-toggle:empty {
            cursor: default
        }

        .json-key {
            color: #9cdcfe;
            margin-right: 5px;
            flex-shrink: 0
        }

        .json-string {
            color: #ffb992;
            white-space: pre-wrap;
            word-break: break-word
        }

        .json-number {
            color: #c2f0b3
        }

        .json-boolean,
        .json-null {
            color: #569cd6;
            font-weight: 600
        }

        .json-bracket {
            color: #d4d4d4
        }

        .json-children.json-collapsed {
            display: none
        }

        .json-indent {
            display: inline-block;
            width: 20px;
            flex-shrink: 0
        }

        .json-value-container {
            flex: 1;
            min-width: 0;
            display: inline
        }

        .raw-json {
            background: #1e1e1e;
            color: #dcdcdc;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #3e3e42;
            white-space: pre;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
        }

        .search-highlight {
            background: #6b5c00;
            color: #fff
        }

        .panel-content::-webkit-scrollbar {
            width: 12px;
            height: 12px
        }

        .panel-content::-webkit-scrollbar-thumb {
            background: #424242;
            border-radius: 6px
        }

        .panel-content::-webkit-scrollbar-thumb:hover {
            background: #4e4e4e
        }
    </style>
</head>

<body>
    <div class="container">

        <!-- Header -->
        <div class="header">
            <div class="api-url">
                <span class="method-badge"><?= htmlspecialchars($method, ENT_QUOTES, 'UTF-8') ?></span>
                <span><?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="meta-info">
                <div class="meta-item"><i class="fa fa-clock-o"></i><span><?= htmlspecialchars($requestedOn, ENT_QUOTES, 'UTF-8') ?></span></div>
                <div class="meta-item"><i class="fa fa-hashtag"></i><span><?= htmlspecialchars($transactionId, ENT_QUOTES, 'UTF-8') ?></span></div>
                <?php if ($responseTime !== null): ?>
                    <div class="meta-item"><i class="fa fa-tachometer"></i><span><?= number_format((float)$responseTime, 2) ?>ms</span></div>
                <?php endif; ?>
                <?php if ($statusCode !== null): ?>
                    <div class="meta-item"><span class="status-badge <?= $statusClass ?>"><?= (int)$statusCode ?></span></div>
                <?php endif; ?>
                <?php if ($req['error']): ?>
                    <div class="meta-item"><i class="fa fa-exclamation-triangle error-icon"></i><span>Request file missing/corrupt</span></div>
                <?php endif; ?>
                <?php if ($res['error']): ?>
                    <div class="meta-item"><i class="fa fa-exclamation-triangle error-icon"></i><span>Response file missing/corrupt</span></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($bothMissing): ?>
            <div class="content">
                <div class="both-missing-alert">
                    <i class="fa fa-exclamation-circle error-icon-large"></i>
                    <h2>No Data Available</h2>
                    <p>Both request and response data files are missing or could not be loaded.</p>
                    <div class="error-detail">
                        <div><strong>Request Error:</strong> <?= htmlspecialchars((string)$req['error'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div style="margin-top:10px;"><strong>Response Error:</strong> <?= htmlspecialchars((string)$res['error'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <p style="margin-top:20px;font-size:12px;">
                        Transaction: <?= htmlspecialchars($transactionId, ENT_QUOTES, 'UTF-8') ?><br>
                        Base path: <?= htmlspecialchars($baseFolder, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            </div>
        <?php else: ?>

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="toolbar-group">
                    <button class="toolbar-btn active" onclick="setViewMode('tree')" id="btn-tree"><i class="fa fa-sitemap"></i> Tree</button>
                    <button class="toolbar-btn" onclick="setViewMode('raw')" id="btn-raw"><i class="fa fa-code"></i> Raw</button>
                    <?php if (!$isParamsOnly && $req['decoded'] !== null && $res['decoded'] !== null): ?>
                        <button class="toolbar-btn" onclick="toggleLayout()" id="btn-layout"><i class="fa fa-columns"></i> Split View</button>
                    <?php endif; ?>
                </div>
                <div class="toolbar-group">
                    <input type="text" class="search-box" placeholder="Search…" id="search-input" oninput="searchJSON(this.value)">
                    <button class="toolbar-btn" onclick="expandAll()"><i class="fa fa-plus-square-o"></i> Expand</button>
                    <button class="toolbar-btn" onclick="collapseAll()"><i class="fa fa-minus-square-o"></i> Collapse</button>
                    <button class="toolbar-btn" onclick="beautify()"><i class="fa fa-magic"></i> Beautify</button>
                </div>
            </div>

            <!-- Content -->
            <div class="content" id="main-content">

                <!-- Request -->
                <div class="panel <?= $req['decoded'] === null ? 'panel-missing' : '' ?>">
                    <div class="panel-header <?= $req['decoded'] === null ? 'has-error' : '' ?>">
                        <div class="panel-title">
                            <i class="fa fa-arrow-circle-up <?= $req['decoded'] === null ? 'error-icon' : 'request-icon' ?>"></i>
                            <span>Request <?= $req['decoded'] === null ? '(Missing)' : '' ?></span>
                        </div>
                        <div class="panel-actions">
                            <button class="action-btn" onclick="copyJSON('request')" <?= $req['decoded'] === null ? 'disabled' : '' ?>><i class="fa fa-copy"></i> Copy</button>
                            <button class="action-btn" onclick="downloadJSON('request','request.json')" <?= $req['decoded'] === null ? 'disabled' : '' ?>><i class="fa fa-download"></i> Download</button>
                        </div>
                    </div>
                    <div class="panel-content" id="request-panel">
                        <?php if ($req['decoded'] === null): ?>
                            <div class="error-message">
                                <i class="fa fa-file-o error-icon-large"></i>
                                <div class="error-title">Request Not Available</div>
                                <p>Couldn’t load <code><?= htmlspecialchars($reqName, ENT_QUOTES, 'UTF-8') ?></code>.</p>
                                <div class="error-detail"><?= htmlspecialchars((string)$req['error'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php else: ?>
                            <div>Loading…</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Response -->
                <?php if (!$isParamsOnly): ?>
                    <div class="panel <?= $res['decoded'] === null ? 'panel-missing' : '' ?>">
                        <div class="panel-header <?= $res['decoded'] === null ? 'has-error' : '' ?>">
                            <div class="panel-title">
                                <i class="fa fa-arrow-circle-down <?= $res['decoded'] === null ? 'error-icon' : 'response-icon' ?>"></i>
                                <span>Response <?= $res['decoded'] === null ? '(Missing)' : '' ?></span>
                            </div>
                            <div class="panel-actions">
                                <button class="action-btn" onclick="copyJSON('response')" <?= $res['decoded'] === null ? 'disabled' : '' ?>><i class="fa fa-copy"></i> Copy</button>
                                <button class="action-btn" onclick="downloadJSON('response','response.json')" <?= $res['decoded'] === null ? 'disabled' : '' ?>><i class="fa fa-download"></i> Download</button>
                            </div>
                        </div>
                        <div class="panel-content" id="response-panel">
                            <?php if ($res['decoded'] === null): ?>
                                <div class="error-message">
                                    <i class="fa fa-file-o error-icon-large"></i>
                                    <div class="error-title">Response Not Available</div>
                                    <p>Couldn’t load <code><?= htmlspecialchars($resName, ENT_QUOTES, 'UTF-8') ?></code>.</p>
                                    <div class="error-detail"><?= htmlspecialchars((string)$res['error'], ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            <?php else: ?>
                                <div>Loading…</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    </div>

    <script>
        // ---------- Data from PHP ----------
        const requestData = <?= $jsRequest ?>;
        const responseData = <?= $jsResponse ?>;
        const isParamsOnly = <?= $isParamsOnly ? 'true' : 'false' ?>;

        let viewMode = 'tree';
        let layout = 'split';

        document.addEventListener('DOMContentLoaded', function() {
            renderContent();
        });

        // ---------- Rendering ----------
        function renderContent() {
            if (requestData !== null) {
                document.getElementById('request-panel').innerHTML =
                    viewMode === 'tree' ? renderJSONTree(requestData, 'request') :
                    '<pre class="raw-json">' + highlightJson(JSON.stringify(requestData, null, 2)) + '</pre>';
            }
            if (!isParamsOnly && responseData !== null) {
                document.getElementById('response-panel').innerHTML =
                    viewMode === 'tree' ? renderJSONTree(responseData, 'response') :
                    '<pre class="raw-json">' + highlightJson(JSON.stringify(responseData, null, 2)) + '</pre>';
            }
        }

        function renderJSONTree(obj, prefix, level = 0) {
            if (obj === null) return '<span class="json-null">null</span>';

            const t = typeof obj;
            if (t !== 'object') {
                if (t === 'string') return '<span class="json-string">"' + escapeHtml(obj) + '"</span>';
                if (t === 'number') return '<span class="json-number">' + String(obj) + '</span>';
                if (t === 'boolean') return '<span class="json-boolean">' + String(obj) + '</span>';
                return '<span class="json-string">' + escapeHtml(String(obj)) + '</span>';
            }

            const isArray = Array.isArray(obj);
            const keys = isArray ? obj.map((_, i) => i) : Object.keys(obj);
            if (keys.length === 0) return '<span class="json-bracket">' + (isArray ? '[]' : '{}') + '</span>';

            let html = '<div class="json-tree">';

            // Opening
            html += '<div class="json-line">';
            html += '<span class="json-indent"></span>'.repeat(level);
            html += '<span class="json-toggle" onclick="toggleNode(this)">▼</span>';
            html += '<span class="json-bracket">' + (isArray ? '[' : '{') + '</span>';
            html += '</div>';

            // Children
            html += '<div class="json-children">';
            keys.forEach((key, idx) => {
                const value = isArray ? obj[key] : obj[key];
                const comma = idx < keys.length - 1 ? ',' : '';

                html += '<div class="json-line">';
                html += '<span class="json-indent"></span>'.repeat(level + 1);

                const expandable = (value !== null && typeof value === 'object' && Object.keys(value).length > 0);
                html += '<span class="json-toggle" ' + (expandable ? 'onclick="toggleNode(this)"' : '') + '>' + (expandable ? '▼' : '') + '</span>';

                if (!isArray) {
                    html += '<span class="json-key">"' + escapeHtml(String(key)) + '"</span>: ';
                }

                html += '<div class="json-value-container">' + renderJSONTree(value, prefix + '_' + key, level + 1) + '</div>' + comma;
                html += '</div>';
            });
            html += '</div>';

            // Closing
            html += '<div class="json-line">';
            html += '<span class="json-indent"></span>'.repeat(level);
            html += '<span class="json-toggle"></span>';
            html += '<span class="json-bracket">' + (isArray ? ']' : '}') + '</span>';
            html += '</div>';

            html += '</div>';
            return html;
        }

        // ---------- Interactions ----------
        function toggleNode(el) {
            if (!el.textContent) return;
            const children = el.closest('.json-line').nextElementSibling;
            if (!children || !children.classList.contains('json-children')) return;

            if (el.textContent === '▼') {
                el.textContent = '▶';
                children.classList.add('json-collapsed');
            } else {
                el.textContent = '▼';
                children.classList.remove('json-collapsed');
            }
        }

        function expandAll() {
            document.querySelectorAll('.json-toggle').forEach(el => {
                if (el.textContent === '▶') el.textContent = '▼';
            });
            document.querySelectorAll('.json-children').forEach(el => el.classList.remove('json-collapsed'));
        }

        function collapseAll() {
            document.querySelectorAll('.json-toggle').forEach(el => {
                if (el.textContent === '▼') el.textContent = '▶';
            });
            document.querySelectorAll('.json-children').forEach(el => el.classList.add('json-collapsed'));
        }

        function setViewMode(mode) {
            viewMode = mode;
            document.getElementById('btn-tree').classList.toggle('active', mode === 'tree');
            document.getElementById('btn-raw').classList.toggle('active', mode === 'raw');
            renderContent();
        }

        function toggleLayout() {
            const content = document.getElementById('main-content');
            if (layout === 'split') {
                layout = 'tabs';
                content.style.flexDirection = 'column';
                document.getElementById('btn-layout').innerHTML = '<i class="fa fa-columns"></i> Split View';
            } else {
                layout = 'split';
                content.style.flexDirection = 'row';
                document.getElementById('btn-layout').innerHTML = '<i class="fa fa-list"></i> Tab View';
            }
        }

        function copyJSON(which) {
            const data = which === 'request' ? requestData : responseData;
            if (data === null) {
                alert('No data');
                return;
            }
            navigator.clipboard.writeText(JSON.stringify(data, null, 2)).then(() => {
                const btn = event.target.closest('button');
                const old = btn.innerHTML;
                btn.innerHTML = '<i class="fa fa-check"></i> Copied';
                setTimeout(() => btn.innerHTML = old, 1500);
            }).catch(err => alert('Copy failed: ' + err));
        }

        function downloadJSON(which, filename) {
            const data = which === 'request' ? requestData : responseData;
            if (data === null) {
                alert('No data');
                return;
            }
            const blob = new Blob([JSON.stringify(data, null, 2)], {
                type: 'application/json'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
        }

        function beautify() {
            if (viewMode === 'raw') {
                renderContent();
                return;
            }
            // no-op: tree already pretty; in Raw view it pretty-prints via JSON.stringify
            setViewMode('raw'); // quick access to pretty text
        }

        // ---------- Search ----------
        function searchJSON(q) {
            document.querySelectorAll('.search-highlight').forEach(el => el.classList.remove('search-highlight'));
            if (!q) return;
            const re = new RegExp(escapeRegex(q), 'i');
            document.querySelectorAll('.json-key,.json-string,.json-number').forEach(el => {
                if (re.test(el.textContent)) {
                    el.classList.add('search-highlight');
                    // expand parents
                    let parent = el.closest('.json-children');
                    while (parent) {
                        parent.classList.remove('json-collapsed');
                        const t = parent.previousElementSibling?.querySelector('.json-toggle');
                        if (t && t.textContent === '▶') t.textContent = '▼';
                        parent = parent.parentElement?.closest('.json-children');
                    }
                }
            });
        }

        // ---------- Utils ----------
        function escapeHtml(s) {
            const d = document.createElement('div');
            d.textContent = String(s);
            return d.innerHTML;
        }

        function escapeRegex(s) {
            return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        function highlightJson(jsonString) {
            return jsonString
                .replace(/(&)/g, '&amp;')
                .replace(/(>)/g, '&gt;')
                .replace(/(<)/g, '&lt;')
                .replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(\.\d+)?([eE][+\-]?\d+)?)/g,
                    function(match) {
                        let cls = 'json-number';
                        if (/^"/.test(match)) {
                            cls = /:$/.test(match) ? 'json-key' : 'json-string';
                        } else if (/true|false/.test(match)) {
                            cls = 'json-boolean';
                        } else if (/null/.test(match)) {
                            cls = 'json-null';
                        }
                        return '<span class="' + cls + '">' + match + '</span>';
                    });
        }
    </script>
</body>

</html>