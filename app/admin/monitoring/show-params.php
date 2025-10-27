<?php

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

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$_COOKIE = _sanitizeInput($request->getCookieParams());

$id = isset($_GET['id']) && !empty($_GET['id']) ? MiscUtility::desqid((string) $_GET['id']) : null;

if ($id === null) {
    http_response_code(400);
    throw new SystemException('Invalid request', 400);
}

$db->where('api_track_id', $id);
$result = $db->getOne('track_api_requests');

$userRequest = $userResponse = "{}";
$folder = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api');

// Load request data
try {
    $userRequest = ArchiveUtility::findAndDecompressArchive(
        $folder . DIRECTORY_SEPARATOR . 'requests',
        $result['transaction_id'] . '.json'
    );
} catch (Throwable $e) {
    LoggerUtility::log('error', "Failed to load request data for {$result['transaction_id']}: " . $e->getMessage());
    $userRequest = "{}";
}

// Load response data
try {
    $userResponse = ArchiveUtility::findAndDecompressArchive(
        $folder . DIRECTORY_SEPARATOR . 'responses',
        $result['transaction_id'] . '.json'
    );
} catch (Throwable $e) {
    LoggerUtility::log('error', "Failed to load response data for {$result['transaction_id']}: " . $e->getMessage());
    $userResponse = "{}";
}

