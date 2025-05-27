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
	/* Stack trace line numbers */
	.logLine br {
		line-height: 1.8;
		margin-top: 5px;
	}

	/* Highlight important stack trace elements */
	.logLine span[style*="e83e8c"] {
		background-color: rgba(232, 62, 140, 0.1);
		padding: 2px 5px;
		border-radius: 3px;
		margin-right: 5px;
	}

	/* Enhanced Log Viewer Styles */
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
		/* Top Right Bottom Left */
		background-color:rgb(255, 255, 255);
		border: 2px solid #f1f1f1;
		border-left: 3px solid #4CAF50;
		font-family: 'Courier New', Courier, monospace;
		margin-bottom: 2em;
		cursor: pointer;
		transition: background-color 0.3s;
		position: relative;
		white-space: pre-wrap;
		/* Preserve whitespace but wrap */
		word-break: break-word;
		/* Break long words if needed */
		text-indent: 0;
		/* Ensure no text indentation */
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
		/* Put above content */
		font-size: 7em;
		color: rgba(0, 0, 0, 0.25);
		pointer-events: none;
		/* Make sure it doesn't interfere with text selection */
	}

	.logLine:hover .lineNumber {
		color: rgba(0, 0, 0, 0.45);
	}

	/* Log level highlighting */
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

	/* Loading and error states */
	.loading,
	.error {
		color: #007bff;
		text-align: center;
		padding: 20px;
		font-style: italic;
	}

	/* Search bar styling */
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

	/* Match highlighting */
	.highlighted-text {
		background-color: #ffff00;
		padding: 2px;
		border-radius: 2px;
	}

	/* Tooltip for copied text */
	.toastify {
		border-radius: 4px !important;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
	}

	/* Stack trace formatting */
	.stack-line {
		color: #000;
		padding-left: 20px;
		font-size: 14px;
	}

	/* Responsive adjustments */
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
</style>


