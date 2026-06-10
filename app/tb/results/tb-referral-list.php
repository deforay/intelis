<?php

// app/tb/results/tb-referral-list.php

$referralListPage = [
    'testType'   => 'tb',
    'title'      => "TB | Referral List",
    'heading'    => _translate("TB Referral List"),
    'referUrl'   => '/tb/results/add-tb-referral.php',
    'ajaxSource' => 'getTbReferralDetails.php',
    'updateUrl'  => 'update-tb-referral-helper.php',
    'pdfUrl'     => '/tb/results/pdf/generate-tb-manifest.php',
];

require APPLICATION_PATH . '/specimen-referral-manifest/_referral-list-body.php';
