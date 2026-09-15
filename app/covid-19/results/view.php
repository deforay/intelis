<?php

// The result a printed report's QR code points to. See _view-result-by-qr.php.
$qrView = [
    'table' => 'form_covid19',
    'primaryKey' => 'covid19_id',
    'pdfPath' => '/covid-19/results/generate-result-pdf.php',
];

require APPLICATION_PATH . '/includes/_view-result-by-qr.php';
