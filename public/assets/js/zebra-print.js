
// Zebra Printer Web Print Integration
// This script handles the interaction with Zebra printers using BrowserPrint API.
// It allows printing barcode labels with a specific format and handles printer status.
let currentZebraPrinter = null;
let available_printers = null;
let default_printer = null;
let selected_printer = null;
let default_mode = true;

// internal state to debounce transient offline
let _lastGoodStatus = null;
let _transientOfflineTimer = null;

function replaceAllSafe(str, search, replacement) {
	return String(str).split(String(search)).join(replacement == null ? '' : replacement);
}

function urldecode(str) {
	if (typeof str !== "string") return str;
	try {
		return decodeURIComponent(str.replace(/\+/g, ' '));
	} catch (error) {
		console.warn('URL decode error:', error);
		return str;
	}
}

function createSafeZebraPrinter(device) {
	const zebra = new Zebra.Printer(device);
	if (typeof zebra.onResponse === 'function') {
		const origOnResponse = zebra.onResponse.bind(zebra);
		zebra.onResponse = function (a, f) {
			if (!this.device_request_queue || this.device_request_queue.length === 0) {
				console.warn("Zebra.Printer.onResponse called with empty queue; skipping response", { a, f });
				return;
			}
			origOnResponse(a, f);
		};
	}
	return zebra;
}

function renderPrinterStatus(status) {
	const el = document.getElementById('printer_status');
	if (!el) return;

	// transient offline suppression: if status.offline && raw is empty, delay showing ❌ for 300ms
	const isActuallyOffline = status && status.offline;
	const rawEmpty = !status.raw || String(status.raw).trim() === "";

	if (isActuallyOffline && rawEmpty) {
		// treat as transient; show “checking...” if no last good status
		if (_transientOfflineTimer) {
			clearTimeout(_transientOfflineTimer);
		}
		el.textContent = '⏳ checking...';
		el.classList.remove('online', 'offline');
		// after delay, if still offline+empty, then show cross
		_transientOfflineTimer = setTimeout(() => {
			// if newer good status arrived meanwhile, skip
			if (_lastGoodStatus && _lastGoodStatus.isPrinterReady && _lastGoodStatus.isPrinterReady()) {
				return;
			}
			el.textContent = '❌ Offline';
			el.classList.add('offline');
			el.classList.remove('online');
		}, 300);
		return;
	}

	// clear any pending transient timer
	if (_transientOfflineTimer) {
		clearTimeout(_transientOfflineTimer);
		_transientOfflineTimer = null;
	}

	const isReady = status && typeof status.isPrinterReady === 'function' && status.isPrinterReady();
	const symbol = isReady ? '✅' : '❌';
	const message = status && typeof status.getMessage === 'function' ? status.getMessage() : (isReady ? 'Ready' : 'Not Ready');

	el.textContent = `${symbol} ${message}`;
	el.classList.toggle('online', isReady);
	el.classList.toggle('offline', !isReady);

	if (isReady) {
		_lastGoodStatus = status;
	}
}

async function primePrinter() {
	if (!currentZebraPrinter) return;
	return new Promise(res => {
		currentZebraPrinter.getStatus(
			status => {
				console.log("🔌 Primed status:", status.getMessage(), status);
				renderPrinterStatus(status);
				res();
			},
			err => {
				console.warn("🔌 Prime status error (ignored):", err);
				res();
			}
		);
	});
}

async function waitUntilPrinterReady(maxTotal = 8000, interval = 500) {
	if (!currentZebraPrinter) {
		console.warn("No Zebra wrapper present; skipping ready check.");
		return;
	}

	const start = Date.now();
	while (true) {
		try {
			const status = await new Promise((resolve, reject) => {
				currentZebraPrinter.getStatus(resolve, reject);
			});
			console.log("✅ waitUntilPrinterReady status:", status.getMessage(), status);
			if (status.raw !== undefined) {
				console.log("Raw status (escaped):", String(status.raw).replace(/\r/g, "\\r").replace(/\n/g, "\\n"));
			}
			renderPrinterStatus(status);

			if (status.isPrinterReady && status.isPrinterReady()) {
				return status;
			}

			// Transient offline with empty raw: retry
			if (status.offline && (!status.raw || String(status.raw).trim() === "")) {
				console.warn("⚠️ Transient empty/offline status; retrying...", status);
			} else {
				// persistent not ready
				throw new Error("Printer not ready: " + status.getMessage());
			}
		} catch (e) {
			console.warn("waitUntilPrinterReady transient error:", e);
			// continue to retry until timeout
		}

		if (Date.now() - start >= maxTotal) {
			throw new Error("Timeout waiting for stable ready status (transient offline persisted).");
		}
		await new Promise(r => setTimeout(r, interval));
	}
}

