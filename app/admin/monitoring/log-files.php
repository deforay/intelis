<?php


use App\Utilities\DateUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$title = _translate("Log File Viewer") . " - " . _translate("Admin");

require_once APPLICATION_PATH . '/header.php';

?>
<link rel="stylesheet" type="text/css" href="/assets/css/toastify.min.css">
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
		padding: 11px 15px 11px 25px;
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

	.logLine:hover {
		background-color: #e8f0fe;
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

	.toastify {
		border-radius: 4px !important;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
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
</style>


<div class="content-wrapper">
	<section class="content-header">
		<h1> <em class="fa-solid fa-file-lines"></em> <?php echo _translate("Log File Viewer"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/system-admin/edit-config/index.php"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
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
												value="<?= DateUtility::humanReadableDateFormat(DateUtility::getCurrentDateTime()); ?>" style="width:220px;background:#fff;" />
										</td>
										<td>
											<button id="viewLogButton" class="btn btn-primary btn-sm">
												<span><?php echo _translate("Search"); ?></span>
											</button>
										</td>
										<td>
											<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location">
												<span><?php echo _translate("Clear Search"); ?></span>
											</button>
										</td>
									</tr>
								</table>
							</div>

							<div class="log-actions">
								<button class="btn btn-info btn-sm" onclick="viewApplicationLogs()">
									<span><?php echo _translate("View System Error Logs"); ?></span>
								</button>
								<button class="btn btn-warning btn-sm" onclick="viewPhpErrorLogs()">
									<span><?php echo _translate("View PHP Error Logs"); ?></span>
								</button>
							</div>
						</div>

						<div style="text-align: right; margin-top: 15px;">
							<strong><?= _translate("Current Server Date and Time"); ?> : </strong>
							<?= DateUtility::humanReadableDateFormat(DateUtility::getCurrentDateTime(), includeTime: true, withSeconds: true); ?>
						</div>
					</div>

					<div class="box-body">
						<div class="row" style="margin-bottom: 15px;">
							<div class="col-md-8">
								<div class="input-group">
									<input type="text" id="logSearchInput" class="form-control"
										placeholder="<?php echo _translate("Search in logs..."); ?>">
									<span class="input-group-btn">
										<button id="searchLogsButton" class="btn btn-default" type="button">
											<i class="fa fa-search"></i>
										</button>
									</span>
								</div>
							</div>
							<div class="col-md-4">
								<div class="btn-group pull-right">
									<button id="exportTxtButton" class="btn btn-default">
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

						<div class="logViewer" id="logViewer" style="white-space: pre-wrap;"></div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>


<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript" src="/assets/js/toastify.js"></script>
<script>
	let start = 0;
	let loading = false;
	let hasMoreLogs = true;
	let logType = 'application';
	let currentFilter = 'all';
	let searchTerm = '';
	let allLoadedLogs = [];
	let searchTimeout;

	function padZero(num) {
		return num < 10 ? '0' + num : num;
	}

	function copyToClipboard(text, lineNumber) {
		const tempDiv = document.createElement('div');
		tempDiv.innerHTML = text;
		const cleanText = tempDiv.textContent || tempDiv.innerText || '';

		navigator.clipboard.writeText(cleanText)
			.then(() => {
				Toastify({
					text: "Copied to clipboard - Line Number - " + lineNumber,
					duration: 3000,
					close: true,
					gravity: "top",
					position: "right",
					backgroundColor: "#4CAF50",
				}).showToast();
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

				Toastify({
					text: "Copied to clipboard - Line Number - " + lineNumber,
					duration: 3000,
					close: true,
					gravity: "top",
					position: "right",
					backgroundColor: "#4CAF50",
				}).showToast();
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
				}); // word*
			} else if (match[7]) {
				terms.push({
					type: 'ends_with',
					value: match[7]
				}); // *word
			} else if (match[8]) {
				terms.push({
					type: 'partial',
					value: match[8]
				});
			}
		}

		return terms.filter(term => term.value.length > 0);
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
				case 'start_boundary':
					return new RegExp(`\\b${escapeRegExp(term.value)}`, 'i').test(text);
				case 'end_boundary':
					return new RegExp(`${escapeRegExp(term.value)}\\b`, 'i').test(text);
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

	function applyFilters() {
		updateSearchTermsIndicator(searchTerm);
		const logViewer = document.getElementById('logViewer');
		let visibleCount = 0;

		document.querySelectorAll('.logLine').forEach(function(logLine) {
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

					logLine.innerHTML = highlightAllSearchTerms(originalHtml, searchTerm);
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
				`<div class="error" id="no-matches">No logs match your filters.</div>`);
		}
	}

	function resetAndLoadLogs() {
		start = 0;
		loading = false;
		hasMoreLogs = true;
		allLoadedLogs = [];
		$('#logViewer').html('');
		loadLogs();
	}

	function exportLogFile() {
		const date = $('#userDate').val();
		const currentFilter = $('#logLevelFilters .active').data('level') || 'all';
		const searchTerm = $('#logSearchInput').val() || '';

		let formattedDate = date;
		if (date) {
			formattedDate = date.replace(/[\/:*?"<>|]/g, '-');
		}

		Toastify({
			text: "Preparing log export...",
			duration: 3000,
			gravity: "top",
			position: "right",
			backgroundColor: "#17a2b8",
		}).showToast();

		$.ajax({
			url: '/admin/monitoring/get-log-files.php',
			data: {
				date: date,
				log_type: logType,
				export_format: 'raw',
				search: searchTerm,
				level: currentFilter
			},
			success: function(data) {
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
			error: function() {
				Toastify({
					text: "Error exporting logs",
					duration: 3000,
					gravity: "top",
					position: "right",
					backgroundColor: "#dc3545",
				}).showToast();
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

	function loadLogs() {
		if (!loading && hasMoreLogs) {
			loading = true;
			$('#logViewer').show();
			$('.loading').remove();
			$('#logViewer').append('<div class="loading">Loading...</div>');

			$.ajax({
				url: '/admin/monitoring/get-log-files.php',
				data: {
					date: $('#userDate').val(),
					start: start,
					log_type: logType,
					search: searchTerm
				},
				success: function(data) {
					$('.loading').remove();

					if (data.includes('No more logs')) {
						hasMoreLogs = false;
						$('#logViewer').append(data);
						return;
					}

					if (data.trim() === '') {
						hasMoreLogs = false;
						if (start === 0) {
							$('#logViewer').html('<div class="logLine">No logs found.</div>');
						} else {
							$('#logViewer').append('<div class="logLine">No more logs.</div>');
						}
					} else {
						$('#logViewer').append(data);

						const parser = new DOMParser();
						const htmlDoc = parser.parseFromString(data, 'text/html');
						const logLines = htmlDoc.querySelectorAll('.logLine');

						logLines.forEach(function(line) {
							const lineInDOM = $('#logViewer .logLine[data-linenumber="' + line.getAttribute('data-linenumber') + '"]').last();
							if (lineInDOM.length === 0) return;

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

							lineInDOM.attr('data-original-html', lineInDOM.html());
						});

						if (logLines.length === 0) {
							hasMoreLogs = false;
							$('#logViewer').append('<div class="logLine">No more logs.</div>');
						} else {
							start += logLines.length;
							applyFilters();
						}
					}
					loading = false;
				},
				error: function() {
					$('.loading').remove();
					if (start === 0) {
						$('#logViewer').html('<div class="error">Error loading logs.</div>');
					} else {
						$('#logViewer').append('<div class="error">Error loading more logs.</div>');
					}
					loading = false;
				}
			});
		}
	}

	function viewPhpErrorLogs() {
		logType = 'php_error';
		resetAndLoadLogs();
	}

	function viewApplicationLogs() {
		logType = 'application';
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
            <li><code>+request ^error vl%</code> - combine multiple patterns</li>
        </ul>
    </div>
`;

		$('#logSearchInput').closest('.input-group').after(searchExamples);

		$(document).on('click', '.search-help-icon', function() {
			$('#searchExamples').toggleClass('show');
		});
	}

	$(document).ready(function() {
		$('#logSearchInput').on('input', function() {
			clearTimeout(searchTimeout);

			const currentSearch = $(this).val();
			updateSearchTermsIndicator(currentSearch);

			searchTimeout = setTimeout(function() {
				searchTerm = $('#logSearchInput').val();
				applyFilters();
			}, 300);
		});

		$('.date').datepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
			maxDate: new Date(),
			yearRange: '<?= (date('Y') - 100); ?>:<?= date('Y'); ?>'
		});

		$('[data-toggle="tooltip"]').tooltip();

		$('#viewLogButton').click(function() {
			viewApplicationLogs();
		});

		$('#searchLogsButton').on('click', function() {
			clearTimeout(searchTimeout);
			searchTerm = $('#logSearchInput').val();
			applyFilters();
		});

		$('#logSearchInput').on('keydown', function(e) {
			if (e.keyCode === 13) {
				clearTimeout(searchTimeout);
				searchTerm = $('#logSearchInput').val();
				applyFilters();
			}
		});

		$('#logSearchInput').attr('placeholder', '+word for exact, "phrase" for exact phrase, word for partial');

		$('#logLevelFilters button').click(function() {
			$('#logLevelFilters button').removeClass('active');
			$(this).addClass('active');
			currentFilter = $(this).data('level');
			applyFilters();
		});

		$('#exportTxtButton').click(exportLogFile);

		addSearchHelp();
		addSearchExamples();

		viewApplicationLogs();

		$(window).scroll(function() {
			if ($(window).scrollTop() + $(window).height() > $(document).height() - 100 && hasMoreLogs) {
				loadLogs();
			}
		});
	});
</script>


<?php
require_once APPLICATION_PATH . '/footer.php';