<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1> <em class="fa-solid fa-file-lines"></em> <?php echo _translate("Log File Viewer"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/system-admin/edit-config/index.php"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("Manage Log File Viewer"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<!-- Log Controls Section -->
					<div class="box-header">
						<div class="log-controls">
							<!-- Date Selection -->
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

							<!-- Log Type Buttons -->
							<div class="log-actions">
								<button class="btn btn-info btn-sm" onclick="viewApplicationLogs()">
									<span><?php echo _translate("View System Error Logs"); ?></span>
								</button>
								<button class="btn btn-warning btn-sm" onclick="viewPhpErrorLogs()">
									<span><?php echo _translate("View PHP Error Logs"); ?></span>
								</button>
							</div>
						</div>

						<!-- Server Time Display -->
						<div style="text-align: right; margin-top: 15px;">
							<strong><?= _translate("Current Server Date and Time"); ?> : </strong>
							<?= DateUtility::humanReadableDateFormat(DateUtility::getCurrentDateTime() , includeTime: true, withSeconds: true); ?>
						</div>
					</div>

					<!-- Search Box and Export Options -->
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

						<!-- Log Level Filter -->
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

						<!-- Log Viewer Container -->
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
	// Enhanced Log Viewer JavaScript
	let start = 0; // Starting index for logs
	let loading = false; // Flag to prevent multiple simultaneous AJAX calls
	let hasMoreLogs = true; // Flag to check if there are more logs to load
	let logType = 'application'; // Default log type is 'application'
	let currentFilter = 'all'; // Current log level filter
	let searchTerm = ''; // Current search term
	let allLoadedLogs = []; // Array to store all loaded logs for filtering and exporting

	// Helper function to pad single-digit numbers with leading zero
	function padZero(num) {
		return num < 10 ? '0' + num : num;
	}

	// Function to copy log line to clipboard
	function copyToClipboard(text, lineNumber) {
		// Clean up text by removing HTML tags
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
				// Fallback for older browsers
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

	// Function to detect log level from log text
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
		return 'info'; // Default to info
	}

	// Function to highlight search term in log text
	function highlightSearchTerm(text, term) {
		if (!term || term === '') {
			return text;
		}

		const regex = new RegExp(term, 'gi');
		return text.replace(regex, match => `<span class="highlighted-text">${match}</span>`);
	}

	// Function to format log line with stack trace styling
	function formatLogLine(logText) {
		// Check if the line is part of a stack trace
		if (logText.trim().startsWith('#') || logText.includes(' at ') || logText.includes('/vendor/') || logText.includes('/Users/')) {
			return `<span class="stack-line">${logText}</span>`;
		}
		return logText;
	}

	// Function to apply filters to logs
	function applyFilters() {
		const logViewer = document.getElementById('logViewer');
		let visibleCount = 0;

		document.querySelectorAll('.logLine').forEach(function(logLine) {
			const logLevel = logLine.getAttribute('data-level') || 'info';
			const logText = logLine.textContent;
			let shouldShow = true;

			// Apply level filter
			if (currentFilter !== 'all' && logLevel !== currentFilter) {
				shouldShow = false;
			}

			// Apply search filter
			if (searchTerm && !logText.toLowerCase().includes(searchTerm.toLowerCase())) {
				shouldShow = false;
			}

			// Set visibility
			if (shouldShow) {
				logLine.style.display = '';
				visibleCount++;

				// Update highlighted text if search term exists
				if (searchTerm) {
					const originalHtml = logLine.getAttribute('data-original-html') || logLine.innerHTML;
					logLine.setAttribute('data-original-html', originalHtml);
					logLine.innerHTML = highlightSearchTerm(originalHtml, searchTerm);
				} else if (logLine.hasAttribute('data-original-html')) {
					// Restore original HTML if no search term
					logLine.innerHTML = logLine.getAttribute('data-original-html');
				}
			} else {
				logLine.style.display = 'none';
			}
		});

		// Show message if no logs match filters
		if (visibleCount === 0 && allLoadedLogs.length > 0) {
			logViewer.insertAdjacentHTML('beforeend',
				`<div class="error" id="no-matches">${_translate('No logs match your filters.')}</div>`);
		} else {
			const noMatchesMsg = document.getElementById('no-matches');
			if (noMatchesMsg) {
				noMatchesMsg.remove();
			}
		}
	}

	// Function to reset logs and load them (starting from the first line)
	function resetAndLoadLogs() {
		start = 0; // Reset start index
		loading = false;
		hasMoreLogs = true; // Reset log continuation flag
		allLoadedLogs = []; // Clear stored logs
		$('#logViewer').html(''); // Clear existing logs
		loadLogs(); // Load initial logs
	}

	// Function to export logs as original raw TXT
	// Function to export logs as HTML or raw TXT
	function exportLogFile() {
		// We need to request the original log content from the server
		const date = $('#userDate').val();
		const currentFilter = $('#logLevelFilters .active').data('level') || 'all';
		const searchTerm = $('#logSearchInput').val() || '';

		// Format the date for the filename - ensure we have a valid date string
		// Convert the datepicker format (dd-M-yy) to a filename-friendly format (dd-MMM-yyyy)
		let formattedDate = date;
		if (date) {
			// Replace any spaces with hyphens and ensure no invalid filename characters
			formattedDate = date.replace(/[\/:*?"<>|]/g, '-');
		}

		// Show loading indicator
		Toastify({
			text: "Preparing log export...",
			duration: 3000,
			gravity: "top",
			position: "right",
			backgroundColor: "#17a2b8",
		}).showToast();

		// Request the original logs
		$.ajax({
			url: '/admin/monitoring/get-log-files.php',
			data: {
				date: date,
				log_type: logType,
				export_format: 'raw', // Note: even though we named the parameter 'raw', the response appears to include HTML
				search: searchTerm,
				level: currentFilter
			},
			success: function(data) {
				// Create download link with properly formatted date and current timestamp
				const currentDateTime = new Date();

				// Format as dd-mm-yyyy-hh-ii-ss
				const formattedDateTime =
					padZero(currentDateTime.getDate()) + '-' +
					padZero(currentDateTime.getMonth() + 1) + '-' +
					currentDateTime.getFullYear() + '-' +
					padZero(currentDateTime.getHours()) + '-' +
					padZero(currentDateTime.getMinutes()) + '-' +
					padZero(currentDateTime.getSeconds());

				// Check if the data contains HTML tags
				const containsHtml = /<[a-z][\s\S]*>/i.test(data);

				// Set file extension and content type based on content
				const fileExtension = containsHtml ? 'html' : 'txt';
				const contentType = containsHtml ? 'text/html' : 'text/plain';

				const filename = `logs-${formattedDate}-${formattedDateTime}.${fileExtension}`;

				// If it's HTML content, wrap it in basic HTML structure for better viewing
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

	// Helper function to download file
	function downloadFile(content, fileName, contentType) {
		alert('Downloading file: ' + fileName);
		const a = document.createElement('a');
		const file = new Blob([content], {
			type: contentType
		});
		a.href = URL.createObjectURL(file);
		a.download = fileName;
		a.click();
		URL.revokeObjectURL(a.href);
	}

	// Function to load logs (application or PHP error)
	function loadLogs() {
		if (!loading && hasMoreLogs) {
			loading = true;
			$('#logViewer').show();
			// Remove existing "Loading..." message if exists
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
					$('.loading').remove(); // Remove loading indicator

					// If "No more logs" message is found, stop further requests
					if (data.includes('No more logs')) {
						hasMoreLogs = false;
						$('#logViewer').append(data); // Append "No more logs" message
						return;
					}

					if (data.trim() === '') {
						hasMoreLogs = false;
						if (start === 0) {
							$('#logViewer').html('<div class="logLine">No logs found.</div>'); // Show no logs if first fetch returns none
						} else {
							$('#logViewer').append('<div class="logLine">No more logs.</div>'); // Indicate no more logs
						}
					} else {
						// Append the data to the log viewer before processing
						$('#logViewer').append(data);

						// Now parse and process the HTML
						const parser = new DOMParser();
						const htmlDoc = parser.parseFromString(data, 'text/html');
						const logLines = htmlDoc.querySelectorAll('.logLine');

						// Process each log line
						logLines.forEach(function(line) {
							// Find the corresponding element in the actual DOM
							const lineInDOM = $('#logViewer .logLine[data-linenumber="' + line.getAttribute('data-linenumber') + '"]').last();
							if (lineInDOM.length === 0) return;

							const lineText = lineInDOM.text();
							const lineNum = lineInDOM.attr('data-linenumber');
							const logLevel = detectLogLevel(lineText);

							// Store log in array for filtering
							allLoadedLogs.push({
								lineNumber: lineNum,
								text: lineText,
								level: logLevel
							});

							// Add log level as a data attribute and class
							lineInDOM.attr('data-level', logLevel);
							lineInDOM.addClass(`log-${logLevel}`);

							// Apply formatting to the content
							const formattedContent = formatLogLine(lineInDOM.html());
							lineInDOM.html(formattedContent);
						});

						if (logLines.length === 0) {
							hasMoreLogs = false; // No more logs to load
							$('#logViewer').append('<div class="logLine">No more logs.</div>');
						} else {
							start += logLines.length; // Update the start index based on actual lines returned
							applyFilters(); // Apply current filters to new logs
						}
					}
					loading = false;
				},
				error: function() {
					$('.loading').remove();
					if (start === 0) {
						$('#logViewer').html('<div class="error">Error loading logs.</div>'); // Show error on first fetch failure
					} else {
						$('#logViewer').append('<div class="error">Error loading more logs.</div>'); // Show error on subsequent fetch failure
					}
					loading = false;
				}
			});
		}
	}

	// Function to view PHP error logs
	function viewPhpErrorLogs() {
		logType = 'php_error'; // Switch log type to PHP error log
		resetAndLoadLogs(); // Reset and load PHP error logs
	}

	// Function to view application logs
	function viewApplicationLogs() {
		logType = 'application'; // Switch log type to application log
		resetAndLoadLogs(); // Reset and load application logs
	}

	$(document).ready(function() {
		// Initialize datepicker
		$('.date').datepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
			maxDate: new Date(),
			yearRange: '<?= (date('Y') - 100); ?>:<?= date('Y'); ?>'
		});

		// Initialize tooltip
		$('[data-toggle="tooltip"]').tooltip();

		// Bind the button to reset logs and load new ones for application logs
		$('#viewLogButton').click(function() {
			viewApplicationLogs();
		});

		// Bind search functionality
		$('#searchLogsButton, #logSearchInput').on('click keyup', function(e) {
			if (e.type === 'click' || e.keyCode === 13) { // Enter key
				searchTerm = $('#logSearchInput').val();
				applyFilters();
			}
		});

		// Bind log level filter buttons
		$('#logLevelFilters button').click(function() {
			$('#logLevelFilters button').removeClass('active');
			$(this).addClass('active');
			currentFilter = $(this).data('level');
			applyFilters();
		});

		// Bind export buttons
		$('#exportTxtButton').click(exportLogFile);

		// Load initial application logs on page load
		viewApplicationLogs();

		// Setup scroll event for infinite scrolling
		$(window).scroll(function() {
			if ($(window).scrollTop() + $(window).height() > $(document).height() - 100 && hasMoreLogs) { // If close to bottom
				loadLogs();
			}
		});
	});
</script>


<?php
require_once APPLICATION_PATH . '/footer.php';