function logPrinterDiagnostics() {
	return new Promise(async (resolve) => {
		if (!currentZebraPrinter) {
			console.warn("No currentZebraPrinter available for diagnostics.");
			return resolve();
		}

		// Status
		await new Promise(res => {
			currentZebraPrinter.getStatus(
				status => {
					console.log("✅ Printer status:", status.getMessage(), status);
					if (status.raw !== undefined) {
						console.log("Raw status (escaped):", String(status.raw).replace(/\r/g, "\\r").replace(/\n/g, "\\n"));
					}
					renderPrinterStatus(status);
					res();
				},
				err => {
					console.warn("⚠️ Status retrieval failed:", err);
					res();
				}
			);
		});

		// Configuration
		await new Promise(res => {
			currentZebraPrinter.getConfiguration(
				cfg => {
					console.log("✅ Parsed configuration:", cfg);
					res();
				},
				err => {
					console.warn("⚠️ Configuration error (expected in some cases):", err);
					if (selected_printer && typeof selected_printer.sendThenReadUntilStringReceived === 'function') {
						selected_printer.sendThenReadUntilStringReceived(
							"^XA^HH^XZ",
							resp => {
								console.log("🔍 Raw config fallback response:", resp);
								console.log("Char codes of raw response:", resp.split("").map(c => c.charCodeAt(0)));
								res();
							},
							err2 => {
								console.error("❌ Failed to get raw config fallback:", err2);
								res();
							},
							String.fromCharCode(3),
							1
						);
					} else {
						res();
					}
				}
			);
		});

		resolve();
	});
}

function setupWebPrint() {
	$('#printer_select').on('change', onPrinterSelected);
	default_mode = true;
	selected_printer = null;
	available_printers = null;
	default_printer = null;

	if (typeof BrowserPrint === 'undefined') {
		showErrorMessage("BrowserPrint core not available. Ensure the native client is installed and running.");
		return;
	}
	if (!window.Zebra || typeof Zebra.Printer !== 'function') {
		showErrorMessage("Zebra helper library failed to load.");
		return;
	}

	showLoading("Loading Printer Information...");

	BrowserPrint.getDefaultDevice('printer', function (printer) {
		default_printer = printer;
		if ((printer != null) && (printer.connection != undefined)) {
			setSelectedPrinter(printer);
			let printer_details = $('#printer_details');
			let selected_printer_div = $('#selected_printer');
			selected_printer_div.text("Using Default Printer: " + printer.name);
			hideLoading();
			printer_details.show();
			$('#print_form').show();
		}

		BrowserPrint.getLocalDevices(function (printers) {
			available_printers = printers;
			let sel = document.getElementById("printers");
			let printers_available = false;

			if (sel) {
				sel.innerHTML = "";
				if (printers != undefined) {
					for (let i = 0; i < printers.length; i++) {
						if (printers[i].connection == 'usb' && printers[i].uid) {
							let opt = document.createElement("option");
							opt.innerHTML = printers[i].connection + ": " + printers[i].uid;
							opt.value = printers[i].uid;
							sel.appendChild(opt);
							printers_available = true;
						}
					}
				}
			}

			if (!printers_available) {
				showErrorMessage("No compatible Zebra USB printers found! Please connect a Zebra printer and try again.");
				hideLoading();
				$('#print_form').hide();
			} else if (selected_printer == null) {
				default_mode = false;
				changePrinter();
				$('#print_form').show();
				hideLoading();
			}
		}, function (error) {
			console.error('Error getting local devices:', error);
			showErrorMessage("Error scanning for printers. Please try again.");
			hideLoading();
		}, 'printer');
	},
	function (error_response) {
		console.error('Error getting default printer:', error_response);
		showBrowserPrintNotFound();
	});
}

function showBrowserPrintNotFound() {
	let message = "An error occurred while attempting to connect to your Zebra Printer. " +
		"This could be due to:<br>" +
		"• Zebra Browser Print is not installed<br>" +
		"• Zebra Browser Print service is not running<br>" +
		"• Printer is not connected or powered on<br><br>" +
		"Please install Zebra Browser Print, ensure the service is running, and verify your printer connection.";
	showErrorMessage(message);
}

