<?php

use App\Utilities\DateUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$title = _translate("Log File Viewer") . " - " . _translate("Admin");

require_once APPLICATION_PATH . '/header.php';

?>

<style>
	.logLine br {
		line-height: 1.8;
		margin-top: 5px;
	}

	.logLine span[style*="e83e8c"] {
		background-color: rgba(232, 62, 140, 0.1);
		padding: 2px 5px;
		border-radius: 3px;
		margin-right: 5px;
	}

	.logViewer {
		display: none;
		border: 1px solid #ddd;
		border-radius: 4px;
		padding: 15px;
		white-space: pre-wrap;
		overflow-x: auto;
		background-color: #f8f9fa;
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
	}

	.logLine {
		color: #000 !important;
		font-size: 14px;
		margin: 5px 0;
		padding: 11px 44px 11px 25px;
		background-color: rgb(255, 255, 255);
		border: 2px solid #f1f1f1;
		border-left: 3px solid #4CAF50;
		font-family: 'Courier New', Courier, monospace;
		margin-bottom: 2em;
		cursor: pointer;
		transition: background-color 0.3s;
		position: relative;
		white-space: pre-wrap;
		word-break: break-word;
		text-indent: 0;
	}

	.log-line-actions {
		position: absolute;
		right: 8px;
		top: 8px;
		display: flex;
		gap: 6px;
		z-index: 2;
	}

	.log-copy-btn {
		background: #ffffff;
		border: 1px solid #e2e6ea;
		border-radius: 4px;
		width: 28px;
		height: 28px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		font-size: 12px;
		cursor: pointer;
		color: #495057;
		opacity: 0.4;
		transition: opacity 0.15s ease, box-shadow 0.15s ease;
	}

	.log-copy-btn:hover {
		opacity: 1;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
	}

	.logLine:hover .log-copy-btn {
		opacity: 1;
	}

	.log-copy-btn:focus-visible {
		outline: 2px solid #80bdff;
		outline-offset: 2px;
		opacity: 1;
	}

	.filter-chip button:focus-visible {
		outline: 2px solid #80bdff;
		outline-offset: 2px;
		border-radius: 10px;
	}

	.logLine:hover {
		background-color: #e8f0fe;
	}

	.logLine-highlight {
		box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.4);
		background-color: #e7f3ff;
	}

	.lineNumber {
		user-select: none;
		position: absolute;
		top: -0.5em;
		left: -0.25em;
		z-index: 1;
		font-size: 7em;
		color: rgba(0, 0, 0, 0.25);
		pointer-events: none;
	}

	.logLine:hover .lineNumber {
		color: rgba(0, 0, 0, 0.45);
	}

	.log-error,
	.log-ERROR {
		border-left: 3px solid #dc3545 !important;
	}

	.log-warn,
	.log-warning,
	.log-WARNING {
		border-left: 3px solid #ffc107 !important;
	}

	.log-info,
	.log-INFO {
		border-left: 3px solid #17a2b8 !important;
	}

	.log-debug,
	.log-DEBUG {
		border-left: 3px solid #6c757d !important;
	}

	.loading,
	.error {
		color: #007bff;
		text-align: center;
		padding: 20px;
		font-style: italic;
	}

	.log-search-container {
		margin-bottom: 20px;
		display: flex;
		align-items: center;
		gap: 10px;
	}

	.log-search-input {
		flex-grow: 1;
		padding: 8px 12px;
		border: 1px solid #ced4da;
		border-radius: 4px;
		font-size: 14px;
	}

	.log-controls {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 15px;
		flex-wrap: wrap;
		gap: 10px;
	}

	.log-filters {
		display: flex;
		align-items: center;
		gap: 10px;
		flex-wrap: wrap;
	}

	.log-actions {
		display: flex;
		gap: 10px;
		margin-left: auto;
	}

	.log-type-group {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.log-type-label {
		font-weight: bold;
		font-size: 12px;
		color: #495057;
	}

	.log-summary {
		background-color: #f8f9fa;
		border: 1px solid #e9ecef;
		border-radius: 4px;
		padding: 8px 12px;
		font-size: 12px;
		color: #495057;
		margin-bottom: 10px;
		display: none;
	}

	.log-summary strong {
		color: #212529;
	}

	.log-summary .summary-sep {
		color: #adb5bd;
		padding: 0 6px;
	}

	.filter-chips {
		display: none;
		flex-wrap: wrap;
		gap: 8px;
		margin-bottom: 10px;
	}

	.filter-chip {
		background: #eef2f7;
		color: #495057;
		border: 1px solid #dde3ea;
		border-radius: 14px;
		padding: 4px 10px;
		font-size: 12px;
		display: inline-flex;
		align-items: center;
		gap: 6px;
	}

	.filter-chip button {
		border: none;
		background: transparent;
		color: #6c757d;
		cursor: pointer;
		font-size: 12px;
		line-height: 1;
		padding: 0;
	}

	.filter-chip-clear {
		background: #fff3cd;
		border-color: #ffeeba;
	}

	.search-hint {
		margin-top: 6px;
		font-size: 12px;
		color: #6c757d;
	}

	.log-level-legend {
		font-size: 12px;
		color: #6c757d;
		margin-bottom: 10px;
	}

	.log-level-legend span {
		margin-right: 12px;
	}

	.log-level-legend .legend-dot {
		display: inline-block;
		width: 8px;
		height: 8px;
		border-radius: 50%;
		margin-right: 6px;
		position: relative;
		top: -1px;
	}

	.legend-error { background-color: #dc3545; }
	.legend-warning { background-color: #ffc107; }
	.legend-info { background-color: #17a2b8; }
	.legend-debug { background-color: #6c757d; }

	.jump-to-line {
		margin-bottom: 8px;
	}

	.jump-to-line .form-control {
		min-width: 80px;
		width: 80px;
	}

	.ui-datepicker {
		z-index: 2000 !important;
	}

	.log-header {
		background-color: #dc3545;
		color: white;
		padding: 1em;
		border-radius: 4px;
		margin-bottom: 15px;
		font-weight: bold;
	}

	.highlighted-text {
		background-color: #ffff00;
		padding: 1px 3px;
		border-radius: 2px;
		font-weight: bold;
	}

	.stack-line {
		color: #000;
		padding-left: 20px;
		font-size: 14px;
	}

	@media (max-width: 768px) {
		.log-controls {
			flex-direction: column;
			align-items: flex-start;
		}

		.log-filters,
		.log-actions {
			width: 100%;
		}
	}

	.search-terms-indicator {
		font-size: 12px;
		color: #666;
		font-style: italic;
		margin-top: 5px;
		padding: 8px 12px;
		background-color: #f8f9fa;
		border: 1px solid #e9ecef;
		border-radius: 4px;
	}

	.search-terms-count {
		background-color: #007bff;
		color: white;
		padding: 2px 8px;
		border-radius: 12px;
		font-size: 10px;
		margin-left: 8px;
		font-weight: bold;
	}

	#logSearchInput {
		font-family: 'Courier New', Courier, monospace;
		font-size: 13px;
	}

	.input-group-addon {
		background-color: #f8f9fa;
		border: 1px solid #ced4da;
		border-left: none;
		display: flex;
		align-items: center;
	}

	.search-examples {
		margin-top: 10px;
		padding: 10px;
		background-color: #e7f3ff;
		border-left: 4px solid #2196F3;
		font-size: 12px;
		display: none;
	}

	.search-examples.show {
		display: block;
	}

	.search-examples h6 {
		margin: 0 0 8px 0;
		color: #1976D2;
		font-weight: bold;
	}

	.search-examples ul {
		margin: 0;
		padding-left: 20px;
	}

	.search-examples li {
		margin-bottom: 4px;
	}

	.search-examples code {
		background-color: #f1f1f1;
		padding: 2px 4px;
		border-radius: 3px;
		font-family: 'Courier New', Courier, monospace;
	}

	.search-input-container {
		position: relative;
	}

	.search-help-icon {
		position: absolute;
		right: -20px;
		top: 50%;
		transform: translateY(-50%);
		color: #666;
		cursor: help;
		font-size: 16px;
		z-index: 10;
	}

	.search-help-icon:hover {
		color: #333;
	}


	.loading-indicator {
		position: fixed;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		background: rgba(0, 0, 0, 0.8);
		color: white;
		padding: 20px;
		border-radius: 5px;
		z-index: 9999;
		display: none;
	}

	.performance-info {
		background-color: #e7f3ff;
		border: 1px solid #b3d7ff;
		border-radius: 4px;
		padding: 10px;
		margin-bottom: 15px;
		font-size: 12px;
		color: #0066cc;
	}

	.file-size-warning {
		background-color: #fff3cd;
		border: 1px solid #ffeaa7;
		border-radius: 4px;
		padding: 10px;
		margin-bottom: 15px;
		color: #856404;
	}
</style>

<div class="content-wrapper">
	<section class="content-header">
		<h1> <em class="fa-solid fa-file-lines"></em> <?php echo _translate("Log File Viewer"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/system-admin/edit-config/index.php"><em class="fa-solid fa-chart-pie"></em>
					<?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("Manage Log File Viewer"); ?></li>
		</ol>
	</section>

	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="box-header">
						<div class="log-controls">
							<div class="log-filters">
								<table aria-describedby="log-date-table" class="table" style="margin: 0; width: auto;">
									<tr>
										<td><strong><?php echo _translate("Date"); ?>&nbsp;:</strong></td>
										<td>
											<input type="text" id="userDate" name="userDate" class="form-control date"
												placeholder="<?php echo _translate('Select Date'); ?>" readonly
												value="<?= DateUtility::humanReadableDateFormat(DateUtility::getCurrentDateTime()); ?>"
												style="width:220px;background:#fff;" />
										</td>
										<td>
											<button id="viewLogButton" class="btn btn-primary btn-sm">
												<span><?php echo _translate("Search"); ?></span>
											</button>
										</td>
										<td>
											<button class="btn btn-danger btn-sm"
												onclick="document.location.href = document.location">
												<span><?php echo _translate("Clear Search"); ?></span>
											</button>
										</td>
									</tr>
								</table>
							</div>

							<div class="log-actions">
								<div class="log-type-group">
									<span class="log-type-label"><?php echo _translate("Log Type"); ?>:</span>
									<div class="btn-group">
										<button id="logTypeApplication" class="btn btn-info btn-sm" data-log-type="application" onclick="viewApplicationLogs()">
											<span><?php echo _translate("System Error Logs"); ?></span>
										</button>
										<button id="logTypePhp" class="btn btn-warning btn-sm" data-log-type="php_error" onclick="viewPhpErrorLogs()">
											<span><?php echo _translate("PHP Error Logs"); ?></span>
										</button>
									</div>
								</div>
							</div>
						</div>

						<div style="text-align: right; margin-top: 8px; font-size: 12px; color: #6c757d;">
							<strong><?= _translate("Current Server Date and Time"); ?> :</strong>
							<?= DateUtility::humanReadableDateFormat(DateUtility::getCurrentDateTime(), includeTime: true, withSeconds: true); ?>
						</div>
					</div>

					<div class="box-body">
						<div id="performanceInfo" class="performance-info" style="display: none;">
							<strong>Performance Mode:</strong> <span id="performanceMode">Standard</span> |
							<strong>File Size:</strong> <span id="fileSize">Unknown</span> |
							<strong>Est. Lines:</strong> <span id="estimatedLines">Unknown</span> |
							<strong>Load Time:</strong> <span id="loadTime">0ms</span>
						</div>

						<div id="fileSizeWarning" class="file-size-warning" style="display: none;">
							<i class="fa fa-exclamation-triangle"></i>
							<strong>Large File Detected:</strong> This log file is quite large.
							Loading may take a moment. Consider using search filters to improve performance.
						</div>

						<div class="row" style="margin-bottom: 15px;">
							<div class="col-md-8">
								<div class="input-group">
									<input type="text" id="logSearchInput" class="form-control"
										placeholder="<?php echo _translate("Search in logs... Use +word for exact, \"phrase\" for exact phrase"); ?>"
										aria-label="<?php echo _translate("Search logs"); ?>">
									<span class="input-group-btn">
										<button id="searchLogsButton" class="btn btn-default" type="button"
											aria-label="<?php echo _translate("Search logs"); ?>">
											<i class="fa fa-search"></i>
										</button>
									</span>
								</div>
								<div class="search-hint">
									<?php echo _translate("Tip: Use +word for exact match, \"phrase\" for exact phrase, ^word for line start, word$ for line end."); ?>
								</div>
							</div>
							<div class="col-md-4">
								<div class="jump-to-line">
									<div class="input-group">
										<input type="number" id="jumpToLineInput" class="form-control"
											placeholder="<?php echo _translate("Line #"); ?>" min="1"
											aria-label="<?php echo _translate("Jump to line number"); ?>">
										<span class="input-group-btn">
											<button id="jumpToLineButton" class="btn btn-default" type="button"
												aria-label="<?php echo _translate("Jump to line"); ?>">
												<i class="fa fa-location-arrow"></i> <?php echo _translate("Jump"); ?>
											</button>
										</span>
									</div>
								</div>
								<div class="btn-group pull-right">
									<button id="clearFiltersButton" class="btn btn-default"
										aria-label="<?php echo _translate("Clear filters"); ?>">
										<i class="fa fa-eraser"></i> <?php echo _translate("Clear Filters"); ?>
									</button>
									<button id="exportTxtButton" class="btn btn-default"
										aria-label="<?php echo _translate("Export log file"); ?>">
										<i class="fa fa-file-text"></i> <?php echo _translate("Export Log File"); ?>
									</button>
								</div>
							</div>
						</div>

						<div class="row" style="margin-bottom: 15px;">
							<div class="col-md-12">
								<div class="btn-group" id="logLevelFilters">
									<button class="btn btn-default active" data-level="all">
										<?php echo _translate("All Levels"); ?>
									</button>
									<button class="btn btn-danger" data-level="error">
										<?php echo _translate("Errors"); ?>
									</button>
									<button class="btn btn-warning" data-level="warning">
										<?php echo _translate("Warnings"); ?>
									</button>
									<button class="btn btn-info" data-level="info">
										<?php echo _translate("Info"); ?>
									</button>
									<button class="btn btn-default" data-level="debug">
										<?php echo _translate("Debug"); ?>
									</button>
								</div>
							</div>
						</div>

						<div id="logSummary" class="log-summary" role="status" aria-live="polite"></div>
						<div id="logFilterChips" class="filter-chips" aria-label="<?php echo _translate("Active filters"); ?>"></div>

						<div class="log-level-legend">
							<span><span class="legend-dot legend-error"></span><?php echo _translate("Error"); ?></span>
							<span><span class="legend-dot legend-warning"></span><?php echo _translate("Warning"); ?></span>
							<span><span class="legend-dot legend-info"></span><?php echo _translate("Info"); ?></span>
							<span><span class="legend-dot legend-debug"></span><?php echo _translate("Debug"); ?></span>
						</div>

						<div class="logViewer" id="logViewer" style="white-space: pre-wrap;"></div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

<!-- Loading indicator -->
<div id="loadingIndicator" class="loading-indicator">
	<i class="fa fa-spinner fa-spin"></i> Loading logs...
</div>

<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script>
	let start = 0;
	let loading = false;
	let hasMoreLogs = true;
	let logType = 'application';
	let currentFilter = 'all';
	let searchTerm = '';
	let allLoadedLogs = [];
	let searchTimeout;
	let loadStartTime = 0;
	let estimatedLines = null;
	let fileSizeBytes = null;

	const STORAGE_KEY = 'vlsm_log_viewer_state';

	// Performance settings - increased for better performance
	const LINES_PER_PAGE = 50;
	const SEARCH_DEBOUNCE_TIME = 500;
	const LARGE_FILE_THRESHOLD = 10 * 1024 * 1024; // 10MB

	function padZero(num) {
		return num < 10 ? '0' + num : num;
	}

	// Debounce function for better performance
	function debounce(func, wait) {
		let timeout;
		return function executedFunction(...args) {
			const later = () => {
				clearTimeout(timeout);
				func(...args);
			};
			clearTimeout(timeout);
			timeout = setTimeout(later, wait);
		};
	}

	function sleep(ms) {
		return new Promise(resolve => setTimeout(resolve, ms));
	}

	function showLoadingIndicator() {
		$('#loadingIndicator').show();
		loadStartTime = performance.now();
	}

	function hideLoadingIndicator() {
		$('#loadingIndicator').hide();
		if (loadStartTime > 0) {
			const loadTime = Math.round(performance.now() - loadStartTime);
			$('#loadTime').text(loadTime + 'ms');
			loadStartTime = 0;
		}
	}

	function updatePerformanceInfo(fileSize, estimated, mode) {
		fileSizeBytes = fileSize;
		if (estimated) {
			estimatedLines = estimated;
		}
		$('#fileSize').text(formatFileSize(fileSize));
		$('#estimatedLines').text(estimated ? estimated.toLocaleString() : 'Unknown');
		$('#performanceMode').text(mode);
		$('#performanceInfo').show();

		if (fileSize > LARGE_FILE_THRESHOLD) {
			$('#fileSizeWarning').show();
		} else {
			$('#fileSizeWarning').hide();
		}
	}

	function formatFileSize(bytes) {
		if (bytes === 0) return '0 Bytes';
		const k = 1024;
		const sizes = ['Bytes', 'KB', 'MB', 'GB'];
		const i = Math.floor(Math.log(bytes) / Math.log(k));
		return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
	}

	function getCleanLogHtml($logLine) {
		const clone = $logLine.clone();
		clone.find('.log-line-actions').remove();
		return clone.html();
	}

	function copyToClipboard(text, lineNumber) {
		const tempDiv = document.createElement('div');
		tempDiv.innerHTML = text;
		const cleanText = tempDiv.textContent || tempDiv.innerText || '';

		navigator.clipboard.writeText(cleanText)
			.then(() => {
				toast.success("<?= _translate("Copied to clipboard - Line Number", true); ?> - " + lineNumber);
			})
			.catch(err => {
				const tempInput = document.createElement('input');
				tempInput.style.position = 'absolute';
				tempInput.style.left = '-9999px';
				tempInput.value = cleanText;
				document.body.appendChild(tempInput);
				tempInput.select();
				document.execCommand('copy');
				document.body.removeChild(tempInput);

				toast.success("<?= _translate("Copied to clipboard - Line Number", true); ?> - " + lineNumber);
			});
	}

	function detectLogLevel(logText) {
		logText = logText.toLowerCase();
		if (logText.includes('error') || logText.includes('exception') || logText.includes('fatal')) {
			return 'error';
		} else if (logText.includes('warn')) {
			return 'warning';
		} else if (logText.includes('info')) {
			return 'info';
		} else if (logText.includes('debug')) {
			return 'debug';
		}
		return 'info';
	}

	function formatLogLine(logText) {
		if (logText.trim().startsWith('#') || logText.includes(' at ') || logText.includes('/vendor/') || logText.includes('/Users/')) {
			return `<span class="stack-line">${logText}</span>`;
		}
		return logText;
	}

	function parseSearchTerms(searchString) {
		const terms = [];
		const regex = /"([^"]+)"|'([^']+)'|\^(\S+)|\+(\S+)|(\S+)\$|(\S+)\*|\*(\S+)|\b(\S+)\b/g;
		let match;

		while ((match = regex.exec(searchString)) !== null) {
			if (match[1]) {
				terms.push({
					type: 'phrase',
					value: match[1]
				});
			} else if (match[2]) {
				terms.push({
					type: 'phrase',
					value: match[2]
				});
			} else if (match[3]) {
				terms.push({
					type: 'start',
					value: match[3]
				});
			} else if (match[4]) {
				terms.push({
					type: 'exact',
					value: match[4]
				});
			} else if (match[5]) {
				terms.push({
					type: 'end',
					value: match[5]
				});
			} else if (match[6]) {
				terms.push({
					type: 'starts_with',
					value: match[6]
				});
			} else if (match[7]) {
				terms.push({
					type: 'ends_with',
					value: match[7]
				});
			} else if (match[8]) {
				terms.push({
					type: 'partial',
					value: match[8]
				});
			}
		}

		return terms.filter(term => term.value.length > 0);
	}

	function buildSearchToken(term) {
		switch (term.type) {
			case 'phrase':
				return `"${term.value}"`;
			case 'start':
				return `^${term.value}`;
			case 'end':
				return `${term.value}$`;
			case 'exact':
				return `+${term.value}`;
			case 'starts_with':
				return `${term.value}*`;
			case 'ends_with':
				return `*${term.value}`;
			default:
				return term.value;
		}
	}

	function searchAllTerms(text, searchTerms) {
		if (!searchTerms || searchTerms.trim() === '') {
			return true;
		}

		const terms = parseSearchTerms(searchTerms);

		return terms.every(term => {
			switch (term.type) {
				case 'exact':
					return new RegExp(`\\b${escapeRegExp(term.value)}\\b`, 'i').test(text);
				case 'starts_with':
					return new RegExp(`\\b${escapeRegExp(term.value)}`, 'i').test(text);
				case 'ends_with':
					return new RegExp(`${escapeRegExp(term.value)}\\b`, 'i').test(text);
				case 'start':
					return new RegExp(`^${escapeRegExp(term.value)}`, 'i').test(text);
				case 'end':
					return new RegExp(`${escapeRegExp(term.value)}$`, 'i').test(text);
				case 'phrase':
					return text.toLowerCase().includes(term.value.toLowerCase());
				default:
					return text.toLowerCase().includes(term.value.toLowerCase());
			}
		});
	}

	function highlightAllSearchTerms(text, searchTerms) {
		if (!searchTerms || searchTerms.trim() === '') {
			return text;
		}

		const terms = parseSearchTerms(searchTerms);
		let highlightedText = text;

		terms.sort((a, b) => b.value.length - a.value.length);

		terms.forEach(term => {
			let regex;

			if (term.type === 'exact') {
				regex = new RegExp(`\\b(${escapeRegExp(term.value)})\\b`, 'gi');
			} else {
				regex = new RegExp(`(${escapeRegExp(term.value)})`, 'gi');
			}

			highlightedText = highlightedText.replace(regex, match =>
				`<span class="highlighted-text">${match}</span>`
			);
		});

		return highlightedText;
	}

	function escapeRegExp(string) {
		return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	}

	function clearHighlights($element) {
		$element.find('.highlighted-text').each(function () {
			$(this).replaceWith($(this).text());
		});
	}

	function buildHighlightRegex(terms) {
		if (!terms || terms.length === 0) return null;

		const parts = terms.map(term => {
			const value = escapeRegExp(term.value);
			switch (term.type) {
				case 'exact':
					return `\\b${value}\\b`;
				case 'starts_with':
					return `\\b${value}`;
				case 'ends_with':
					return `${value}\\b`;
				case 'start':
					return `^${value}`;
				case 'end':
					return `${value}$`;
				case 'phrase':
				default:
					return `${value}`;
			}
		});

		return new RegExp(parts.join('|'), 'gi');
	}

	function highlightSearchTermsInElement($element, searchTerms) {
		clearHighlights($element);

		if (!searchTerms || searchTerms.trim() === '') {
			return;
		}

		const terms = parseSearchTerms(searchTerms);
		const regex = buildHighlightRegex(terms);
		if (!regex) return;

		const root = $element[0];
		const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
			acceptNode: function (node) {
				if (!node.nodeValue || !node.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
				const parent = node.parentNode;
				if (!parent || !parent.closest) return NodeFilter.FILTER_ACCEPT;
				if (parent.closest('.log-line-actions') || parent.closest('.lineNumber')) {
					return NodeFilter.FILTER_REJECT;
				}
				return NodeFilter.FILTER_ACCEPT;
			}
		});

		const textNodes = [];
		while (walker.nextNode()) {
			textNodes.push(walker.currentNode);
		}

		textNodes.forEach(node => {
			const text = node.nodeValue;
			if (!regex.test(text)) {
				regex.lastIndex = 0;
				return;
			}
			regex.lastIndex = 0;
			const frag = document.createDocumentFragment();
			let lastIndex = 0;
			let match;

			while ((match = regex.exec(text)) !== null) {
				const start = match.index;
				const end = start + match[0].length;

				if (start > lastIndex) {
					frag.appendChild(document.createTextNode(text.slice(lastIndex, start)));
				}

				const span = document.createElement('span');
				span.className = 'highlighted-text';
				span.textContent = match[0];
				frag.appendChild(span);

				lastIndex = end;
			}

			if (lastIndex < text.length) {
				frag.appendChild(document.createTextNode(text.slice(lastIndex)));
			}

			node.parentNode.replaceChild(frag, node);
		});
	}

	function updateSearchTermsIndicator(searchTerms) {
		$('.search-terms-indicator').remove();

		if (!searchTerms || searchTerms.trim() === '') {
			return;
		}

		const terms = parseSearchTerms(searchTerms);

		if (terms.length > 0) {
			const termDescriptions = terms.map(term => {
				let description = `<strong>${term.value}</strong>`;
				if (term.type === 'exact') {
					description += ' <span style="color: #28a745; font-size: 10px;">(exact word)</span>';
				} else if (term.type === 'phrase') {
					description += ' <span style="color: #17a2b8; font-size: 10px;">(exact phrase)</span>';
				} else if (term.type === 'start') {
					description += ' <span style="color: #dc3545; font-size: 10px;">(line start)</span>';
				} else if (term.type === 'end') {
					description += ' <span style="color: #dc3545; font-size: 10px;">(line end)</span>';
				} else if (term.type === 'starts_with') {
					description += ' <span style="color: #fd7e14; font-size: 10px;">(starts with)</span>';
				} else if (term.type === 'ends_with') {
					description += ' <span style="color: #fd7e14; font-size: 10px;">(ends with)</span>';
				} else {
					description += ' <span style="color: #6c757d; font-size: 10px;">(partial)</span>';
				}
				return description;
			});

			const indicator = `<div class="search-terms-indicator">
								Searching for ALL terms: ${termDescriptions.join(', ')}
								<span class="search-terms-count">${terms.length} term${terms.length > 1 ? 's' : ''}</span>
								</div>`;

			$('#logSearchInput').closest('.input-group').after(indicator);
		}
	}

	function updateFilterChips() {
		const chips = [];

		if (logType && logType !== 'application') {
			chips.push({
				type: 'logType',
				label: `Type: ${logType === 'php_error' ? 'PHP Error' : 'Application'}`
			});
		}

		if (currentFilter && currentFilter !== 'all') {
			chips.push({
				type: 'level',
				label: `Level: ${currentFilter}`
			});
		}

		if (searchTerm && searchTerm.trim() !== '') {
			const terms = parseSearchTerms(searchTerm).map((term, index) => ({
				type: 'search',
				label: buildSearchToken(term),
				index
			}));
			chips.push(...terms);
		}

		if (chips.length === 0) {
			$('#logFilterChips').hide().html('');
			return;
		}

		const chipHtml = chips.map((chip) => `
			<span class="filter-chip" data-chip-type="${chip.type}" ${chip.type === 'search' ? `data-chip-index="${chip.index}"` : ''}>
				${chip.label}
				<button type="button" aria-label="Remove filter">×</button>
			</span>
		`).join('');

		const clearAllChip = `
			<span class="filter-chip filter-chip-clear" data-chip-type="clear">
				Clear All
				<button type="button" aria-label="Clear all filters">×</button>
			</span>
		`;

		$('#logFilterChips').html(chipHtml + clearAllChip).show();
	}

	function applyFilters() {
		updateSearchTermsIndicator(searchTerm);
		const logViewer = document.getElementById('logViewer');
		let visibleCount = 0;

		document.querySelectorAll('.logLine').forEach(function (logLine) {
			const logLevel = logLine.getAttribute('data-level') || 'info';
			const logText = logLine.textContent;
			let shouldShow = true;

			if (currentFilter !== 'all' && logLevel !== currentFilter) {
				shouldShow = false;
			}

			if (searchTerm && !searchAllTerms(logText, searchTerm)) {
				shouldShow = false;
			}

			if (shouldShow) {
				logLine.style.display = '';
				visibleCount++;

				if (searchTerm) {
					let originalHtml = logLine.getAttribute('data-original-html');
					if (!originalHtml) {
						originalHtml = logLine.innerHTML;
						logLine.setAttribute('data-original-html', originalHtml);
					}

					logLine.innerHTML = originalHtml;
					highlightSearchTermsInElement($(logLine), searchTerm);
				} else if (logLine.hasAttribute('data-original-html')) {
					logLine.innerHTML = logLine.getAttribute('data-original-html');
				}
			} else {
				logLine.style.display = 'none';
			}
		});

		const existingNoMatchMsg = document.getElementById('no-matches');
		if (existingNoMatchMsg) {
			existingNoMatchMsg.remove();
		}

		if (visibleCount === 0 && allLoadedLogs.length > 0) {
			logViewer.insertAdjacentHTML('beforeend',
				`<div class="error" id="no-matches">No logs match your filters. Try clearing filters or adjusting search terms.</div>`);
		}

		updateLogSummary(visibleCount);
		updateFilterChips();
	}

	function resetAndLoadLogs() {
		start = 0;
		loading = false;
		hasMoreLogs = true;
		allLoadedLogs = [];
		estimatedLines = null;
		fileSizeBytes = null;
		$('#logViewer').html('');
		$('#performanceInfo').hide();
		$('#fileSizeWarning').hide();
		loadLogs();
	}

	async function jumpToLine() {
		const rawValue = $('#jumpToLineInput').val();
		const lineNumber = parseInt(rawValue, 10);

		if (!lineNumber || lineNumber < 1) {
			toast.info("<?= _translate("Enter a valid line number", true); ?>");
			return;
		}

		let $target = $('#logViewer .logLine[data-linenumber="' + lineNumber + '"]').first();
		if ($target.length === 0 && hasMoreLogs) {
			toast.info("<?= _translate("Loading more logs to find that line...", true); ?>");
			const maxAttempts = 20;

			for (let attempt = 0; attempt < maxAttempts; attempt++) {
				if (loading) {
					await sleep(200);
					continue;
				}

				const loaded = await loadLogsAsync();
				if (!loaded) break;

				$target = $('#logViewer .logLine[data-linenumber="' + lineNumber + '"]').first();
				if ($target.length > 0) {
					break;
				}
			}
		}

		if ($target.length === 0) {
			toast.info("<?= _translate("Line not loaded yet. Try again after more logs load.", true); ?>");
			return;
		}

		$('html, body').animate({ scrollTop: $target.offset().top - 80 }, 200);
		$target.addClass('logLine-highlight');
		setTimeout(() => $target.removeClass('logLine-highlight'), 1200);
	}

	function updateLogSummary(visibleCount) {
		const totalLoaded = allLoadedLogs.length;
		const activeFilters = [];
		const estimatedText = estimatedLines ? ` / ${estimatedLines.toLocaleString()} est.` : '';

		if (logType) {
			activeFilters.push(`Type: ${logType === 'php_error' ? 'PHP Error' : 'Application'}`);
		}

		if (currentFilter && currentFilter !== 'all') {
			activeFilters.push(`Level: ${currentFilter}`);
		}

		if (searchTerm && searchTerm.trim() !== '') {
			activeFilters.push(`Search: "${searchTerm.trim()}"`);
		}

		const filtersText = activeFilters.length > 0 ? activeFilters.join(' · ') : 'None';

		const summaryHtml = `
			<strong>Loaded:</strong> ${totalLoaded.toLocaleString()}${estimatedText}
			<span class="summary-sep">|</span>
			<strong>Visible:</strong> ${visibleCount.toLocaleString()}
			<span class="summary-sep">|</span>
			<strong>Filters:</strong> ${filtersText}
		`;

		$('#logSummary').html(summaryHtml).show();
	}

	function saveViewerState() {
		const state = {
			logType,
			currentFilter,
			searchTerm,
			date: $('#userDate').val()
		};

		try {
			localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
		} catch (e) {
			// Non-critical: ignore storage failures (e.g., private mode)
		}
	}

	function loadViewerState() {
		try {
			const raw = localStorage.getItem(STORAGE_KEY);
			if (!raw) return;

			const state = JSON.parse(raw);
			if (!state || typeof state !== 'object') return;

			if (state.date) {
				$('#userDate').val(state.date);
			}
			if (state.searchTerm) {
				$('#logSearchInput').val(state.searchTerm);
				searchTerm = state.searchTerm;
			}
			if (state.currentFilter) {
				currentFilter = state.currentFilter;
				$('#logLevelFilters button').removeClass('active');
				$('#logLevelFilters button[data-level="' + currentFilter + '"]').addClass('active');
			}
			if (state.logType) {
				logType = state.logType;
				if (logType === 'php_error') {
					$('#logTypePhp').addClass('active');
					$('#logTypeApplication').removeClass('active');
				} else {
					$('#logTypeApplication').addClass('active');
					$('#logTypePhp').removeClass('active');
				}
			} else {
				$('#logTypeApplication').addClass('active');
			}
		} catch (e) {
			// Non-critical: ignore parse/storage errors
		}
	}

	function clearFilters() {
		searchTerm = '';
		currentFilter = 'all';
		$('#logSearchInput').val('');
		$('#logLevelFilters button').removeClass('active');
		$('#logLevelFilters button[data-level="all"]').addClass('active');
		applyFilters();
		saveViewerState();
	}

	function removeSearchTermAtIndex(index) {
		if (Number.isNaN(index)) return;
		const terms = parseSearchTerms(searchTerm);
		if (!terms[index]) return;

		terms.splice(index, 1);
		const rebuilt = terms.map(buildSearchToken).join(' ');
		searchTerm = rebuilt;
		$('#logSearchInput').val(rebuilt);
		applyFilters();
		saveViewerState();
	}

	// Optimized log loading function
	function loadLogs() {
		loadLogsAsync();
	}

	function loadLogsAsync() {
		return new Promise((resolve) => {
			if (loading || !hasMoreLogs) {
				resolve(false);
				return;
			}

			loading = true;
			$('#logViewer').show();

			if (start === 0) {
				showLoadingIndicator();
			} else {
				$('.loading').remove();
				$('#logViewer').append('<div class="loading">Loading more...</div>');
			}

			// Use AJAX with better error handling
			$.ajax({
				url: '/admin/monitoring/get-log-files.php',
				data: {
					date: $('#userDate').val(),
					start: start,
					log_type: logType,
					search: searchTerm
				},
				timeout: 60000, // 60 second timeout
				success: function (data) {
					$('.loading').remove();
					hideLoadingIndicator();

					// Parse performance info if available - more robust approach
					try {
						var performanceInfoStart = data.indexOf('<!-- PERFORMANCE_INFO: ');
						var performanceInfoEnd = data.indexOf(' -->', performanceInfoStart);

						if (performanceInfoStart !== -1 && performanceInfoEnd !== -1) {
							var performanceInfoStr = data.substring(
								performanceInfoStart + 23, // Length of '<!-- PERFORMANCE_INFO: '
								performanceInfoEnd
							);

							if (performanceInfoStr && performanceInfoStr.trim()) {
								var perfInfo = JSON.parse(performanceInfoStr.trim());
								if (perfInfo && typeof perfInfo === 'object') {
									updatePerformanceInfo(
										perfInfo.fileSize || 0,
										perfInfo.estimatedLines || 0,
										perfInfo.mode || 'standard'
									);
								}
							}
						}
					} catch (e) {
						console.warn('Could not parse performance info:', e);
						// Continue without performance info - not critical
					}

					if (data.includes('No more logs')) {
						hasMoreLogs = false;
					}

					if (data.trim() === '' || data.replace(/<!-- PERFORMANCE_INFO:.*?-->/g, '').trim() === '') {
						hasMoreLogs = false;
						if (start === 0) {
							$('#logViewer').html('<div class="logLine">No logs found for this date. Try a different date or adjust filters.</div>');
						} else {
							$('#logViewer').append('<div class="logLine">No more logs. Try a different date or adjust filters.</div>');
						}
					} else {
						// Use requestAnimationFrame for smooth UI updates
						requestAnimationFrame(() => {
							appendLogsToViewer(data);
						});
					}

					loading = false;
					resolve(true);
				},
				error: function (xhr, status, error) {
					$('.loading').remove();
					hideLoadingIndicator();

					if (status === 'timeout') {
						$('#logViewer').append('<div class="error">Request timed out. The log file is very large. Try using search filters to narrow results.</div>');
					} else {
						if (start === 0) {
							$('#logViewer').html('<div class="error">Error loading logs. The file might be very large or corrupted.</div>');
						} else {
							$('#logViewer').append('<div class="error">Error loading more logs.</div>');
						}
					}
					loading = false;
					resolve(false);
				}
			});
		});
	}

	function appendLogsToViewer(data) {
		// Remove performance info from display data
		const cleanData = data.replace(/<!-- PERFORMANCE_INFO:.*?-->/g, '');
		$('#logViewer').append(cleanData);

		const parser = new DOMParser();
		const htmlDoc = parser.parseFromString(cleanData, 'text/html');
		const logLines = htmlDoc.querySelectorAll('.logLine');

		let processedCount = 0;

		// Process logs in batches to avoid blocking UI
		function processBatch() {
			const batchSize = 15;
			const endIndex = Math.min(processedCount + batchSize, logLines.length);

			for (let i = processedCount; i < endIndex; i++) {
				const line = logLines[i];
				const lineInDOM = $('#logViewer .logLine[data-linenumber="' + line.getAttribute('data-linenumber') + '"]').last();

				if (lineInDOM.length === 0) continue;

				const lineText = lineInDOM.text();
				const lineNum = lineInDOM.attr('data-linenumber');
				const logLevel = detectLogLevel(lineText);

				allLoadedLogs.push({
					lineNumber: lineNum,
					text: lineText,
					level: logLevel
				});

				lineInDOM.attr('data-level', logLevel);
				lineInDOM.addClass(`log-${logLevel}`);

				const formattedContent = formatLogLine(lineInDOM.html());
				lineInDOM.html(formattedContent);
				lineInDOM.append(`
					<div class="log-line-actions">
						<button class="log-copy-btn" type="button" title="Copy line" aria-label="Copy line">
							<i class="fa fa-clipboard"></i>
						</button>
					</div>
				`);
				lineInDOM.attr('data-original-html', lineInDOM.html());
			}

			processedCount = endIndex;

			if (processedCount < logLines.length) {
				requestAnimationFrame(processBatch);
			} else {
				if (logLines.length === 0) {
					hasMoreLogs = false;
					$('#logViewer').append('<div class="logLine">No more logs.</div>');
				} else {
					start += logLines.length;
					applyFilters();
				}
			}
		}

		processBatch();
	}

	function exportLogFile() {
		const date = $('#userDate').val();
		const currentFilter = $('#logLevelFilters .active').data('level') || 'all';
		const searchTerm = $('#logSearchInput').val() || '';

		let formattedDate = date;
		if (date) {
			formattedDate = date.replace(/[\/:*?"<>|]/g, '-');
		}

		toast.info("<?= _translate("Preparing log export...", true); ?>");

		$.ajax({
			url: '/admin/monitoring/get-log-files.php',
			data: {
				date: date,
				log_type: logType,
				export_format: 'raw',
				search: searchTerm,
				level: currentFilter
			},
			success: function (data) {
				const currentDateTime = new Date();

				const formattedDateTime =
					padZero(currentDateTime.getDate()) + '-' +
					padZero(currentDateTime.getMonth() + 1) + '-' +
					currentDateTime.getFullYear() + '-' +
					padZero(currentDateTime.getHours()) + '-' +
					padZero(currentDateTime.getMinutes()) + '-' +
					padZero(currentDateTime.getSeconds());

				const containsHtml = /<[a-z][\s\S]*>/i.test(data);

				const fileExtension = containsHtml ? 'html' : 'txt';
				const contentType = containsHtml ? 'text/html' : 'text/plain';

				const filename = `logs-${formattedDate}-${formattedDateTime}.${fileExtension}`;

				if (containsHtml) {
					const htmlContent = `<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Log Export - ${formattedDate}</title>
	<style>
		body { font-family: Arial, sans-serif; padding: 20px; }
		.logLine { margin-bottom: 20px; border-left: 3px solid #4CAF50; padding: 10px; background-color: #f9f9f9; }
		.log-error { border-left-color: #dc3545; }
		.log-warning { border-left-color: #ffc107; }
		.log-info { border-left-color: #17a2b8; }
		.log-debug { border-left-color: #6c757d; }
		.lineNumber { color: #999; font-weight: bold; margin-right: 10px; }
		span[style*="e83e8c"] { background-color: rgba(232, 62, 140, 0.1); padding: 2px 5px; border-radius: 3px; }
	</style>
</head>
<body>
	<h1>Log Export - ${formattedDate}</h1>
	<p>Exported on: ${currentDateTime.toLocaleString()}</p>
	<div class="log-container">
		${data}
	</div>
</body>
</html>`;
					downloadFile(htmlContent, filename, contentType);
				} else {
					downloadFile(data, filename, contentType);
				}
			},
			error: function () {
				toast.error("<?= _translate("Error exporting log", true); ?>");
			}
		});
	}

	function downloadFile(content, fileName, contentType) {
		const a = document.createElement('a');
		const file = new Blob([content], {
			type: contentType
		});
		a.href = URL.createObjectURL(file);
		a.download = fileName;
		a.click();
		URL.revokeObjectURL(a.href);
	}

	function viewPhpErrorLogs() {
		logType = 'php_error';
		$('#logTypePhp').addClass('active');
		$('#logTypeApplication').removeClass('active');
		saveViewerState();
		resetAndLoadLogs();
	}

	function viewApplicationLogs() {
		logType = 'application';
		$('#logTypeApplication').addClass('active');
		$('#logTypePhp').removeClass('active');
		saveViewerState();
		resetAndLoadLogs();
	}

	function addSearchHelp() {
		const helpIcon = `<i class="fa fa-question-circle search-help-icon"
						 title="Search Syntax:&#10;• word - partial match&#10;• +word - exact word match&#10;• ^word - word at line start&#10;• word$ - word at line end&#10;• word* - starts with word&#10;• *word - ends with word&#10;• &quot;exact phrase&quot; - exact phrase match&#10;• Mix examples: +request vl* *xml"></i>`;

		$('#logSearchInput').closest('.col-md-8').addClass('search-input-container').append(helpIcon);
	}

	function addSearchExamples() {
		const searchExamples = `
	<div class="search-examples" id="searchExamples">
		<h6>Search Syntax Examples:</h6>
		<ul>
			<li><code>+request</code> - exact word "request" (not "requesthandler")</li>
			<li><code>^error</code> - lines starting with "error"</li>
			<li><code>failed$</code> - lines ending with "failed"</li>
			<li><code>vl*</code> - starts with "vl" (matches "vlsm", "vlan")</li>
			<li><code>*vl</code> - ends with "vl" (matches "xml", "html")</li>
			<li><code>"error message"</code> - exact phrase "error message"</li>
			<li><code>+request ^error vl*</code> - combine multiple patterns</li>
		</ul>
	</div>
`;

		$('#logSearchInput').closest('.input-group').after(searchExamples);

		$(document).on('click', '.search-help-icon', function () {
			$('#searchExamples').toggleClass('show');
		});
	}

	// Optimized search with debouncing
	const optimizedSearch = debounce(function () {
		searchTerm = $('#logSearchInput').val();

		if (searchTerm.length > 100) {
			toast.info("<?= _translate("Long search terms may impact performance", true); ?>");
		}

		applyFilters();
		saveViewerState();
	}, SEARCH_DEBOUNCE_TIME);

	// Virtual scrolling for better performance
	function initVirtualScrolling() {
		let ticking = false;

		$(window).on('scroll', function () {
			if (!ticking) {
				requestAnimationFrame(function () {
					if ($(window).scrollTop() + $(window).height() > $(document).height() - 200 && hasMoreLogs && !loading) {
						loadLogs();
					}
					ticking = false;
				});
				ticking = true;
			}
		});
	}

	$(document).ready(function () {
		$('#logSearchInput').on('input', optimizedSearch);

		$('.date').datepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
			maxDate: new Date(),
			yearRange: '<?= (date('Y') - 100); ?>:<?= date('Y'); ?>'
		});

		$('[data-toggle="tooltip"]').tooltip();

		$('#viewLogButton').click(function () {
			showLoadingIndicator();
			saveViewerState();
			resetAndLoadLogs();
		});

		$('#searchLogsButton').on('click', function () {
			clearTimeout(searchTimeout);
			searchTerm = $('#logSearchInput').val();
			applyFilters();
			saveViewerState();
		});

		$('#logSearchInput').on('keydown', function (e) {
			if (e.keyCode === 13) {
				clearTimeout(searchTimeout);
				searchTerm = $('#logSearchInput').val();
				applyFilters();
				saveViewerState();
			}
		});

		$('#logLevelFilters button').click(function () {
			$('#logLevelFilters button').removeClass('active');
			$(this).addClass('active');
			currentFilter = $(this).data('level');
			applyFilters();
			saveViewerState();
		});

		$('#exportTxtButton').click(exportLogFile);
		$('#clearFiltersButton').click(clearFilters);
		$('#jumpToLineButton').click(jumpToLine);

		$('#jumpToLineInput').on('keydown', function (e) {
			if (e.keyCode === 13) {
				jumpToLine();
			}
		});

		$(document).on('click', '.log-copy-btn', function (e) {
			e.stopPropagation();
			const $line = $(this).closest('.logLine');
			const lineNumber = $line.attr('data-linenumber') || '';
			copyToClipboard(getCleanLogHtml($line), lineNumber);
		});

		$(document).on('keydown', function (e) {
			if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
				e.preventDefault();
				$('#logSearchInput').focus().select();
			}

			if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'l') {
				e.preventDefault();
				clearFilters();
			}

			if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'g') {
				e.preventDefault();
				$('#jumpToLineInput').focus().select();
			}
		});

		$(document).on('click', '#logFilterChips .filter-chip button', function () {
			const $chip = $(this).closest('.filter-chip');
			const chipType = $chip.data('chip-type');

			if (chipType === 'clear') {
				clearFilters();
				return;
			}

			if (chipType === 'logType') {
				viewApplicationLogs();
				return;
			}

			if (chipType === 'level') {
				currentFilter = 'all';
				$('#logLevelFilters button').removeClass('active');
				$('#logLevelFilters button[data-level="all"]').addClass('active');
				applyFilters();
				saveViewerState();
				return;
			}

			if (chipType === 'search') {
				const chipIndex = parseInt($chip.data('chip-index'), 10);
				removeSearchTermAtIndex(chipIndex);
			}
		});

		$('#userDate').on('change', function () {
			saveViewerState();
			resetAndLoadLogs();
		});

		addSearchHelp();
		addSearchExamples();
		initVirtualScrolling();

		loadViewerState();
		resetAndLoadLogs();
	});
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
