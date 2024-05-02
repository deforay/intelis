<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\SystemService;
use App\Utilities\MiscUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var SystemService $systemService */
$systemService = ContainerRegistry::get(SystemService::class);

$supportEmail = trim((string) $general->getGlobalConfig('support_email'));


if (_isAllowed("sync-history.php")) {
	$syncHistory = "/common/reference/sync-history.php";
} else {
	$syncHistory = "javascript:void(0);";
}

$syncLatestTime = $general->getLastRemoteSyncDateTime();

if (empty($syncLatestTime)) {
	$syncHistoryDisplay = "display:none;";
} else {
	$syncHistoryDisplay = "display:inline;";
}

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

			<small class="pull-right" style="font-weight:bold;">&nbsp;&nbsp;
				<?php echo "v" . VERSION; ?>
			</small>
			<?php

			if (!empty(SYSTEM_CONFIG['remoteURL']) && isset($_SESSION['userName']) && $general->isLISInstance()) { ?>

				<small class="pull-right">
					<a href="javascript:syncRemoteData();">
						<?= _translate("Force Remote Sync"); ?>
					</a>&nbsp;&nbsp;
				</small>

			<?php
			}
			?>
			<br>
			<span class="syncHistoryDiv" style="float:right;font-size:x-small;<?= $syncHistoryDisplay ?>" class="pull-right">
				<a href="<?= $syncHistory; ?>" class="text-muted">
					<?= _translate("Last synced at"); ?>
					<span class="lastSyncDateTime">
						<?= $syncLatestTime; ?>
					</span>
				</a>
			</span>
		</div>
	</div>
	<?php if (!empty($supportEmail)) { ?>
		<small><a href="javascript:void(0);" onclick="showModal('/support/index.php?fUrl=<?php echo htmlspecialchars($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>', 900, 520);">Support</a></small>
	<?php } ?>


</footer>
</div>

<script type="text/javascript" src="/assets/js/toastify.js"></script>
<script type="text/javascript" src="/assets/js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript" src="/assets/js/js.cookie.js"></script>
<script type="text/javascript" src="/assets/js/select2.min.js"></script>
<script type="text/javascript" src="/assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="/assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="/assets/plugins/datatables/dataTables.bootstrap.min.js"></script>
<script type="text/javascript" src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript" src="/assets/js/dayjs.min.js"></script>
<script type="text/javascript" src="/assets/js/dayjs.customParseFormat.js"></script>
<script type="text/javascript" src="/assets/js/dayjs.utc.js"></script>
<script type="text/javascript" src="/assets/js/dayjs.timezone.js"></script>
<script type="text/javascript" src="/assets/js/app.min.js"></script>
<script type="text/javascript" src="/assets/js/deforayValidation.js"></script>
<script type="text/javascript" src="/assets/js/jquery.maskedinput.js"></script>
<script type="text/javascript" src="/assets/js/jquery.blockUI.js"></script>
<script src="/assets/js/highcharts.js"></script>
<script src="/assets/js/highcharts-exporting.js"></script>
<script src="/assets/js/highcharts-offline-exporting.js"></script>
<script src="/assets/js/highcharts-accessibility.js"></script>
<?php require_once(WEB_ROOT . '/assets/js/main.js.php'); ?>
<?php require_once(WEB_ROOT . '/assets/js/dates.js.php'); ?>

