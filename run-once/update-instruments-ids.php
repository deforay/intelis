<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\RunOnceUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    if ($general->isSTSInstance()) {
        // Not required on STS — return so the harness still records it as
        // applied (it won't be retried every upgrade) without doing any work.
        return;
    }

    $instrumentResult = $db->rawQuery("SELECT * FROM instruments");

    $updatedOn = DateUtility::getCurrentDateTime();

    // spinnerStart renders this message on its own line directly above the bar,
    // so it is the single "what's running" line followed by the progress bar.
    $total = count($instrumentResult);
    $bar = $total > 0 ? MiscUtility::spinnerStart($total, 'Updating instrument IDs…') : null;

    foreach ($instrumentResult as $row) {

        $oldInstrumentId = null;
        if (is_numeric($row['instrument_id'])) {
            $oldInstrumentId = $row['instrument_id'];
            $instrumentId = MiscUtility::generateULID();
            $db->where("instrument_id", $row['instrument_id']);
            $db->update('instruments', ['instrument_id' => $instrumentId, 'updated_datetime' => $updatedOn]);

            $db->where("instrument_id", $row['instrument_id']);
            $db->update('instrument_controls', ['instrument_id' => $instrumentId, 'updated_datetime' => $updatedOn]);

            $db->where("instrument_id", $row['instrument_id']);
            $db->update('instrument_machines', ['instrument_id' => $instrumentId, 'updated_datetime' => $updatedOn]);
        } else {
            $instrumentId = $row['instrument_id'];
        }

        $db->where("vl_test_platform", $row["machine_name"]);
        if (!empty($oldInstrumentId)) {
            $db->orWhere("instrument_id", $oldInstrumentId);
        }
        $db->update('form_vl', ['instrument_id' => $instrumentId]);

        $db->where("eid_test_platform", $row["machine_name"]);
        if (!empty($oldInstrumentId)) {
            $db->orWhere("instrument_id", $oldInstrumentId);
        }
        $db->update('form_eid', ['instrument_id' => $instrumentId]);

        $db->where("testing_platform", $row["machine_name"]);
        if (!empty($oldInstrumentId)) {
            $db->orWhere("instrument_id", $oldInstrumentId);
        }
        $db->update('covid19_tests', ['instrument_id' => $instrumentId]);

        $db->where("hepatitis_test_platform", $row["machine_name"]);
        if (!empty($oldInstrumentId)) {
            $db->orWhere("instrument_id", $oldInstrumentId);
        }
        $db->update('form_hepatitis', ['instrument_id' => $instrumentId]);

        $db->where("tb_test_platform", $row["machine_name"]);
        if (!empty($oldInstrumentId)) {
            $db->orWhere("instrument_id", $oldInstrumentId);
        }
        $db->update('form_tb', ['instrument_id' => $instrumentId]);

        if ($bar !== null) {
            MiscUtility::spinnerAdvance($bar);
        }
    }

    if ($bar !== null) {
        MiscUtility::spinnerFinish($bar);
    }
});