async function printBarcodeLabel(barcode, facility, patientART) {
	if (!selected_printer) {
		showErrorMessage("No printer selected. Please select a printer first.");
		return;
	}

	barcode = String(barcode || '').trim();
	if (!barcode) {
		showErrorMessage("Invalid barcode data. Please provide a valid barcode.");
		return;
	}

	if (typeof zebraFormat === 'undefined' || !zebraFormat) {
		showErrorMessage("Label format is not defined. Please ensure the format template is loaded.");
		return;
	}

	facility = urldecode(facility || '').trim();
	patientART = String(patientART || '').trim();

	showLoading("Printing...");

	await logPrinterDiagnostics();
	await primePrinter();

	try {
		await waitUntilPrinterReady();
	} catch (err) {
		printerError("Printer not ready or timed out: " + (err && err.toString ? err.toString() : err));
		return;
	}

	// small settle pause so internal state stabilizes
	await new Promise(r => setTimeout(r, 100));

	try {
		let strToPrint = replaceAllSafe(zebraFormat, "1234567", barcode);
		strToPrint = replaceAllSafe(strToPrint, "BARCODE", barcode);
		if (facility) {
			strToPrint = replaceAllSafe(strToPrint, "FACILITY", facility);
		}
		if (patientART) {
			strToPrint = replaceAllSafe(strToPrint, "PATIENTART", patientART);
		}

		console.log("zebraFormat raw:", zebraFormat);
		console.log("Barcode:", barcode);
		console.log("Facility:", facility);
		console.log("PatientART:", patientART);
		console.log("Final ZPL being sent:", strToPrint);
		if (strToPrint.includes("BARCODE") || strToPrint.includes("1234567")) {
			console.warn("Placeholder not replaced fully", { strToPrint, zebraFormat, barcode });
		}

		window.lastLabel = strToPrint;
		window.labelDebugHistory = window.labelDebugHistory || [];
		window.labelDebugHistory.push({
			zpl: strToPrint,
			barcode,
			facility,
			patientART,
			timestamp: new Date().toISOString()
		});
		if (window.labelDebugHistory.length > 50) {
			window.labelDebugHistory = window.labelDebugHistory.slice(-50);
		}

		selected_printer.send(
			strToPrint,
			() => {
				console.log("✅ Label sent.");
				printComplete();
			},
			printerError
		);
	} catch (error) {
		console.error('Print formatting error:', error);
		printerError("Error formatting print data: " + error.message);
	}
}

function hidePrintForm() {
	$('#print_form').hide();
}

function showPrintForm() {
	$('#print_form').show();
}

function showLoading(text) {
	$('#loading_message').text(text);
	$('#printer_data_loading').show();
	hidePrintForm();
	$('#printer_details').hide();
	$('#printer_select').hide();
}

function printComplete() {
	hideLoading();
	if (typeof toast !== 'undefined') {
		toast.success('Label printed successfully!');
	} else {
		alert("Printing complete");
	}
}

function hideLoading() {
	$('#printer_data_loading').hide();
	if (default_mode === true) {
		showPrintForm();
		$('#printer_details').show();
	} else {
		$('#printer_select').show();
		showPrintForm();
	}
}

function changePrinter() {
	default_mode = false;
	setSelectedPrinter(null);
	$('#printer_details').hide();

	if (available_printers == null) {
		showLoading("Searching for Compatible Zebra Printers...Please wait");
		$('#print_form').hide();
		setTimeout(changePrinter, 200);
		return;
	}

	$('#printer_select').show();
	onPrinterSelected();
}

function onPrinterSelected() {
	const printersElement = document.getElementById('printers');
	if (!printersElement) {
		console.error('Printers select element not found');
		return;
	}
	if (!available_printers || available_printers.length === 0) {
		console.error('No available printers');
		return;
	}
	const selectedIndex = printersElement.selectedIndex;
	if (selectedIndex >= 0 && selectedIndex < available_printers.length) {
		const selectedValue = printersElement.value;
		for (let i = 0; i < available_printers.length; i++) {
			if (available_printers[i].uid === selectedValue) {
				setSelectedPrinter(available_printers[i]);
				console.log('Printer selected:', selected_printer);
				break;
			}
		}
	}
}

function setSelectedPrinter(printer) {
	selected_printer = printer;
	if (selected_printer) {
		try {
			currentZebraPrinter = createSafeZebraPrinter(selected_printer);
			console.log("Wrapped selected printer in Zebra.Printer:", currentZebraPrinter);
		} catch (e) {
			console.error("Failed to wrap printer; will proceed without Zebra wrapper:", e);
			currentZebraPrinter = null;
		}
	} else {
		currentZebraPrinter = null;
	}
}

function showErrorMessage(text) {
	$('#main').hide();
	$('#error_div').show();
	$('#error_message').text(text);
	console.error('Error:', text);
}

function printerError(text) {
	hideLoading();
	const errorMessage = "An error occurred while printing: " + (text || "Unknown error") +
		". Please check your printer connection and try again.";
	showErrorMessage(errorMessage);
}

function trySetupAgain() {
	$('#main').show();
	$('#error_div').hide();
	setupWebPrint();
}

$(document).ready(function () {
	setupWebPrint();
});

function getAvailablePrinters() {
	return available_printers || [];
}

function isReady() {
	return currentZebraPrinter !== null && typeof zebraFormat !== 'undefined' && !!zebraFormat;
}
