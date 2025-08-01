let currentZebraPrinter = null;
let available_printers = null;
let default_printer = null;
let selected_printer = null;
let default_mode = true;

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

function setupWebPrint() {
	$('#printer_select').on('change', onPrinterSelected);
	default_mode = true;
	selected_printer = null;
	available_printers = null;
	default_printer = null;

	// Fail fast if core libraries missing
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

function printBarcodeLabel(barcode, facility, patientART) {
	if (!selected_printer || !currentZebraPrinter) {
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

	// readiness with timeout (fallback to direct if Utilities missing)
	let readyPromise;
	if (typeof Utilities !== 'undefined' && Utilities.withTimeout) {
		readyPromise = Utilities.withTimeout(() => currentZebraPrinter.isPrinterReady(), 5000)();
	} else {
		readyPromise = currentZebraPrinter.isPrinterReady();
	}

	readyPromise
		.then(() => {
			try {
				// Build the ZPL with replacements (no mutation/wrapping)
				let strToPrint = replaceAllSafe(zebraFormat, "1234567", barcode);
				strToPrint = replaceAllSafe(strToPrint, "BARCODE", barcode);
				if (facility) {
					strToPrint = replaceAllSafe(strToPrint, "FACILITY", facility);
				}
				if (patientART) {
					strToPrint = replaceAllSafe(strToPrint, "PATIENTART", patientART);
				}

				// Diagnostics
				console.log("zebraFormat raw:", zebraFormat);
				console.log("Barcode:", barcode);
				console.log("Facility:", facility);
				console.log("PatientART:", patientART);
				console.log("Final ZPL being sent:", strToPrint);
				if (strToPrint.includes("BARCODE") || strToPrint.includes("1234567")) {
					console.warn("Placeholder not replaced fully", { strToPrint, zebraFormat, barcode });
				}

				// Capture for debugging
				window.lastLabel = strToPrint;
				window.labelDebugHistory = window.labelDebugHistory || [];
				window.labelDebugHistory.push({
					zpl: strToPrint,
					barcode,
					facility,
					patientART,
					timestamp: new Date().toISOString()
				});

				selected_printer.send(strToPrint, printComplete, printerError);
			} catch (error) {
				console.error('Print formatting error:', error);
				printerError("Error formatting print data: " + error.message);
			}
		})
		.catch(err => {
			const msg = (err && err.toString) ? err.toString() : "Printer not ready or timed out";
			printerError(msg);
		});
}

// UI control functions
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
	if (typeof toastr !== 'undefined') {
		toastr.success('Label printed successfully!');
	} else {
		alert("Printing complete");
	}
}

function hideLoading() {
	$('#printer_data_loading').hide();
	if (default_mode == true) {
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
		currentZebraPrinter = new Zebra.Printer(selected_printer);
		console.log("Wrapped selected printer in Zebra.Printer:", currentZebraPrinter);
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

// Auto-initialize
$(document).ready(function () {
	setupWebPrint();
});

function getAvailablePrinters() {
	return available_printers || [];
}

function isReady() {
	return currentZebraPrinter !== null && typeof zebraFormat !== 'undefined' && !!zebraFormat;
}

// left here in case needed later
function getPrinterStatus() {
    if (!currentZebraPrinter) return Promise.reject(new Error("No printer selected"));
    return currentZebraPrinter.isPrinterReady()
        .then(() => "Ready to Print")
        .catch(err => (err && err.toString) ? err.toString() : "Printer not ready");
}
