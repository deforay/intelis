<?php

// The result a printed report's QR code points to. See _view-result-by-qr.php.
$qrView = [
    'table' => 'form_eid',
    'primaryKey' => 'eid_id',
    'pdfPath' => '/eid/results/generate-result-pdf.php',
];

require APPLICATION_PATH . '/includes/_view-result-by-qr.php';
