<?php

// The result a printed report's QR code points to. See _view-result-by-qr.php.
$qrView = [
    'table' => 'form_cd4',
    'primaryKey' => 'cd4_id',
    'pdfPath' => '/cd4/results/generate-result-pdf.php',
];

require APPLICATION_PATH . '/includes/_view-result-by-qr.php';
