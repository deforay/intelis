/**
 * Enhanced Zebra Printer Web Interface
 * Compatible with Zebra Browser Print
 * Maintains original function signatures with improved implementation
 */

// Global variables - maintain original structure
var available_printers = null;
var selected_category = null;
var default_printer = null;
var selected_printer = null;
var default_mode = true;

// Enhanced string replacement (replaces String.prototype.replaceAll)
String.prototype.replaceAll = function (search, replacement) {
	if (typeof this !== 'string' || typeof search !== 'string') {
		return this;
	}
	return this.split(search).join(replacement || '');
};

// Enhanced URL decode function
function urldecode(str) {
	if (typeof str !== "string") return str;
	try {
		return decodeURIComponent(str.replace(/\+/g, ' '));
	} catch (error) {
		console.warn('URL decode error:', error);
		return str;
	}
}

// Main setup function - enhanced with better error handling
function setupWebPrint() {
	$('#printer_select').on('change', onPrinterSelected);
	default_mode = true;
	selected_printer = null;
	available_printers = null;
	selected_category = null;
	default_printer = null;

	// Check if BrowserPrint is available
	if (typeof BrowserPrint === 'undefined') {
		showBrowserPrintNotFound();
		return;
	}

	showLoading("Loading Printer Information...");

	BrowserPrint.getDefaultDevice('printer', function (printer) {
		default_printer = printer;
		if ((printer != null) && (printer.connection != undefined)) {
			selected_printer = printer;
			var printer_details = $('#printer_details');
			var selected_printer_div = $('#selected_printer');

			selected_printer_div.text("Using Default Printer: " + printer.name);
			hideLoading();
			printer_details.show();
			$('#print_form').show();
		}

		BrowserPrint.getLocalDevices(function (printers) {
			available_printers = printers;
			var sel = document.getElementById("printers");
			var printers_available = false;

			if (sel) {
				sel.innerHTML = "";
				if (printers != undefined) {
					for (var i = 0; i < printers.length; i++) {
						if (printers[i].connection == 'usb' && printers[i].uid) {
							var opt = document.createElement("option");
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
			}
			else if (selected_printer == null) {
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

// Enhanced browser print not found message
function showBrowserPrintNotFound() {
	var message = "An error occurred while attempting to connect to your Zebra Printer. " +
		"This could be due to:<br>" +
		"• Zebra Browser Print is not installed<br>" +
		"• Zebra Browser Print service is not running<br>" +
		"• Printer is not connected or powered on<br><br>" +
		"Please install Zebra Browser Print, ensure the service is running, and verify your printer connection.";
	showErrorMessage(message);
}

// Enhanced barcode printing with validation
function printBarcodeLabel(barcode, facility, patientART) {
	// Input validation
	if (!selected_printer) {
		showErrorMessage("No printer selected. Please select a printer first.");
		return;
	}

	if (!barcode || typeof barcode !== 'string' || barcode.trim() === '') {
		showErrorMessage("Invalid barcode data. Please provide a valid barcode.");
		return;
	}

	// Check if zebraFormat is available
	if (typeof zebraFormat === 'undefined') {
		showErrorMessage("Label format is not defined. Please ensure the format template is loaded.");
		return;
	}

	showLoading("Printing...");
	facility = urldecode(facility || '');
	patientART = patientART || '';

	checkPrinterStatus(function (text) {
		if (text == "Ready to Print") {
			try {
				let strToPrint = zebraFormat.replaceAll("1234567", barcode);
				strToPrint = strToPrint.replaceAll("BARCODE", barcode);
				if (facility != '') {
					strToPrint = strToPrint.replaceAll("FACILITY", facility);
				}
				if (patientART != '') {
					strToPrint = strToPrint.replaceAll("PATIENTART", patientART);
				}
				selected_printer.send(strToPrint, printComplete, printerError);
			} catch (error) {
				console.error('Print formatting error:', error);
				printerError("Error formatting print data: " + error.message);
			}
		}
		else {
			printerError(text);
		}
	});
}

// Enhanced printer status check with better error parsing
function checkPrinterStatus(finishedFunction) {
	if (!selected_printer) {
		finishedFunction("No printer selected");
		return;
	}

	selected_printer.sendThenRead("~HQES",
		function (text) {
			try {
				let statuses = [];
				let ok = false;

				// Validate response length
				if (!text || text.length < 90) {
					finishedFunction("Invalid status response from printer");
					return;
				}

				let is_error = text.charAt(70);
				let media = text.charAt(88);
				let head = text.charAt(87);
				let pause = text.charAt(84);

				// Check each flag that prevents printing
				if (is_error == '0') {
					ok = true;
					statuses.push("Ready to Print");
				}

				// Media status checks
				if (media == '1') statuses.push("Paper out");
				if (media == '2') statuses.push("Ribbon out");
				if (media == '4') statuses.push("Media door open");
				if (media == '8') statuses.push("Cutter fault");

				// Head status checks
				if (head == '1') statuses.push("Printhead overheating");
				if (head == '2') statuses.push("Motor overheating");
				if (head == '4') statuses.push("Printhead fault");
				if (head == '8') statuses.push("Incorrect printhead");

				// Pause status
				if (pause == '1') statuses.push("Printer paused");

				console.log('Printer status response:', text);
				console.log('Parsed statuses:', statuses);

				if (!ok && statuses.length == 0) {
					statuses.push("Unknown error - please check printer");
				}

				finishedFunction(statuses.join(', '));
			} catch (error) {
				console.error('Status parsing error:', error);
				finishedFunction("Error reading printer status");
			}
		},
		function (error) {
			console.error('Status check error:', error);
			printerError("Unable to check printer status: " + error);
		}
	);
}

// UI control functions - enhanced with better error handling
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
	// Use modern notification if available, fallback to alert
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
	}
	else {
		$('#printer_select').show();
		showPrintForm();
	}
}

function changePrinter() {
	default_mode = false;
	selected_printer = null;
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

// Enhanced printer selection with better error handling
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
		// Find the printer that matches the selected option
		const selectedValue = printersElement.value;
		for (let i = 0; i < available_printers.length; i++) {
			if (available_printers[i].uid === selectedValue) {
				selected_printer = available_printers[i];
				console.log('Printer selected:', selected_printer);
				break;
			}
		}
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

// Auto-initialize when DOM is ready - this replaces the old $(document).ready call
$(document).ready(function () {
	setupWebPrint();
});

// Additional utility functions for advanced usage (optional)
function getPrinterStatus() {
	return new Promise(function (resolve, reject) {
		if (!selected_printer) {
			reject(new Error('No printer selected'));
			return;
		}

		checkPrinterStatus(function (status) {
			resolve(status);
		});
	});
}

function getAvailablePrinters() {
	return available_printers || [];
}

function isReady() {
	return selected_printer !== null && typeof zebraFormat !== 'undefined';
}