$isParamsOnly = !empty($result['api_params']);

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Request Details</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/font-awesome.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1e1e1e;
            color: #d4d4d4;
            overflow: hidden;
        }

        .container {
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            background: #252526;
            border-bottom: 1px solid #3e3e42;
            padding: 12px 20px;
            flex-shrink: 0;
        }

        .api-url {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #4ec9b0;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .method-badge {
            background: #0e639c;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }

        .meta-info {
            display: flex;
            gap: 20px;
            font-size: 12px;
            color: #858585;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Toolbar */
        .toolbar {
            background: #2d2d30;
            border-bottom: 1px solid #3e3e42;
            padding: 8px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .toolbar-group {
            display: flex;
            gap: 5px;
        }

        .toolbar-btn {
            background: #3e3e42;
            border: 1px solid #555;
            color: #ccc;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .toolbar-btn:hover {
            background: #505050;
            border-color: #777;
        }

        .toolbar-btn.active {
            background: #0e639c;
            border-color: #0e639c;
            color: white;
        }

        /* Main Content */
        .content {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #1e1e1e;
            border-right: 1px solid #3e3e42;
        }

        .panel:last-child {
            border-right: none;
        }

        .panel-header {
            background: #2d2d30;
            padding: 10px 15px;
            border-bottom: 1px solid #3e3e42;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .panel-title {
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .request-icon {
            color: #4ec9b0;
        }

        .response-icon {
            color: #ce9178;
        }

        .panel-actions {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            background: transparent;
            border: 1px solid #555;
            color: #ccc;
            padding: 4px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.2s;
        }

        .action-btn:hover {
            background: #3e3e42;
            border-color: #777;
        }

        .panel-content {
            flex: 1;
            overflow: auto;
            padding: 15px;
        }

        /* JSON Tree Styles */
        .json-tree {
            font-family: 'Courier New', 'Consolas', monospace;
            font-size: 13px;
            line-height: 1.6;
        }

        .json-line {
            display: flex;
            align-items: flex-start;
        }

        .json-toggle {
            width: 20px;
            cursor: pointer;
            user-select: none;
            color: #858585;
            flex-shrink: 0;
        }

        .json-toggle:hover {
            color: #ccc;
        }

        .json-key {
            color: #9cdcfe;
            margin-right: 5px;
        }

        .json-string {
            color: #ce9178;
        }

        .json-number {
            color: #b5cea8;
        }

        .json-boolean {
            color: #569cd6;
            font-weight: 600;
        }

        .json-null {
            color: #569cd6;
            font-weight: 600;
        }

        .json-bracket {
            color: #d4d4d4;
        }

        .json-collapsed {
            display: none;
        }

        .json-indent {
            display: inline-block;
            width: 20px;
        }

        /* Search */
        .search-box {
            background: #3e3e42;
            border: 1px solid #555;
            color: #d4d4d4;
            padding: 6px 10px;
            border-radius: 3px;
            font-size: 12px;
            width: 200px;
        }

        .search-box:focus {
            outline: none;
            border-color: #0e639c;
        }

        .search-highlight {
            background-color: #6b5c00;
            color: #ffffff;
        }

        /* Scrollbar */
        .panel-content::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }

        .panel-content::-webkit-scrollbar-track {
            background: #1e1e1e;
        }

        .panel-content::-webkit-scrollbar-thumb {
            background: #424242;
            border-radius: 6px;
        }

        .panel-content::-webkit-scrollbar-thumb:hover {
            background: #4e4e4e;
        }

        /* Loading */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #858585;
        }

        /* Raw View */
        .raw-json {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            white-space: pre-wrap;
            word-wrap: break-word;
            margin: 0;
            color: #d4d4d4;
        }

        /* Status Badge */
        .status-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-success {
            background: #107c10;
            color: white;
        }

        .status-error {
            background: #e81123;
            color: white;
        }

        .status-warning {
            background: #ff8c00;
            color: white;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="api-url">
                <span class="method-badge">POST</span>
                <span><?= htmlspecialchars($result['api_url']); ?></span>
            </div>

            <div class="meta-info">
                <div class="meta-item">
                    <i class="fa fa-clock-o"></i>
                    <span><?= date('Y-m-d H:i:s', strtotime($result['requested_on'])); ?></span>
                </div>
                <div class="meta-item">
                    <i class="fa fa-hashtag"></i>
                    <span><?= htmlspecialchars($result['transaction_id']); ?></span>
                </div>
                <?php if (isset($result['response_time'])) { ?>
                    <div class="meta-item">
                        <i class="fa fa-tachometer"></i>
                        <span><?= number_format($result['response_time'], 2); ?>ms</span>
                    </div>
                <?php } ?>
                <?php if (isset($result['status_code'])) {
                    $statusClass = $result['status_code'] < 300 ? 'status-success' : ($result['status_code'] < 400 ? 'status-warning' : 'status-error');
                ?>
                    <div class="meta-item">
                        <span class="status-badge <?= $statusClass; ?>"><?= $result['status_code']; ?></span>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="toolbar-group">
                <button class="toolbar-btn active" onclick="setViewMode('tree')" id="btn-tree">
                    <i class="fa fa-sitemap"></i> Tree View
                </button>
                <button class="toolbar-btn" onclick="setViewMode('raw')" id="btn-raw">
                    <i class="fa fa-code"></i> Raw JSON
                </button>
                <?php if (!$isParamsOnly) { ?>
                    <button class="toolbar-btn" onclick="toggleLayout()" id="btn-layout">
                        <i class="fa fa-columns"></i> Split View
                    </button>
                <?php } ?>
            </div>

            <div class="toolbar-group">
                <input type="text" class="search-box" placeholder="Search..." id="search-input"
                    oninput="searchJSON(this.value)">
                <button class="toolbar-btn" onclick="expandAll()">
                    <i class="fa fa-plus-square-o"></i> Expand All
                </button>
                <button class="toolbar-btn" onclick="collapseAll()">
                    <i class="fa fa-minus-square-o"></i> Collapse All
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="content" id="main-content">
            <?php if ($isParamsOnly) { ?>
                <!-- Single Panel -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="fa fa-cogs request-icon"></i>
                            <span>API Parameters</span>
                        </div>
                        <div class="panel-actions">
                            <button class="action-btn" onclick="copyJSON('request-data')">
                                <i class="fa fa-copy"></i> Copy
                            </button>
                            <button class="action-btn" onclick="downloadJSON('request-data', 'api-params.json')">
                                <i class="fa fa-download"></i> Download
                            </button>
                        </div>
                    </div>
                    <div class="panel-content" id="request-panel">
                        <div class="loading">Loading...</div>
                    </div>
                </div>
            <?php } else { ?>
                <!-- Request Panel -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="fa fa-arrow-circle-up request-icon"></i>
                            <span>Request</span>
                        </div>
                        <div class="panel-actions">
                            <button class="action-btn" onclick="copyJSON('request-data')">
                                <i class="fa fa-copy"></i> Copy
                            </button>
                            <button class="action-btn" onclick="downloadJSON('request-data', 'request.json')">
                                <i class="fa fa-download"></i> Download
                            </button>
                        </div>
                    </div>
                    <div class="panel-content" id="request-panel">
                        <div class="loading">Loading...</div>
                    </div>
                </div>

                <!-- Response Panel -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="fa fa-arrow-circle-down response-icon"></i>
                            <span>Response</span>
                        </div>
                        <div class="panel-actions">
                            <button class="action-btn" onclick="copyJSON('response-data')">
                                <i class="fa fa-copy"></i> Copy
                            </button>
                            <button class="action-btn" onclick="downloadJSON('response-data', 'response.json')">
                                <i class="fa fa-download"></i> Download
                            </button>
                        </div>
                    </div>
                    <div class="panel-content" id="response-panel">
                        <div class="loading">Loading...</div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <script>
        // Data
        const requestData = <?= $userRequest; ?>;
        const responseData = <?= $userResponse; ?>;
        const isParamsOnly = <?= $isParamsOnly ? 'true' : 'false'; ?>;

        let viewMode = 'tree';
        let layout = 'split';

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            renderContent();
        });

        // Render content based on view mode
        function renderContent() {
            if (viewMode === 'tree') {
                document.getElementById('request-panel').innerHTML = renderJSONTree(requestData, 'request');
                if (!isParamsOnly) {
                    document.getElementById('response-panel').innerHTML = renderJSONTree(responseData, 'response');
                }
            } else {
                document.getElementById('request-panel').innerHTML = '<pre class="raw-json">' +
                    JSON.stringify(requestData, null, 2) + '</pre>';
                if (!isParamsOnly) {
                    document.getElementById('response-panel').innerHTML = '<pre class="raw-json">' +
                        JSON.stringify(responseData, null, 2) + '</pre>';
                }
            }
        }

        // Render JSON as collapsible tree
        function renderJSONTree(obj, prefix, level = 0) {
            if (obj === null) return '<span class="json-null">null</span>';
            if (typeof obj !== 'object') {
                if (typeof obj === 'string') return '<span class="json-string">"' + escapeHtml(obj) + '"</span>';
                if (typeof obj === 'number') return '<span class="json-number">' + obj + '</span>';
                if (typeof obj === 'boolean') return '<span class="json-boolean">' + obj + '</span>';
                return escapeHtml(String(obj));
            }

            const isArray = Array.isArray(obj);
            const keys = Object.keys(obj);

            if (keys.length === 0) {
                return isArray ? '<span class="json-bracket">[]</span>' : '<span class="json-bracket">{}</span>';
            }

            let html = '<div class="json-tree">';
            html += '<div class="json-line">';
            html += '<span class="json-indent">'.repeat(level);
            html += '<span class="json-toggle" onclick="toggleNode(this)">▼</span>';
            html += '<span class="json-bracket">' + (isArray ? '[' : '{') + '</span>';
            html += '</div>';

            html += '<div class="json-children">';
            keys.forEach((key, index) => {
                const value = obj[key];
                const comma = index < keys.length - 1 ? ',' : '';

                html += '<div class="json-line">';
                html += '<span class="json-indent">'.repeat(level + 1);

                if (typeof value === 'object' && value !== null && Object.keys(value).length > 0) {
                    html += '<span class="json-toggle" onclick="toggleNode(this)">▼</span>';
                } else {
                    html += '<span class="json-toggle"></span>';
                }

                if (!isArray) {
                    html += '<span class="json-key">"' + escapeHtml(key) + '"</span>: ';
                }

                if (typeof value === 'object' && value !== null) {
                    html += renderJSONTree(value, prefix + '_' + key, level + 1);
                } else {
                    html += renderJSONTree(value, prefix + '_' + key, level + 1);
                }

                html += comma;
                html += '</div>';
            });
            html += '</div>';

            html += '<div class="json-line">';
            html += '<span class="json-indent">'.repeat(level);
            html += '<span class="json-toggle"></span>';
            html += '<span class="json-bracket">' + (isArray ? ']' : '}') + '</span>';
            html += '</div>';
            html += '</div>';

            return html;
        }

        // Toggle node expansion
        function toggleNode(el) {
            if (el.textContent === '▼') {
                el.textContent = '▶';
                const parent = el.closest('.json-line').nextElementSibling;
                if (parent && parent.classList.contains('json-children')) {
                    parent.classList.add('json-collapsed');
                }
            } else {
                el.textContent = '▼';
                const parent = el.closest('.json-line').nextElementSibling;
                if (parent && parent.classList.contains('json-children')) {
                    parent.classList.remove('json-collapsed');
                }
            }
        }

        // Expand all nodes
        function expandAll() {
            document.querySelectorAll('.json-toggle').forEach(el => {
                if (el.textContent === '▶') {
                    el.textContent = '▼';
                }
            });
            document.querySelectorAll('.json-children').forEach(el => {
                el.classList.remove('json-collapsed');
            });
        }

        // Collapse all nodes
        function collapseAll() {
            document.querySelectorAll('.json-toggle').forEach(el => {
                if (el.textContent === '▼') {
                    el.textContent = '▶';
                }
            });
            document.querySelectorAll('.json-children').forEach(el => {
                el.classList.add('json-collapsed');
            });
        }

        // Set view mode
        function setViewMode(mode) {
            viewMode = mode;
            document.getElementById('btn-tree').classList.toggle('active', mode === 'tree');
            document.getElementById('btn-raw').classList.toggle('active', mode === 'raw');
            renderContent();
        }

        // Toggle layout
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

        // Copy JSON
        function copyJSON(dataName) {
            const data = dataName === 'request-data' ? requestData : responseData;
            navigator.clipboard.writeText(JSON.stringify(data, null, 2)).then(() => {
                alert('Copied to clipboard!');
            });
        }

        // Download JSON
        function downloadJSON(dataName, filename) {
            const data = dataName === 'request-data' ? requestData : responseData;
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

        // Search JSON
        function searchJSON(query) {
            // Remove previous highlights
            document.querySelectorAll('.search-highlight').forEach(el => {
                el.classList.remove('search-highlight');
            });

            if (!query) return;

            // Highlight matches
            const regex = new RegExp(escapeRegex(query), 'gi');
            document.querySelectorAll('.json-key, .json-string, .json-number').forEach(el => {
                if (regex.test(el.textContent)) {
                    el.classList.add('search-highlight');
                }
            });
        }

        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function escapeRegex(text) {
            return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
    </script>
</body>

</html>