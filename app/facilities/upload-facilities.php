<?php

use App\Registries\AppRegistry;
use App\Utilities\MiscUtility;
use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\FacilityImportService;

require_once APPLICATION_PATH . '/header.php';

/** @var FacilityImportService $importService */
$importService = ContainerRegistry::get(FacilityImportService::class);

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$h = fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$batch = null;
if (!empty($_GET['batch'])) {
	$batch = $importService->loadBatch((string) $_GET['batch']);
	if ($batch === null) {
		$_SESSION['alertMsg'] = _translate('This upload has expired or was already imported. Upload the file again.');
	}
}

$importResult = $_SESSION['facilityImportResult'] ?? null;
unset($_SESSION['facilityImportResult']);

$actionLabels = [
	FacilityImportService::ACTION_INSERT => [_translate('New'), 'label-success'],
	FacilityImportService::ACTION_UPDATE => [_translate('Update'), 'label-primary'],
	FacilityImportService::ACTION_UNCHANGED => [_translate('No change'), 'label-default'],
	FacilityImportService::ACTION_SKIP => [_translate('Skipped'), 'label-warning'],
	FacilityImportService::ACTION_ERROR => [_translate('Error'), 'label-danger'],
];
$fieldLabels = [
	'facility_name' => _translate('Facility Name'),
	'facility_code' => _translate('Facility Code'),
	'other_id' => _translate('External Facility Code'),
	'facility_state' => _translate('Province/State'),
	'facility_district' => _translate('District/County'),
	'facility_type' => _translate('Facility Type'),
	'address' => _translate('Address'),
	'facility_emails' => _translate('Email'),
	'facility_mobile_numbers' => _translate('Phone Number'),
	'latitude' => _translate('Latitude'),
	'longitude' => _translate('Longitude'),
	'status' => _translate('Status'),
];