<script type="text/javascript">
	Highcharts.setOptions({
		chart: {
			style: {
				fontFamily: 'Arial', // Set global font family (optional)
				fontSize: '16px' // Set global font size
			}
		},
		exporting: {
			buttons: {
				contextButton: {
					menuItems: [
						"viewFullscreen",
						"printChart",
						"separator",
						"downloadPNG",
						"downloadJPEG",
						"downloadSVG"
					]
				}
			}
		}
	});

	let remoteSync = false;
	let globalDayjsDateFormat = '<?= $systemService->getDateFormat('dayjs'); ?>';
	let systemTimezone = '<?= $_SESSION['APP_TIMEZONE'] ?? 'UTC'; ?>';

	window.additionalXHRParams = {
		layout: 0,
		'X-CSRF-Token': '<?php echo $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? MiscUtility::generateUUID(); ?>'
	};

	$.ajaxSetup({
		headers: window.additionalXHRParams
	});

	function setCrossLogin() {
		StorageHelper.storeInSessionStorage('crosslogin', 'true');
	}
	<?php if (!empty(trim((string) SYSTEM_CONFIG['remoteURL'])) && $general->isLISInstance()) { ?>
		remoteSync = true;

		function syncRemoteData() {
			if (!navigator.onLine) {
				alert("<?= _translate("Please connect to internet to sync with STS", escapeText: true); ?>");
				return false;
			}

			if (remoteSync) {
				$.blockUI({
					message: "<h3><?= _translate("Preparing for STS sync.", escapeText: true); ?><br><?= _translate("Please wait...", escapeText: true); ?></h3>"
				});
				$.ajax({
						url: "/scheduled-jobs/remote/sts-metadata-receiver.php",
					})
					.done(function(data) {
						console.log("Metadata Synced | STS -> LIS");
						$.unblockUI();
					})
					.fail(function() {
						$.unblockUI();
						alert("<?= _translate("Unable to do STS Sync. Please contact technical team for assistance.", escapeText: true); ?>");
					})
					.always(function() {
						syncResults();
					});
			}
		}

		function syncResults() {

			$.blockUI({
				message: "<h3><?= _translate("Trying to sync Test Results", escapeText: true); ?><br><?= _translate("Please wait...", escapeText: true); ?></h3>"
			});

			if (remoteSync) {
				$.ajax({
						url: "/scheduled-jobs/remote/results-sender.php",
					})
					.done(function(data) {
						console.log("Results Synced | LIS -> STS");
						$.unblockUI();
					})
					.fail(function() {
						$.unblockUI();
						alert("<?= _translate("Unable to do STS Sync. Please contact technical team for assistance.", escapeText: true); ?>");
					})
					.always(function() {
						syncRequests();
					});
			}
		}


		function syncRequests() {
			$.blockUI({
				message: "<h3><?= _translate("Trying to sync Test Requests", escapeText: true); ?><br><?= _translate("Please wait...", escapeText: true); ?></h3>"
			});

			if (remoteSync) {
				$.ajax({
						url: "/scheduled-jobs/remote/requests-receiver.php",
					})
					.done(function(data) {
						console.log("Requests Synced | STS -> LIS");
						$.unblockUI();
					})
					.fail(function() {
						$.unblockUI();
						alert("<?= _translate("Unable to do STS Sync. Please contact technical team for assistance.", escapeText: true); ?>");
					});
			}
		}
	<?php } ?>



	function screenshot(supportId, attached) {
		if (supportId != "" && attached == 'yes') {
			closeModal();
			html2canvas(document.querySelector("#lis-body")).then(canvas => {
				dataURL = canvas.toDataURL();
				$.blockUI();
				$.post("/support/saveScreenshot.php", {
						image: dataURL,
						supportId: supportId
					},
					function(data) {
						$.unblockUI();
						alert("<?= _translate("Thank you.Your message has been submitted.", escapeText: true); ?>");
					});
			});
		} else {
			closeModal();
			$.blockUI();
			$.post("/support/saveScreenshot.php", {
					supportId: supportId
				},
				function(data) {
					$.unblockUI();
					alert("<?= _translate("Thank you.Your message has been submitted.", escapeText: true); ?>");
				});
		}
	}

	$(document).ready(function() {

		$(".allMenu").removeClass('active');

		let url = window.location.pathname + window.location.search;
		let currentMenuItem = $('a[href="' + url + '"]');

		if (!currentMenuItem.length) {
			let currentPaths = Utilities.splitPath(url).map(path => btoa(path));
			currentMenuItem = $('a[data-inner-pages]').filter(function() {
				return currentPaths.some(path => $(this).data('inner-pages').split(';').includes(path));
			});
		}

		if (currentMenuItem.length) {
			currentMenuItem.parent().addClass('active');
			let treeview = currentMenuItem.parents('li.treeview').addClass('active')[0];
			let offset = treeview ? treeview.offsetTop : 0;
			if (offset > 200) {
				$('.main-sidebar').scrollTop(offset);
			}
		}

		if (remoteSync) {
			(function getLastRemoteSyncDateTime() {
				let currentDateTime = new Date();
				$.ajax({
					url: '/scheduled-jobs/remote/get-last-sts-sync-datetime.php',
					cache: false,
					success: function(lastSyncDateString) {
						if (lastSyncDateString != null && lastSyncDateString != undefined) {
							$('.lastSyncDateTime').html(lastSyncDateString);
							$('.syncHistoryDiv').show();
						}
					},
					error: function(data) {}
				});
				setTimeout(getLastRemoteSyncDateTime, 15 * 60 * 1000);
			})();

			// Every 5 mins check if STS is reachable
			(function checkSTSConnection() {
				if (<?= empty(trim((string) SYSTEM_CONFIG['remoteURL'])) ? 1 : 0 ?>) {
					$('.is-remote-server-reachable').hide();
				} else {
					$.ajax({
						url: '<?= SYSTEM_CONFIG['remoteURL']; ?>' + '/api/version.php',
						cache: false,
						success: function(data) {
							$('.is-remote-server-reachable').fadeIn(1000);
							$('.is-remote-server-reachable').css('color', '#4dbc3c');
						},
						error: function() {
							$('.is-remote-server-reachable').fadeIn(1000);
							$('.is-remote-server-reachable').css('color', 'red');
						}
					});
				}
				setTimeout(checkSTSConnection, 15 * 60 * 1000);
			})();
		}

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
		if ($isLogged !== '') {
		?>
			setCrossLogin();
		<?php }
		// if instance facility name is not set, let us show the modal

		if (empty($_SESSION['instance']['facilityName']) || ($general->isLISInstance() && $_SESSION['instance']['labId'] == null)) {
		?>
			showModal('/new-instance.php', 900, 420);
		<?php } ?>

		let phoneNumberDebounceTimeout;
		const countryCode = "<?= $countryCode ?? ''; ?>";

		$('.phone-number').on('input', function() {
			clearTimeout(phoneNumberDebounceTimeout);
			let inputElement = $(this);
			let phoneNumber = inputElement.val().trim();

			phoneNumberDebounceTimeout = setTimeout(function() {
				phoneNumber = inputElement.val().trim();

				if (phoneNumber === countryCode || phoneNumber === "") {
					inputElement.val("");
					return;
				}

				phoneNumber = phoneNumber.replace(/[^0-9+]/g, ''); // Remove non-numeric and non-plus characters
				inputElement.val(phoneNumber);

				$.ajax({
					type: 'POST',
					url: '/includes/validatePhoneNumber.php',
					data: {
						phoneNumber: phoneNumber
					},
					success: function(response) {
						if (!response.isValid) {
							Toastify({
								text: "<?= _translate('Invalid phone number. Please enter full phone number with the proper country code', escapeText: true) ?>",
								duration: 3000,
								style: {
									background: 'red'
								}
							}).showToast();
						}
					},
					error: function() {
						console.error("An error occurred while validating the phone number.");
					}
				});
			}, 700);
		});

		$('.phone-number').on('focus', function() {
			let phoneNumber = $(this).val().trim();
			if (phoneNumber === "") {
				$(this).val(countryCode);
			}
		});

		$('.phone-number').on('blur', function() {
			let phoneNumber = $(this).val().trim();
			if (phoneNumber === countryCode || phoneNumber === "") {
				$(this).val("");
			}
		});

		$('.patientId').on('change', function() {
			var patientId = $(this).val();

			var minLength = '<?= $minPatientIdLength ?? 0; ?>';

			if (patientId.length < minLength) {
				$(".lengthErr").remove();
				var txt = "<?= _translate('Please enter minimum length for Patient Id : ', escapeText: true); ?>" + minLength;
				$(this).parent().append('<span class="lengthErr" style="color:red;">' + txt + '</span>');
			} else {
				$(".lengthErr").remove();
			}


		});
	});

	function editableSelect(id, _fieldName, table, _placeholder) {
		$("#" + id).select2({
			placeholder: _placeholder,
			minimumInputLength: 0,
			width: '100%',
			allowClear: true,
			id: function(bond) {
				return bond._id;
			},
			ajax: {
				placeholder: "<?= _translate("Type one or more character to search", escapeText: true); ?>",
				url: "/includes/get-data-list-for-generic.php",
				dataType: 'json',
				delay: 250,
				data: function(params) {
					return {
						fieldName: _fieldName,
						tableName: table,
						q: params.term, // search term
						page: params.page
					};
				},
				processResults: function(data, params) {
					params.page = params.page || 1;
					return {
						results: data.result,
						pagination: {
							more: (params.page * 30) < data.total_count
						}
					};
				},
				//cache: true
			},
			escapeMarkup: function(markup) {
				return markup;
			}
		});
	}
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

	function formatStringToSnakeCase(inputStr) {
		// Remove special characters except underscore
		var result = inputStr.replace(/[^a-zA-Z0-9_ ]/g, '');
		// Convert to lowercase
		result = result.toLowerCase();
		// Replace spaces with underscore
		return result.replace(/ /g, '_');
	}

	function clearCache() {
		$.ajax({
			url: '/includes/clear-cache.php',
			cache: false,
			success: function(data) {
				Toastify({
					text: "<?= _translate('Cache cleared successfully', escapeText: true) ?>",
					duration: 3000,
					style: {
						background: 'green'
					}
				}).showToast();
			},
			error: function() {
				console.error("An error occurred while clearing the cache.");
			}
		});
	}
</script>
</body>

</html>
