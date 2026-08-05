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
<?php require_once APPLICATION_PATH . '/_spotlight.php'; ?>

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
				// Activate only on single-result test forms (VL/CD4/EID/COVID/hepatitis). Generic
				// (sub-test results) and TB (per-test fields) are intentionally excluded.
				if (!$form.find('#vlResult, #cd4Result, [name="result"], [name="HBsAg"], [name="antiHcv"]').length) return;
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
					'[name="HBsAg"], [name="antiHcv"], .result-fields, .specialResults, #isSampleRejected, #rejectionReason, ' +
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

<?php // Rejection reason is mandatory whenever a sample (or a single test) is marked rejected. ?>
<script type="text/javascript">
	(function () {
		var REASON_TITLE = <?= json_encode(_htmlTranslate('Please select the reason for sample rejection')) ?>;
		var SPECIFY_TITLE = <?= json_encode(_htmlTranslate('Please enter the reason for sample rejection')) ?>;

		// Rejection toggles across the modules. Sample level is #isSampleRejected
		// (VL / EID / COVID-19 / CD4 / Hepatitis / TB / Custom Tests) or #gtSampleRejected
		// (Custom Tests sample outcome); per-test cards suffix the id with the card number.
		var TOGGLES = '[id^="isSampleRejected"], #gtSampleRejected';
		var REASONS = '#rejectionReason, [id^="sampleRejectionReason"], #gtSampleRejectionReason';

		function byId(id) {
			var el = id ? document.getElementById(id) : null;
			return el ? $(el) : $();
		}

		// The reason field governed by a given rejection toggle.
		function reasonFor($toggle) {
			var id = $toggle.attr('id') || '';
			if (id === 'gtSampleRejected') return byId('gtSampleRejectionReason');
			if (id.indexOf('isSampleRejected') !== 0) return $();
			var n = id.slice('isSampleRejected'.length);
			var $reason = byId('rejectionReason' + n);
			return $reason.length ? $reason : byId('sampleRejectionReason' + n);
		}

		// Free-text box behind the "Other (Please Specify)" option, where a form offers it.
		function specifyFor($reason, n) {
			var $box = byId('newRejectionReason' + n);
			return $box.length ? $box : $reason.closest('div, td').find('.newRejectionReason').first();
		}

		// A field the user must fill has to be reachable. Only inline-hidden wrappers are
		// re-shown, so stylesheet-driven layout (table cells and the like) stays intact.
		function inlineHidden() {
			return this.style && this.style.display === 'none';
		}

		function reveal($field) {
			if (!$field.length || $field.is(':visible')) return;
			$field.parentsUntil('form').addBack().filter(inlineHidden).show();
			// Table layouts hide the label cell separately from the input cell.
			$field.closest('tr').children().filter(inlineHidden).show();
		}

		function hasChoices($reason) {
			if (!$reason.is('select')) return true;
			return $reason.find('option').filter(function () { return this.value !== ''; }).length > 0;
		}

		function markMandatory($field, on) {
			var id = $field.attr('id');
			if (!id) return;
			var $label = $('label[for="' + id + '"]');
			if (!$label.length) return;
			if (on) {
				if (!$label.find('.mandatory').length) {
					$label.append(' <span class="mandatory rejectionReasonStar">*</span>');
				}
			} else {
				$label.find('.rejectionReasonStar').remove();
			}
		}

		function sync($toggle) {
			var $reason = reasonFor($toggle);
			if (!$reason.length) return;
			var id = $toggle.attr('id') || '';
			var n = (id.indexOf('isSampleRejected') === 0) ? id.slice('isSampleRejected'.length) : '';
			var rejected = String($toggle.val() || '').toLowerCase() === 'yes';
			var $specify = specifyFor($reason, n);

			markMandatory($reason, rejected);

			// Not rejected, a field the user cannot edit (read-only lab section), or a list
			// with nothing to pick (no rejection reasons configured for this test yet):
			// none of these may block the save.
			if (!rejected || $reason.prop('disabled') || !hasChoices($reason)) {
				$reason.removeClass('isRequired');
				$specify.removeClass('isRequired');
				return;
			}

			$reason.addClass('isRequired');
			if (!$reason.attr('title')) $reason.attr('title', REASON_TITLE);
			reveal($reason);

			if ($specify.length && String($reason.val()) === 'other') {
				$specify.addClass('isRequired');
				if (!$specify.attr('title')) $specify.attr('title', SPECIFY_TITLE);
				reveal($specify);
			} else {
				$specify.removeClass('isRequired');
			}
		}

		function syncAll() {
			$(TOGGLES).each(function () { sync($(this)); });
		}

		// Delegated, so this runs after each form's own handlers and also covers
		// per-test cards added after page load.
		$(document).on('change', TOGGLES, function () { sync($(this)); });
		$(document).on('change', REASONS, function () { syncAll(); });

		// Let each form's own on-load triggers settle before the first pass.
		$(function () { setTimeout(syncAll, 700); });

		// Last line of defence: whatever a form did to the classes in between,
		// re-apply the rule immediately before the form is validated.
		// deforayValidator is a top-level `let`, so it is a bare global, not a window property.
		if (typeof deforayValidator !== 'undefined' && typeof deforayValidator.validate === 'function') {
			var originalValidate = deforayValidator.validate;
			deforayValidator.validate = function () {
				syncAll();
				return originalValidate.apply(this, arguments);
			};
		}
	})();
</script>
</body>

</html>