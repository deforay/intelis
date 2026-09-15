<?php

// The result a printed report's QR code points to. See _view-result-by-qr.php.
$qrView = [
    'table' => 'form_generic',
    'primaryKey' => 'sample_id',
    'pdfPath' => '/generic-tests/results/generate-result-pdf.php',
];

require APPLICATION_PATH . '/includes/_view-result-by-qr.php';
