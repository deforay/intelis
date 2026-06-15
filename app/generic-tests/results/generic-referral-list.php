<?php

// app/generic-tests/results/generic-referral-list.php

$referralListPage = [
    'testType'   => 'generic-tests',
    'title'      => "Other Lab Tests | Referral List",
    'heading'    => _translate("Other Lab Tests Referral List"),
    'referUrl'   => '/generic-tests/results/add-generic-referral.php',
    'ajaxSource' => 'getGenericReferralDetails.php',
    'updateUrl'  => 'save-generic-referral-helper.php',
    'pdfUrl'     => '/generic-tests/results/pdf/generate-generic-manifest.php',
];

require APPLICATION_PATH . '/specimen-referral-manifest/_referral-list-body.php';