$counts = array_fill_keys(array_keys($actionLabels), 0);
$warningCount = 0;
$problemsToken = null;
$writable = [FacilityImportService::ACTION_INSERT, FacilityImportService::ACTION_UPDATE];
if ($batch !== null) {
	$problemRows = [];
	foreach ($batch['rows'] as $row) {
		$counts[$row['action']]++;
		if (!empty($row['warnings'])) {
			$warningCount++;
		}
		if (!empty($row['warnings']) || in_array($row['action'], [FacilityImportService::ACTION_ERROR, FacilityImportService::ACTION_SKIP], true)) {
			$problemRows[] = $row;
		}
	}
	if ($problemRows !== []) {
		$problemsFile = VAR_TEMP_PATH . DIRECTORY_SEPARATOR . 'facility-import' . DIRECTORY_SEPARATOR . 'problems-' . $batch['id'] . '.xlsx';
		MiscUtility::makeDirectory(dirname($problemsFile));
		$importService->writeRowsFile($problemRows, $problemsFile);
		$problemsToken = _downloadToken($problemsFile);
	}
}
$options = [
	FacilityImportService::OPTION_DEFAULT => [
		'fa-circle-plus',
		_translate("Add new only"),
		_translate("Existing facilities are left untouched. Rows matching one are skipped."),
		_translate("Default"),
	],
	FacilityImportService::OPTION_NAME => [
		'fa-font',
		_translate("Match by name"),
		_translate("Updates the facility with the same name. Other rows are added."),
		null,
	],
	FacilityImportService::OPTION_CODE => [
		'fa-hashtag',
		_translate("Match by code"),
		_translate("Updates the facility with the same code. Use this to rename facilities."),
		null,
	],
	FacilityImportService::OPTION_NAME_CODE => [
		'fa-shield-halved',
		_translate("Match by name and code"),
		_translate("Updates only when both belong to the same facility. The safest update."),
		null,
	],
];
$step = $batch !== null ? 2 : ($importResult !== null ? 3 : 1);
$steps = [1 => _translate("Upload file"), 2 => _translate("Review changes"), 3 => _translate("Done")];
$tiles = [
	FacilityImportService::ACTION_INSERT => ['fa-circle-plus', 'tile-new'],
	FacilityImportService::ACTION_UPDATE => ['fa-pen', 'tile-update'],
	FacilityImportService::ACTION_UNCHANGED => ['fa-equals', 'tile-same'],
	FacilityImportService::ACTION_SKIP => ['fa-forward', 'tile-skip'],
	FacilityImportService::ACTION_ERROR => ['fa-circle-xmark', 'tile-error'],
];
?>
<style>
	#facilityUpload {
		--fu-border: #dfe4ea;
		--fu-muted: #6b7785;
		--fu-blue: #3c8dbc;
		--fu-green: #00a65a;
		--fu-amber: #e08e0b;
		--fu-red: #dd4b39;
		max-width: 1280px;
		margin: 0 auto;
	}

	#facilityUpload .fu-card {
		background: #fff;
		border: 1px solid var(--fu-border);
		border-radius: 8px;
		box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
		margin-bottom: 20px;
	}

	#facilityUpload .fu-card-body {
		padding: 24px;
	}

	#facilityUpload .fu-card h3 {
		margin: 0 0 4px;
		font-size: 18px;
		font-weight: 600;
	}

	#facilityUpload .fu-sub {
		color: var(--fu-muted);
		margin: 0 0 18px;
	}

	/* Steps */
	#facilityUpload .fu-steps {
		display: flex;
		gap: 8px;
		list-style: none;
		padding: 0;
		margin: 0 0 20px;
	}

	#facilityUpload .fu-steps li {
		flex: 1;
		display: flex;
		align-items: center;
		gap: 10px;
		padding: 10px 14px;
		border-radius: 8px;
		background: #fff;
		border: 1px solid var(--fu-border);
		color: var(--fu-muted);
		font-weight: 600;
	}

	#facilityUpload .fu-steps .fu-step-no {
		width: 26px;
		height: 26px;
		border-radius: 50%;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: #eef1f4;
		font-size: 13px;
	}

	#facilityUpload .fu-steps li.is-current {
		border-color: var(--fu-blue);
		color: #1f2d3d;
	}

	#facilityUpload .fu-steps li.is-current .fu-step-no {
		background: var(--fu-blue);
		color: #fff;
	}

	#facilityUpload .fu-steps li.is-done .fu-step-no {
		background: var(--fu-green);
		color: #fff;
	}

	/* Match options */
	#facilityUpload .fu-options {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 12px;
		margin-bottom: 28px;
	}

	#facilityUpload .fu-option {
		position: relative;
		display: flex;
		align-items: flex-start;
		gap: 12px;
		margin: 0;
		padding: 14px 16px;
		border: 1px solid var(--fu-border);
		border-radius: 10px;
		background: #fff;
		cursor: pointer;
		font-weight: 400;
		transition: border-color .15s, background-color .15s, box-shadow .15s;
	}

	#facilityUpload .fu-option input {
		position: absolute;
		opacity: 0;
		pointer-events: none;
	}

	#facilityUpload .fu-option-icon {
		flex: 0 0 36px;
		height: 36px;
		border-radius: 8px;
		background: #eef1f4;
		color: var(--fu-muted);
		display: inline-flex;
		align-items: center;
		justify-content: center;
		font-size: 15px;
		transition: background-color .15s, color .15s;
	}

	#facilityUpload .fu-option-body {
		flex: 1;
		min-width: 0;
	}

	#facilityUpload .fu-option-title {
		display: flex;
		align-items: center;
		gap: 8px;
		font-size: 14px;
		font-weight: 600;
		color: #1f2d3d;
		line-height: 1.3;
		margin-bottom: 3px;
	}

	#facilityUpload .fu-option-badge {
		font-size: 11px;
		font-weight: 600;
		padding: 1px 7px;
		border-radius: 999px;
		background: #eef1f4;
		color: var(--fu-muted);
	}

	#facilityUpload .fu-option-text {
		display: block;
		font-size: 13px;
		font-weight: 400;
		line-height: 1.45;
		color: var(--fu-muted);
	}

	#facilityUpload .fu-option-radio {
		flex: 0 0 18px;
		height: 18px;
		margin-top: 2px;
		border: 2px solid #c3cdd6;
		border-radius: 50%;
		transition: border-color .15s, box-shadow .15s;
	}

	#facilityUpload .fu-option:hover {
		border-color: #b8c4d0;
		box-shadow: 0 2px 6px rgba(16, 24, 40, .06);
	}

	#facilityUpload .fu-option:has(input:checked),
	#facilityUpload .fu-option.is-checked {
		border-color: var(--fu-blue);
		background: #f5f9fd;
		box-shadow: 0 0 0 1px var(--fu-blue);
	}

	#facilityUpload .fu-option:has(input:checked) .fu-option-icon,
	#facilityUpload .fu-option.is-checked .fu-option-icon {
		background: var(--fu-blue);
		color: #fff;
	}

	#facilityUpload .fu-option:has(input:checked) .fu-option-radio,
	#facilityUpload .fu-option.is-checked .fu-option-radio {
		border-color: var(--fu-blue);
		box-shadow: inset 0 0 0 3px #fff, inset 0 0 0 9px var(--fu-blue);
	}

	#facilityUpload .fu-option:has(input:focus-visible) {
		outline: 2px solid var(--fu-blue);
		outline-offset: 2px;
	}

	@media (max-width: 991px) {
		#facilityUpload .fu-options {
			grid-template-columns: 1fr;
		}
	}

	/* Dropzone */
	#facilityUpload .fu-dropzone {
		position: relative;
		display: block;
		margin: 0;
		border: 2px dashed #c3cdd6;
		border-radius: 8px;
		background: #f7f9fb;
		padding: 40px 20px;
		text-align: center;
		cursor: pointer;
		font-weight: normal;
		transition: border-color .15s, background-color .15s;
	}

	#facilityUpload .fu-dropzone:hover,
	#facilityUpload .fu-dropzone:focus-within,
	#facilityUpload .fu-dropzone.is-dragover {
		border-color: var(--fu-blue);
		background: #eef6fc;
	}

	#facilityUpload .fu-dropzone.is-dragover {
		border-style: solid;
	}

	#facilityUpload .fu-dropzone input[type=file] {
		position: absolute;
		width: 1px;
		height: 1px;
		opacity: 0;
	}

	#facilityUpload .fu-drop-icon {
		font-size: 40px;
		color: #9aa7b3;
		margin-bottom: 10px;
	}

	#facilityUpload .fu-dropzone:hover .fu-drop-icon,
	#facilityUpload .fu-dropzone.is-dragover .fu-drop-icon {
		color: var(--fu-blue);
	}

	#facilityUpload .fu-drop-primary {
		font-size: 16px;
		color: #1f2d3d;
	}

	#facilityUpload .fu-drop-primary u {
		color: var(--fu-blue);
	}

	#facilityUpload .fu-drop-hint {
		color: var(--fu-muted);
		font-size: 12px;
		margin: 4px 0 0;
	}

	#facilityUpload .fu-file {
		display: none;
		align-items: center;
		justify-content: center;
		gap: 10px;
		font-size: 15px;
		color: #1f2d3d;
	}

	#facilityUpload .fu-file .fa-file-excel {
		color: #1d6f42;
		font-size: 26px;
	}

	#facilityUpload .fu-file-size {
		color: var(--fu-muted);
		font-size: 13px;
	}

	#facilityUpload .fu-file-clear {
		color: var(--fu-red);
		font-size: 13px;
		margin-left: 6px;
	}

	#facilityUpload .fu-dropzone.has-file {
		border-style: solid;
		border-color: var(--fu-green);
		background: #f4fbf6;
	}

	#facilityUpload .fu-dropzone.has-file .fu-drop-prompt {
		display: none;
	}

	#facilityUpload .fu-dropzone.has-file .fu-file {
		display: flex;
	}

	#facilityUpload .fu-error {
		display: none;
		margin: 10px 0 0;
		color: var(--fu-red);
	}

	#facilityUpload .fu-actions {
		display: flex;
		gap: 8px;
		align-items: center;
		justify-content: flex-end;
		padding: 16px 24px;
		border-top: 1px solid var(--fu-border);
		background: #fafbfc;
		border-radius: 0 0 8px 8px;
	}

	#facilityUpload .fu-actions .fu-actions-note {
		margin-right: auto;
		color: var(--fu-muted);
	}

	#facilityUpload .btn {
		border-radius: 6px;
	}

	/* Side panel */
	#facilityUpload .fu-side h4 {
		font-size: 15px;
		font-weight: 600;
		margin: 0 0 10px;
	}

	#facilityUpload .fu-side .btn-block {
		margin-bottom: 8px;
	}

	#facilityUpload .fu-template-btn {
		font-weight: 600;
		padding: 10px 12px;
	}

	#facilityUpload .fu-instructions {
		margin: 0 0 16px;
		padding: 0;
		list-style: none;
		counter-reset: fu-step;
		font-size: 13px;
	}

	#facilityUpload .fu-instructions li {
		position: relative;
		padding: 0 0 10px 32px;
		counter-increment: fu-step;
		color: #34404d;
	}

	#facilityUpload .fu-instructions li::before {
		content: counter(fu-step);
		position: absolute;
		left: 0;
		top: -1px;
		width: 22px;
		height: 22px;
		border-radius: 50%;
		background: #eef6fc;
		color: var(--fu-blue);
		font-weight: 700;
		font-size: 12px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
	}

	#facilityUpload .fu-columns {
		margin: 0;
		padding: 0;
		list-style: none;
		font-size: 13px;
	}

	#facilityUpload .fu-columns li {
		display: flex;
		gap: 8px;
		padding: 5px 0;
		border-bottom: 1px dashed #eef1f4;
	}

	#facilityUpload .fu-columns li:last-child {
		border-bottom: 0;
	}

	#facilityUpload .fu-col-letter {
		flex: 0 0 22px;
		height: 22px;
		border-radius: 4px;
		background: #eef1f4;
		color: var(--fu-muted);
		font-weight: 600;
		font-size: 12px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
	}

	/* Summary tiles */
	#facilityUpload .fu-tiles {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
		gap: 10px;
		margin-bottom: 16px;
	}

	#facilityUpload .fu-tile {
		display: block;
		width: 100%;
		text-align: left;
		background: #fff;
		border: 1px solid var(--fu-border);
		border-left: 4px solid var(--tile-color);
		border-radius: 8px;
		padding: 10px 14px;
		cursor: pointer;
		transition: box-shadow .15s, background-color .15s;
	}

	#facilityUpload .fu-tile:hover {
		box-shadow: 0 2px 6px rgba(16, 24, 40, .08);
	}

	#facilityUpload .fu-tile.is-active {
		background: #f0f6fb;
		box-shadow: inset 0 0 0 1px var(--tile-color);
	}

	#facilityUpload .fu-tile[disabled] {
		opacity: .5;
		cursor: default;
		box-shadow: none;
	}

	#facilityUpload .fu-tile-count {
		display: block;
		font-size: 24px;
		font-weight: 700;
		line-height: 1.1;
		color: #1f2d3d;
	}

	#facilityUpload .fu-tile-label {
		color: var(--fu-muted);
		font-size: 13px;
	}

	#facilityUpload .fu-tile-label .fa-solid {
		color: var(--tile-color);
		margin-right: 4px;
	}

	#facilityUpload .tile-all { --tile-color: #607d8b; }
	#facilityUpload .tile-new { --tile-color: var(--fu-green); }
	#facilityUpload .tile-update { --tile-color: var(--fu-blue); }
	#facilityUpload .tile-same { --tile-color: #9aa7b3; }
	#facilityUpload .tile-skip { --tile-color: #8e6fd1; }
	#facilityUpload .tile-error { --tile-color: var(--fu-red); }
	#facilityUpload .tile-warning { --tile-color: var(--fu-amber); }

	/* Review table */
	#facilityUpload .fu-toolbar {
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
		align-items: center;
		margin-bottom: 12px;
	}

	#facilityUpload .fu-search {
		position: relative;
		flex: 1 1 260px;
		max-width: 360px;
	}

	#facilityUpload .fu-search .fa-magnifying-glass {
		position: absolute;
		left: 11px;
		top: 10px;
		color: var(--fu-muted);
	}

	#facilityUpload .fu-search input {
		padding-left: 32px;
		border-radius: 6px;
	}

	#facilityUpload .fu-table-wrap {
		max-height: 62vh;
		overflow: auto;
		border: 1px solid var(--fu-border);
		border-radius: 8px;
	}

	#facilityUpload .fu-table {
		margin: 0;
		font-size: 13px;
	}

	#facilityUpload .fu-table thead th {
		position: sticky;
		top: 0;
		z-index: 1;
		background: #f7f9fb;
		border-bottom: 1px solid var(--fu-border);
		color: var(--fu-muted);
		font-weight: 600;
		white-space: nowrap;
	}

	#facilityUpload .fu-table td {
		vertical-align: top;
		border-top: 1px solid #eef1f4;
	}

	#facilityUpload .fu-table tr.has-warning td {
		background: #fffaf0;
	}

	#facilityUpload .fu-table tr.has-warning td:first-child {
		box-shadow: inset 3px 0 0 var(--fu-amber);
	}

	#facilityUpload .fu-pill {
		display: inline-block;
		padding: 2px 9px;
		border-radius: 999px;
		font-size: 12px;
		font-weight: 600;
		white-space: nowrap;
		color: var(--tile-color);
		background: color-mix(in srgb, var(--tile-color) 12%, #fff);
	}

	#facilityUpload .fu-name {
		font-weight: 600;
		color: #1f2d3d;
	}

	#facilityUpload .fu-meta {
		color: var(--fu-muted);
		font-size: 12px;
	}

	#facilityUpload .fu-details {
		margin: 0;
		padding: 0;
		list-style: none;
	}

	#facilityUpload .fu-details li {
		margin-bottom: 3px;
	}

	#facilityUpload .fu-details .fu-warn {
		color: #8a5a00;
		font-weight: 600;
	}

	#facilityUpload .fu-details .fu-msg {
		color: var(--fu-muted);
	}

	#facilityUpload .fu-change-field {
		color: var(--fu-muted);
	}

	#facilityUpload .fu-old {
		color: #a94442;
		text-decoration: line-through;
	}

	#facilityUpload .fu-new {
		color: #2d7a3e;
		font-weight: 600;
	}

	#facilityUpload .fu-empty {
		display: none;
		padding: 30px;
		text-align: center;
		color: var(--fu-muted);
	}

	/* Sticky import bar */
	#facilityUpload .fu-sticky {
		position: sticky;
		bottom: 0;
		z-index: 5;
	}

	/* Result */
	#facilityUpload .fu-result {
		text-align: center;
		padding: 36px 24px 28px;
	}

	#facilityUpload .fu-result-icon {
		font-size: 46px;
		margin-bottom: 10px;
	}

	#facilityUpload .fu-result .fu-tiles {
		max-width: 760px;
		margin: 20px auto;
		text-align: left;
	}

	#facilityUpload .fu-result .fu-tile {
		cursor: default;
	}

	#facilityUpload .fu-result .fu-tile:hover {
		box-shadow: none;
	}

	@media (max-width: 767px) {
		#facilityUpload .fu-steps .fu-step-label {
			display: none;
		}

		#facilityUpload .fu-actions {
			flex-wrap: wrap;
		}

		#facilityUpload .fu-actions .fu-actions-note {
			flex-basis: 100%;
		}
	}
