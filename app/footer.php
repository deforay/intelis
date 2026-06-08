<?php
// footer.php
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$remoteURL = $general->getRemoteURL();

$supportEmail = trim((string) $general->getGlobalConfig('support_email'));

// Sync History
//$syncHistory = (_isAllowed("sync-history.php")) ? "/common/reference/sync-history.php" : "javascript:void(0);";
$syncLatestTime = $general->getLastSTSSyncDateTime();
$syncHistoryDisplay = (empty($syncLatestTime)) ? "display:none;" : "display:inline;";

?>

<footer class="main-footer">

	<div class="row">
		<div class="col-lg-8 col-sm-8">
			<small><?= _translate("This project is supported by the U.S. President's Emergency Plan for AIDS Relief (PEPFAR) through the U.S.
		Centers for Disease Control and Prevention (CDC)."); ?>
			</small>
			<br>
			<small class="text-muted"><a href="javascript:void(0);" onclick="clearCache();" style="font-size:0.8em;"><?= _translate("Clear Cache"); ?></a></small>
		</div>
		<div class=" col-lg-4 col-sm-4">
			<?php $commitShaShort = $general->getCommitShaShort(); ?>
			<small class="pull-right" style="font-weight:bold;">
				&nbsp;&nbsp;<?= "v" . VERSION; ?><?php if ($commitShaShort): ?> <span class="text-muted" style="font-weight:normal;">(<?= htmlspecialchars($commitShaShort, ENT_QUOTES, 'UTF-8'); ?>)</span><?php endif; ?>
			</small>
			<?php

			if (!empty($remoteURL) && isset($_SESSION['userName']) && $general->isLISInstance()) { ?>

				<small class="pull-right">
					<a href="javascript:receiveMetaData();">
						<?= _translate("Force Remote Sync"); ?>
					</a>&nbsp;&nbsp;
				</small>

			<?php
			}
			?>
			<br>
			<span class="syncHistoryDiv" style="float:right;font-size:x-small;" class="pull-right">
				<span class="text-muted"><?= $general->getInstanceName() ?></span>
				<span class="text-muted" style="<?= $syncHistoryDisplay ?>">
					| <?= _translate("Last synced at") . ' ' . $syncLatestTime; ?>
				</span>

			</span>
		</div>
	</div>
	<?php if ($supportEmail !== '' && $supportEmail !== '0') { ?>
		<small>
			<a href="javascript:void(0);" onclick="showModal('/support/index.php?fUrl=<?php echo htmlspecialchars($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>', 900, 520);">Support</a>
		</small>
	<?php } ?>
</footer>
</div>

<?php require_once WEB_ROOT . '/assets/js/main.js.php'; ?>
<?php require_once WEB_ROOT . '/assets/js/dates.js.php'; ?>
<script type="text/javascript"
	src="/assets/js/spotlight-search.js?v=<?= filemtime(WEB_ROOT . '/assets/js/spotlight-search.js'); ?>"></script>

<script type="text/javascript">
	$(document).ready(function() {
		<?php
		$alertMsg = $_SESSION['alertMsg'] ?? '';
		if ($alertMsg !== '') {
		?>
			alert("<?= $alertMsg; ?>");
		<?php
			unset($_SESSION['alertMsg']);
		}
		unset($_SESSION['alertMsg']);

		$isLogged = $_SESSION['logged'] ?? '';
		if ($isLogged !== '') { ?>
			setCrossLogin();
		<?php } ?>

	});


	<?php
	if (!empty($arr['display_encrypt_pii_option']) && $arr['display_encrypt_pii_option'] == "yes") {
	?>
		$('.encryptPIIContainer').show();
	<?php
	} else {
	?>
		$('.encryptPIIContainer').hide();
	<?php
	}
	?>
</script>

<?php // Uniform "reason for result/rejection change" capture across every single-result test form. ?>
<script type="text/javascript">
	(function () {
		var CHANGE_REASON_LABEL = <?= json_encode(_htmlTranslate('Reason For Change in Result / Rejection Status')) ?>;
		var CHANGE_REASON_PLACEHOLDER = <?= json_encode(_htmlTranslate('Please enter the reason for this change')) ?>;

		function initChangeReasonCapture() {
			$('form').each(function () {
				var $form = $(this);
				// Only result-entry / edit forms expose the rejection selector.
				if (!$form.find('#isSampleRejected').length) return;
				// TB multi-test uses its own per-test reason fields.
				if ($form.find('[name="testResult[reasonForChange][]"]').length) return;
				// Rollout gate: VL first. Widen this to other test types' result fields
				// (#cd4Result, [name="result"], ...) after each is validated in-browser.
				if (!$form.find('#vlResult').length) return;
				if ($form.data('changeReasonInit')) return;
				$form.data('changeReasonInit', true);

				// Neutralize any bespoke inline reason field so only the injected one submits / is required.
				$form.find('[name="reasonForResultChanges"], [name="reasonForChanging"]').each(function () {
					$(this).removeAttr('name').removeAttr('id').prop('disabled', true).removeClass('isRequired')
						.closest('.reasonForResultChanges, .change-reason').removeClass('reasonForResultChanges change-reason').hide();
				});

				// Inject the single standard mandatory reason field just above the Save button.
				var $section = $(
					'<div class="row changeReasonSection" style="display:none;margin:10px 0;">' +
						'<div class="col-md-12">' +
							'<label class="control-label">' + CHANGE_REASON_LABEL + ' <span class="mandatory">*</span></label>' +
							'<textarea class="form-control" name="reasonForResultChanges" id="reasonForResultChanges" rows="2" ' +
								'placeholder="' + CHANGE_REASON_PLACEHOLDER + '" title="' + CHANGE_REASON_PLACEHOLDER + '" style="width:100%;"></textarea>' +
						'</div>' +
					'</div>'
				);
				var $save = $form.find('a[onclick*="validateNow"], button[type="submit"], input[type="submit"]').first();
				var $footer = $save.closest('.box-footer');
				if ($footer.length) { $footer.before($section); }
				else if ($save.length) { $save.before($section); }
				else { $form.append($section); }

				var $reason = $section.find('#reasonForResultChanges');
				// Fields whose change requires a reason: result, rejection status, and final interpretation.
				var watch = '#vlResult, #cd4Result, [name="result"], [name="cd4Result"], [name="vlResult"], ' +
					'.result-fields, .specialResults, #isSampleRejected, #rejectionReason, ' +
					'[name="resultInterpretation"], [name^="resultInterpretation"], [name="finalResultInterpretation"]';

				function valOf(el) {
					return (el.type === 'checkbox' || el.type === 'radio') ? (el.checked ? el.value : '') : ($(el).val() || '');
				}

				// Baseline of each watched field at load. A reason is required only when a field that
				// ALREADY had a value gets changed -- so first-time result entry / new requests don't trigger.
				var baseline = [];
				function captureBaseline() {
					baseline = $form.find(watch).map(function () { return { el: this, val: valOf(this) }; }).get();
				}
				function existingValueChanged() {
					for (var i = 0; i < baseline.length; i++) {
						var b = baseline[i];
						if (b.val !== '' && document.body.contains(b.el) && valOf(b.el) !== b.val) return true;
					}
					return false;
				}

				var ready = false;
				function check() {
					if (!ready) return;
					if (existingValueChanged()) {
						$section.show();
						$reason.addClass('isRequired');
					} else {
						$section.hide();
						$reason.removeClass('isRequired');
					}
				}

				$form.on('change keyup', watch, function () { setTimeout(check, 0); });
				// Capture the baseline only after the form's own on-load triggers have settled.
				setTimeout(function () { captureBaseline(); ready = true; check(); }, 600);
			});
		}

		if (window.jQuery) { jQuery(initChangeReasonCapture); }
	})();
</script>
</body>

</html>