<?php

// The result a printed report's QR code points to. See _view-result-by-qr.php.
$qrView = [
    'table' => 'form_hepatitis',
    'primaryKey' => 'hepatitis_id',
    'pdfPath' => '/hepatitis/results/generate-result-pdf.php',
];

require APPLICATION_PATH . '/includes/_view-result-by-qr.php';
