/**
 * Enhanced DYMO Printer Interface
 * Maintains original function signatures with improved implementation
 * Compatible with DYMO Label Framework
 */

// Enhanced URL decode function with better error handling
function urldecode(str) {
    if (typeof str !== "string") return str;
    try {
        return decodeURIComponent(str.replace(/\+/g, ' '));
    } catch (error) {
        console.warn('URL decode error:', error);
        return str; // Return original string if decode fails
    }
}

// Enhanced barcode printing with comprehensive error handling and validation
function printBarcodeLabel(barcode, facility = '', patientART = '') {
    // Input validation
    if (!barcode || typeof barcode !== 'string' || barcode.trim() === '') {
        alert("Invalid barcode data. Please provide a valid barcode.");
        return;
    }

    // Check if DYMO framework is available
    if (typeof dymo === 'undefined' || !dymo.label || !dymo.label.framework) {
        alert("DYMO Label Framework is not available. Please ensure it is installed and loaded.");
        return;
    }

    // Check if dymoLabelXml is defined
    if (typeof dymoLabelXml === 'undefined') {
        alert("DYMO label template is not defined. Please ensure the label XML is loaded.");
        return;
    }

    $.blockUI({
        message: '<div style="padding: 20px;"><h4>Preparing to print...</h4><p>Please wait while we process your label.</p></div>',
        css: {
            border: 'none',
            padding: '15px',
            backgroundColor: '#000',
            '-webkit-border-radius': '10px',
            '-moz-border-radius': '10px',
            opacity: 0.8,
            color: '#fff'
        }
    });

    // Enable tracing for debugging
    dymo.label.framework.trace = 1;

    // URL decode facility parameter
    facility = urldecode(facility || '');
    patientART = patientART || '';

    try {
        console.log('Starting print process with:', { barcode, facility, patientART });

        // Validate and open label XML
        if (!dymoLabelXml || typeof dymoLabelXml !== 'string') {
            throw new Error("Invalid label XML template");
        }

        var label = dymo.label.framework.openLabelXml(dymoLabelXml);
        if (!label) {
            throw new Error("Failed to open label template. Please check the XML format.");
        }

        // Set label text with enhanced error handling
        var objectsSet = [];

        // Set barcode if the template has a BARCODE object
        if (dymoLabelXml.indexOf("<Name>BARCODE</Name>") !== -1) {
            try {
                label.setObjectText('BARCODE', barcode);
                objectsSet.push('BARCODE');
                console.log('Set BARCODE object with value:', barcode);
            } catch (e) {
                console.warn('Could not set BARCODE object:', e);
            }
        }

        // Set facility text if the template has a TEXT object
        if (facility && dymoLabelXml.indexOf("<Name>TEXT</Name>") !== -1) {
            try {
                label.setObjectText('TEXT', facility);
                objectsSet.push('TEXT');
                console.log('Set TEXT object with value:', facility);
            } catch (e) {
                console.warn('Could not set TEXT object:', e);
            }
        }

        // Set patient ART if the template has a PATIENTART object
        if (patientART && dymoLabelXml.indexOf("<Name>PATIENTART</Name>") !== -1) {
            try {
                label.setObjectText('PATIENTART', patientART);
                objectsSet.push('PATIENTART');
                console.log('Set PATIENTART object with value:', patientART);
            } catch (e) {
                console.warn('Could not set PATIENTART object:', e);
            }
        }

        // Additional common object names to check for
        const commonObjects = [
            { name: 'FACILITY', value: facility },
            { name: 'PATIENT_ID', value: patientART },
            { name: 'SAMPLE_CODE', value: barcode }
        ];

        commonObjects.forEach(obj => {
            if (obj.value && dymoLabelXml.indexOf(`<Name>${obj.name}</Name>`) !== -1) {
                try {
                    label.setObjectText(obj.name, obj.value);
                    objectsSet.push(obj.name);
                    console.log(`Set ${obj.name} object with value:`, obj.value);
                } catch (e) {
                    console.warn(`Could not set ${obj.name} object:`, e);
                }
            }
        });

        console.log('Successfully set objects:', objectsSet);

        // Enhanced printer discovery and selection
        var printers = dymo.label.framework.getPrinters();
        console.log('Available printers:', printers);

        if (!printers || printers.length === 0) {
            throw new Error("No DYMO printers are installed. Please install DYMO Label software and connect a printer.");
        }

        // Find the best available printer
        var selectedPrinter = findBestPrinter(printers);

        if (!selectedPrinter) {
            var printerList = printers.map(p => `${p.name} (${p.printerType})`).join(', ');
            throw new Error(`No compatible DYMO printers found. Available printers: ${printerList}. Please ensure a LabelWriter printer is connected.`);
        }

        console.log('Selected printer:', selectedPrinter);

        // Update UI to show printing status
        $.blockUI({
            message: '<div style="padding: 20px;"><h4>Printing label...</h4><p>Sending to: ' + selectedPrinter.name + '</p></div>',
            css: {
                border: 'none',
                padding: '15px',
                backgroundColor: '#000',
                '-webkit-border-radius': '10px',
                '-moz-border-radius': '10px',
                opacity: 0.8,
                color: '#fff'
            }
        });

        // Print the label with error handling
        try {
            label.print(selectedPrinter.name);
            console.log('Print command sent successfully');

            // Show success message briefly
            setTimeout(function() {
                $.unblockUI();
                showPrintSuccess(selectedPrinter.name);
            }, 1000);

        } catch (printError) {
            throw new Error(`Print failed: ${printError.message || printError}. Please check printer connection and try again.`);
        }

    } catch (e) {
        console.error('Print error:', e);
        $.unblockUI();

        var errorMessage = e.message || e.toString();

        // Provide more helpful error messages
        if (errorMessage.includes("framework")) {
            errorMessage += "\n\nPlease ensure DYMO Label software is installed and running.";
        } else if (errorMessage.includes("printer")) {
            errorMessage += "\n\nPlease check that your DYMO printer is connected and powered on.";
        }

        showPrintError(errorMessage);
    }
}