</style>

<div class="content-wrapper">
	<section class="content-header">
		<h1><em class="fa-solid fa-hospital"></em> <?= _translate("Upload Facilities in Bulk"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a></li>
			<li><a href="/facilities/facilities.php"><?= _translate("Facilities"); ?></a></li>
			<li class="active"><?= _translate("Bulk Upload"); ?></li>
		</ol>
	</section>

	<section class="content" id="facilityUpload">
		<ol class="fu-steps" aria-label="<?= _translate("Upload progress"); ?>">
			<?php foreach ($steps as $no => $stepLabel) { ?>
				<li class="<?= $no === $step ? 'is-current' : ($no < $step ? 'is-done' : ''); ?>" <?= $no === $step ? 'aria-current="step"' : ''; ?>>
					<span class="fu-step-no"><?= $no < $step ? '<em class="fa-solid fa-check"></em>' : $no; ?></span>
					<span class="fu-step-label"><?= $stepLabel; ?></span>
				</li>
			<?php } ?>
		</ol>

		<?php if ($importResult !== null) {
			$failed = (int) $importResult['failed']; ?>
			<div class="fu-card">
				<div class="fu-result">
					<div class="fu-result-icon" style="color: <?= $failed > 0 ? '#e08e0b' : '#00a65a'; ?>;">
						<em class="fa-solid <?= $failed > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check'; ?>"></em>
					</div>
					<h3><?= $failed > 0 ? _translate("Upload finished with some rows not saved") : _translate("Upload complete"); ?></h3>
					<div class="fu-tiles">
						<?php foreach ([
							['inserted', _translate("Added"), 'tile-new', 'fa-circle-plus'],
							['updated', _translate("Updated"), 'tile-update', 'fa-pen'],
							['unchanged', _translate("No change"), 'tile-same', 'fa-equals'],
							['excluded', _translate("Left out"), 'tile-skip', 'fa-forward'],
							['failed', _translate("Not saved"), 'tile-error', 'fa-circle-xmark'],
						] as [$key, $resultLabel, $class, $icon]) { ?>
							<div class="fu-tile <?= $class; ?>">
								<span class="fu-tile-count"><?= (int) ($importResult[$key] ?? 0); ?></span>
								<span class="fu-tile-label"><em class="fa-solid <?= $icon; ?>"></em><?= $resultLabel; ?></span>
							</div>
						<?php } ?>
					</div>
					<?php if (!empty($importResult['failedToken'])) { ?>
						<p><?= _translate("Rows that were not saved can be downloaded, corrected and uploaded again."); ?></p>
					<?php } ?>
				</div>
				<div class="fu-actions">
					<?php if (!empty($importResult['failedToken'])) { ?>
						<a class="btn btn-warning" href="/download.php?f=<?= $h($importResult['failedToken']); ?>" target="_blank">
							<em class="fa-solid fa-download"></em> <?= _translate("Download rows not saved"); ?></a>
					<?php } ?>
					<a class="btn btn-default" href="/facilities/upload-facilities.php"><em class="fa-solid fa-upload"></em> <?= _translate("Upload another file"); ?></a>
					<a class="btn btn-primary" href="/facilities/facilities.php"><?= _translate("Go to Facilities"); ?></a>
				</div>
			</div>
		<?php } elseif ($batch !== null) {
			$total = count($batch['rows']); ?>
			<div class="fu-card">
				<div class="fu-card-body">
					<h3><?= _translate("Review changes"); ?></h3>
					<p class="fu-sub">
						<em class="fa-solid fa-file-excel" style="color:#1d6f42;"></em> <?= $h($batch['fileName']); ?> &middot;
						<?= sprintf(_translate("%d rows"), $total); ?> &middot;
						<?= _translate("Nothing has been saved yet. Only ticked rows are imported. Rows with warnings start unticked. Blank optional cells keep the value already saved."); ?>
					</p>

					<div class="fu-tiles" role="group" aria-label="<?= _translate("Filter rows"); ?>">
						<button type="button" class="fu-tile tile-all is-active" data-filter="all">
							<span class="fu-tile-count"><?= $total; ?></span>
							<span class="fu-tile-label"><em class="fa-solid fa-list"></em><?= _translate("All rows"); ?></span>
						</button>
						<button type="button" class="fu-tile tile-warning" data-filter="warning" <?= $warningCount === 0 ? 'disabled' : ''; ?>>
							<span class="fu-tile-count"><?= $warningCount; ?></span>
							<span class="fu-tile-label"><em class="fa-solid fa-triangle-exclamation"></em><?= _translate("Warnings"); ?></span>
						</button>
						<?php foreach ($tiles as $action => [$icon, $class]) { ?>
							<button type="button" class="fu-tile <?= $class; ?>" data-filter="<?= $h($action); ?>" <?= $counts[$action] === 0 ? 'disabled' : ''; ?>>
								<span class="fu-tile-count"><?= (int) $counts[$action]; ?></span>
								<span class="fu-tile-label"><em class="fa-solid <?= $icon; ?>"></em><?= $actionLabels[$action][0]; ?></span>
							</button>
						<?php } ?>
					</div>

					<div class="fu-toolbar">
						<div class="fu-search">
							<em class="fa-solid fa-magnifying-glass"></em>
							<input type="search" class="form-control" id="reviewSearch" placeholder="<?= _translate("Search name, code, province or district"); ?>" aria-label="<?= _translate("Search rows"); ?>" />
						</div>
						<?php if ($problemsToken !== null) { ?>
							<a class="btn btn-default" href="/download.php?f=<?= $h($problemsToken); ?>" target="_blank">
								<em class="fa-solid fa-download"></em> <?= _translate("Download rows with errors, skips or warnings"); ?></a>
						<?php } ?>
					</div>

					<form method="post" action="/facilities/upload-facilities-helper.php" id="confirmImportForm">
						<input type="hidden" name="action" value="confirm" />
						<input type="hidden" name="batchId" value="<?= $h($batch['id']); ?>" />
						<div class="fu-table-wrap">
							<table class="table fu-table" aria-label="<?= _translate("Facility upload review"); ?>">
								<thead>
									<tr>
										<th scope="col" style="width:36px;"><input type="checkbox" id="tickAllRows" title="<?= _translate("Tick all shown rows that can be imported"); ?>" aria-label="<?= _translate("Tick all shown rows that can be imported"); ?>" /></th>
										<th scope="col" style="width:50px;"><?= _translate("Row"); ?></th>
										<th scope="col" style="width:110px;"><?= _translate("Result"); ?></th>
										<th scope="col"><?= _translate("Facility"); ?></th>
										<th scope="col"><?= _translate("Location"); ?></th>
										<th scope="col" style="width:40%;"><?= _translate("Details"); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($batch['rows'] as $row) {
										$v = $row['values'];
										$hasWarning = !empty($row['warnings']);
										[$icon, $class] = $tiles[$row['action']];
										$search = mb_strtolower(implode(' ', [$v[0], $v[1], $v[2], $v[3], $v[4]])); ?>
										<tr data-action="<?= $h($row['action']); ?>" data-warning="<?= $hasWarning ? '1' : '0'; ?>" data-search="<?= $h($search); ?>" class="<?= $hasWarning ? 'has-warning' : ''; ?>">
											<td>
												<?php if (in_array($row['action'], $writable, true)) { ?>
													<input type="checkbox" class="import-row" name="rows[]" value="<?= (int) $row['row']; ?>" <?= $hasWarning ? '' : 'checked'; ?> aria-label="<?= $h(sprintf(_translate("Import row %d"), $row['row'])); ?>" />
												<?php } ?>
											</td>
											<td class="fu-meta"><?= (int) $row['row']; ?></td>
											<td><span class="fu-pill <?= $class; ?>"><em class="fa-solid <?= $icon; ?>"></em> <?= $actionLabels[$row['action']][0]; ?></span></td>
											<td>
												<div class="fu-name"><?= $v[0] === '' ? '&mdash;' : $h($v[0]); ?></div>
												<div class="fu-meta">
													<?= $v[1] === '' ? '' : $h($v[1]) . ' &middot; '; ?><?= $h($v[5]); ?>
												</div>
											</td>
											<td>
												<div><?= $h($v[3]); ?></div>
												<div class="fu-meta"><?= $h($v[4]); ?></div>
											</td>
											<td>
												<ul class="fu-details">
													<?php foreach ($row['warnings'] ?? [] as $warning) { ?>
														<li class="fu-warn"><em class="fa-solid fa-triangle-exclamation"></em> <?= $h($warning); ?></li>
													<?php } ?>
													<?php foreach ($row['changes'] as $field => [$old, $new]) { ?>
														<li><span class="fu-change-field"><?= $h($fieldLabels[$field] ?? $field); ?>:</span>
															<span class="fu-old"><?= $old === '' ? '&mdash;' : $h($old); ?></span>
															<em class="fa-solid fa-arrow-right fu-meta"></em>
															<span class="fu-new"><?= $h($new); ?></span></li>
													<?php } ?>
													<?php foreach ($row['messages'] as $message) { ?>
														<li class="<?= $row['action'] === FacilityImportService::ACTION_ERROR ? 'text-danger' : 'fu-msg'; ?>"><?= $h($message); ?></li>
													<?php } ?>
												</ul>
											</td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
							<div class="fu-empty" id="reviewEmpty"><?= _translate("No rows match this filter."); ?></div>
						</div>
					</form>
				</div>
				<div class="fu-actions fu-sticky">
					<span class="fu-actions-note">
						<strong id="tickedCount">0</strong> <?= _translate("rows ticked for import"); ?>
						<span id="tickedWarningNote" style="display:none;">
							&middot; <em class="fa-solid fa-triangle-exclamation" style="color:#e08e0b;"></em>
							<span id="tickedWarningCount">0</span> <?= _translate("with warnings"); ?>
						</span>
					</span>
					<form method="post" action="/facilities/upload-facilities-helper.php" style="display:inline;">
						<input type="hidden" name="action" value="discard" />
						<input type="hidden" name="batchId" value="<?= $h($batch['id']); ?>" />
						<button type="submit" class="btn btn-default"><?= _translate("Cancel"); ?></button>
					</form>
					<button type="button" class="btn btn-primary" id="confirmImportButton" onclick="confirmImport(this);">
						<em class="fa-solid fa-check"></em> <?= _translate("Import ticked rows"); ?>
					</button>
				</div>
			</div>
		<?php } else { ?>
			<div class="row">
				<div class="col-md-8">
					<form method="post" id="uploadFacilityForm" autocomplete="off" enctype="multipart/form-data" action="/facilities/upload-facilities-helper.php" class="fu-card">
						<input type="hidden" name="action" value="stage" />
						<div class="fu-card-body">
							<h3><?= _translate("How should existing facilities be handled?"); ?></h3>
							<p class="fu-sub"><?= _translate("Every option shows the result for review before anything is saved."); ?></p>
							<div class="fu-options" role="radiogroup" aria-label="<?= _translate("How should existing facilities be handled?"); ?>">
								<?php foreach ($options as $value => [$icon, $optionLabel, $optionText, $badge]) { ?>
									<label class="fu-option">
										<input type="radio" name="uploadOption" value="<?= $h($value); ?>" <?= $value === FacilityImportService::OPTION_DEFAULT ? 'checked' : ''; ?> />
										<span class="fu-option-icon"><em class="fa-solid <?= $icon; ?>"></em></span>
										<span class="fu-option-body">
											<span class="fu-option-title"><?= $optionLabel; ?>
												<?php if ($badge !== null) { ?><span class="fu-option-badge"><?= $badge; ?></span><?php } ?>
											</span>
											<span class="fu-option-text"><?= $optionText; ?></span>
										</span>
										<span class="fu-option-radio" aria-hidden="true"></span>
									</label>
								<?php } ?>
							</div>

							<h3><?= _translate("Facilities file"); ?></h3>
							<p class="fu-sub"><?= _translate("An export from the Facilities list can be edited and uploaded as it is."); ?></p>
							<label for="facilitiesInfo" class="fu-dropzone" id="facilitiesDropzone">
								<input type="file" id="facilitiesInfo" name="facilitiesInfo" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
								<div class="fu-drop-prompt">
									<div class="fu-drop-icon"><em class="fa-solid fa-cloud-arrow-up"></em></div>
									<div class="fu-drop-primary"><?= sprintf(_translate("Drop the Excel file here, or %s"), '<u>' . _translate("browse") . '</u>'); ?></div>
									<p class="fu-drop-hint"><?= _translate("One .xlsx file"); ?></p>
								</div>
								<div class="fu-file">
									<em class="fa-solid fa-file-excel"></em>
									<span id="facilitiesFileName"></span>
									<span class="fu-file-size" id="facilitiesFileSize"></span>
									<span class="fu-file-clear" id="facilitiesFileClear" role="button" tabindex="0">
										<em class="fa-solid fa-xmark"></em> <?= _translate("Remove"); ?>
									</span>
								</div>
							</label>
							<p class="fu-error" id="facilitiesFileError" role="alert"></p>
						</div>
						<div class="fu-actions">
							<a href="/facilities/facilities.php" class="btn btn-default"><?= _translate("Cancel"); ?></a>
							<button type="submit" class="btn btn-primary" id="reviewUploadButton" disabled>
								<?= _translate("Review Upload"); ?> <em class="fa-solid fa-arrow-right"></em>
							</button>
						</div>
					</form>
				</div>
				<div class="col-md-4">
					<div class="fu-card fu-side">
						<div class="fu-card-body">
							<h4><em class="fa-solid fa-circle-info" style="color:#3c8dbc;"></em> <?= _translate("Instructions"); ?></h4>
							<ol class="fu-instructions">
								<li><?= _translate("Download the blank template, or export the existing facilities to edit them."); ?></li>
								<li><?= _translate("Fill in one facility per row. Keep the columns in their order and leave the heading row in place."); ?></li>
								<li><?= _translate("Facility Type is 1 (Health Facility), 2 (Testing Lab) or 3 (Collection Site). Status is active or inactive."); ?></li>
								<li><?= _translate("Choose how existing facilities are handled, then drop the file on the left."); ?></li>
								<li><?= _translate("Check the review. Rows with warnings start unticked. Nothing is saved until Import is selected."); ?></li>
							</ol>
							<a class="btn btn-success btn-block fu-template-btn" href="/download.php?f=<?= $h(_downloadToken($importService->templateFile())); ?>">
								<em class="fa-solid fa-file-arrow-down"></em> <?= _translate("Download blank template"); ?></a>
							<a class="btn btn-default btn-block" href="/facilities/facilities.php">
								<em class="fa-solid fa-file-export"></em> <?= _translate("Export existing facilities"); ?></a>
						</div>
					</div>
					<div class="fu-card fu-side">
						<div class="fu-card-body">
							<h4><?= _translate("Template columns"); ?></h4>
							<ul class="fu-columns">
								<?php foreach (FacilitiesService::bulkUploadHeadings() as $i => $heading) { ?>
									<li><span class="fu-col-letter"><?= chr(65 + $i); ?></span><span><?= $h($heading); ?></span></li>
								<?php } ?>
							</ul>
							<p class="fu-meta" style="margin:10px 0 0;"><?= _translate("* Required. Blank optional cells keep the value already saved."); ?></p>
						</div>
					</div>
				</div>
			</div>
		<?php } ?>
	</section>
</div>

<script type="text/javascript">
	(function() {
		var form = document.getElementById('uploadFacilityForm');
		if (!form) {
			return;
		}
		var dropzone = document.getElementById('facilitiesDropzone');
		var input = document.getElementById('facilitiesInfo');
		var errorEl = document.getElementById('facilitiesFileError');
		var submit = document.getElementById('reviewUploadButton');

		function formatSize(bytes) {
			return bytes < 1024 * 1024 ? Math.max(1, Math.round(bytes / 1024)) + ' KB' : (bytes / 1024 / 1024).toFixed(1) + ' MB';
		}

		function showFile() {
			var file = input.files && input.files[0] ? input.files[0] : null;
			var valid = !!file && /\.xlsx$/i.test(file.name);
			errorEl.style.display = file && !valid ? 'block' : 'none';
			errorEl.textContent = file && !valid ? "<?= _translate("Only .xlsx files can be uploaded.", true); ?>" : '';
			if (file && !valid) {
				input.value = '';
			}
			dropzone.classList.toggle('has-file', valid);
			document.getElementById('facilitiesFileName').textContent = valid ? file.name : '';
			document.getElementById('facilitiesFileSize').textContent = valid ? formatSize(file.size) : '';
			submit.disabled = !valid;
		}

		function clearFile(e) {
			e.preventDefault();
			e.stopPropagation();
			input.value = '';
			showFile();
		}

		input.addEventListener('change', showFile);
		var clearEl = document.getElementById('facilitiesFileClear');
		clearEl.addEventListener('click', clearFile);
		clearEl.addEventListener('keydown', function(e) {
			if (e.key === 'Enter' || e.key === ' ') {
				clearFile(e);
			}
		});

		['dragenter', 'dragover'].forEach(function(evt) {
			dropzone.addEventListener(evt, function(e) {
				e.preventDefault();
				dropzone.classList.add('is-dragover');
			});
		});
		['dragleave', 'dragend', 'drop'].forEach(function(evt) {
			dropzone.addEventListener(evt, function(e) {
				e.preventDefault();
				dropzone.classList.remove('is-dragover');
			});
		});
		dropzone.addEventListener('drop', function(e) {
			var files = e.dataTransfer && e.dataTransfer.files;
			if (files && files.length) {
				var transfer = new DataTransfer();
				transfer.items.add(files[0]);
				input.files = transfer.files;
				showFile();
			}
		});
		// A file dropped beside the zone would otherwise open in the browser and leave the page.
		['dragover', 'drop'].forEach(function(evt) {
			window.addEventListener(evt, function(e) {
				if (!dropzone.contains(e.target)) {
					e.preventDefault();
				}
			});
		});

		// Browsers without :has() still highlight the chosen option.
		form.querySelectorAll('.fu-option input').forEach(function(radio) {
			radio.addEventListener('change', function() {
				form.querySelectorAll('.fu-option').forEach(function(option) {
					option.classList.toggle('is-checked', option.querySelector('input').checked);
				});
			});
		});
		form.querySelector('.fu-option input:checked').dispatchEvent(new Event('change'));

		form.addEventListener('submit', function(e) {
			if (submit.disabled) {
				e.preventDefault();
				return;
			}
			submit.disabled = true;
			$.blockUI();
		});
	})();

	(function() {
		var confirmForm = document.getElementById('confirmImportForm');
		if (!confirmForm) {
			return;
		}
		var rows = Array.prototype.slice.call(confirmForm.querySelectorAll('tbody tr'));
		var filter = 'all';

		function applyFilter() {
			var term = document.getElementById('reviewSearch').value.trim().toLowerCase();
			var shown = 0;
			rows.forEach(function(tr) {
				var matchesFilter = filter === 'all' ||
					(filter === 'warning' ? tr.dataset.warning === '1' : tr.dataset.action === filter);
				var visible = matchesFilter && (term === '' || tr.dataset.search.indexOf(term) !== -1);
				tr.style.display = visible ? '' : 'none';
				shown += visible ? 1 : 0;
			});
			document.getElementById('reviewEmpty').style.display = shown === 0 ? 'block' : 'none';
			syncTickAll();
		}

		function syncTickAll() {
			var visible = confirmForm.querySelectorAll('tbody tr:not([style*="none"]) .import-row');
			var ticked = confirmForm.querySelectorAll('tbody tr:not([style*="none"]) .import-row:checked');
			var tickAll = document.getElementById('tickAllRows');
			tickAll.checked = visible.length > 0 && ticked.length === visible.length;
			tickAll.indeterminate = ticked.length > 0 && ticked.length < visible.length;
		}

		function updateTickedCount() {
			var ticked = confirmForm.querySelectorAll('.import-row:checked').length;
			var warned = confirmForm.querySelectorAll('tr.has-warning .import-row:checked').length;
			document.getElementById('tickedCount').textContent = ticked;
			document.getElementById('tickedWarningCount').textContent = warned;
			document.getElementById('tickedWarningNote').style.display = warned > 0 ? '' : 'none';
			document.getElementById('confirmImportButton').disabled = ticked === 0;
			syncTickAll();
		}

		document.querySelectorAll('#facilityUpload .fu-tile[data-filter]').forEach(function(tile) {
			tile.addEventListener('click', function() {
				filter = tile.classList.contains('is-active') && tile.dataset.filter !== 'all' ? 'all' : tile.dataset.filter;
				document.querySelectorAll('#facilityUpload .fu-tile[data-filter]').forEach(function(other) {
					other.classList.toggle('is-active', other.dataset.filter === filter);
				});
				applyFilter();
			});
		});
		document.getElementById('reviewSearch').addEventListener('input', applyFilter);
		document.getElementById('tickAllRows').addEventListener('change', function() {
			var checked = this.checked;
			confirmForm.querySelectorAll('tbody tr:not([style*="none"]) .import-row').forEach(function(box) {
				box.checked = checked;
			});
			updateTickedCount();
		});
		confirmForm.addEventListener('change', function(e) {
			if (e.target.classList.contains('import-row')) {
				updateTickedCount();
			}
		});

		window.confirmImport = function(button) {
			var ticked = confirmForm.querySelectorAll('.import-row:checked').length;
			var warned = confirmForm.querySelectorAll('tr.has-warning .import-row:checked').length;
			if (ticked === 0) {
				return;
			}
			if (warned > 0 && !confirm("<?= _translate("Some ticked rows have warnings. Import them anyway?", true); ?>")) {
				return;
			}
			button.disabled = true;
			$.blockUI();
			confirmForm.submit();
		};

		updateTickedCount();
	})();
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