// Helper function to find the best available printer
function findBestPrinter(printers) {
    // Priority order for printer types
    const printerTypePriority = [
        "LabelWriterPrinter",
        "LabelWriterDuoLabelPrinter",
        "LabelWriterDuoTapePrinter",
        "TapePrinter"
    ];

    // First, try to find printers by priority
    for (let i = 0; i < printerTypePriority.length; i++) {
        const preferredType = printerTypePriority[i];
        for (let j = 0; j < printers.length; j++) {
            const printer = printers[j];
            if (printer.printerType === preferredType && printer.isConnected !== false) {
                return printer;
            }
        }
    }

    // If no preferred printer found, return the first connected printer
    for (let i = 0; i < printers.length; i++) {
        if (printers[i].isConnected !== false) {
            return printers[i];
        }
    }

    return null;
}

// Enhanced success notification
function showPrintSuccess(printerName) {
    if (typeof toastr !== 'undefined') {
        toastr.success(`Label printed successfully on ${printerName}!`);
    } else if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Print Successful',
            text: `Label printed on ${printerName}`,
            timer: 2000,
            showConfirmButton: false
        });
    } else {
        alert(`Label printed successfully on ${printerName}!`);
    }
}

// Enhanced error notification
function showPrintError(errorMessage) {
    if (typeof toastr !== 'undefined') {
        toastr.error(errorMessage, 'Print Error', {
            timeOut: 0,
            extendedTimeOut: 0,
            closeButton: true
        });
    } else if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Print Error',
            text: errorMessage,
            confirmButtonText: 'OK'
        });
    } else {
        alert(errorMessage);
    }
}

// Utility function to check DYMO system status
function checkDymoStatus() {
    try {
        if (typeof dymo === 'undefined' || !dymo.label || !dymo.label.framework) {
            return { status: 'error', message: 'DYMO Label Framework not loaded' };
        }

        var printers = dymo.label.framework.getPrinters();
        if (!printers || printers.length === 0) {
            return { status: 'warning', message: 'No DYMO printers found' };
        }

        var connectedPrinters = printers.filter(p => p.isConnected !== false);
        if (connectedPrinters.length === 0) {
            return { status: 'warning', message: 'No connected DYMO printers found' };
        }

        return {
            status: 'success',
            message: `${connectedPrinters.length} DYMO printer(s) ready`,
            printers: connectedPrinters
        };
    } catch (e) {
        return { status: 'error', message: e.message || 'Unknown error checking DYMO status' };
    }
}

// Utility function to get available printers
function getDymoPrinters() {
    try {
        if (typeof dymo === 'undefined' || !dymo.label || !dymo.label.framework) {
            return [];
        }
        return dymo.label.framework.getPrinters() || [];
    } catch (e) {
        console.error('Error getting DYMO printers:', e);
        return [];
    }
}

// Function to validate label template
function validateDymoLabelXml(xml) {
    if (!xml || typeof xml !== 'string') {
        return { valid: false, error: 'No XML template provided' };
    }

    try {
        // Basic XML validation
        if (!xml.includes('<DieCutLabel') && !xml.includes('<ContinuousLabel')) {
            return { valid: false, error: 'Invalid DYMO label XML format' };
        }

        // Check for common objects
        const objects = [];
        const objectRegex = /<Name>([^<]+)<\/Name>/g;
        let match;
        while ((match = objectRegex.exec(xml)) !== null) {
            objects.push(match[1]);
        }

        return {
            valid: true,
            objects: objects,
            hasBarcode: objects.includes('BARCODE'),
            hasText: objects.includes('TEXT')
        };
    } catch (e) {
        return { valid: false, error: e.message };
    }
}

// Initialize function to check system on page load
function initializeDymoSystem() {
    $(document).ready(function() {
        // Check DYMO status after a brief delay to allow framework to load
        setTimeout(function() {
            var status = checkDymoStatus();
            console.log('DYMO System Status:', status);

            if (status.status === 'error') {
                console.warn('DYMO System Issue:', status.message);
            }
        }, 1000);
    });
}

// Auto-initialize
initializeDymoSystem();
