<?php

use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$supportEmail = trim((string) $general->getGlobalConfig('support_email'));

?>

<footer class="main-footer">

	<small>
		<?= _translate("This project is supported by the U.S. President's Emergency Plan for AIDS Relief (PEPFAR) through the U.S.
		Centers for Disease Control and Prevention (CDC)."); ?>
	</small>
	<?php if (!empty($supportEmail)) { ?>
		<small><a href="javascript:void(0);" onclick="showModal('/support/index.php?fUrl=<?php echo htmlspecialchars($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>', 900, 520);">Support</a></small>
	<?php } ?>
	<small class="pull-right" style="font-weight:bold;">&nbsp;&nbsp;
		<?php echo "v" . VERSION; ?>
	</small>
	<?php

	if (!empty(SYSTEM_CONFIG['remoteURL']) && isset($_SESSION['userName']) && isset($_SESSION['instanceType']) && ($_SESSION['instanceType'] == 'vluser')) { ?>
		<div class="pull-right">
			<small>
				<a href="javascript:syncRemoteData();">
					<?= _translate("Force Remote Sync"); ?>
				</a>&nbsp;&nbsp;
			</small>
		</div>
	<?php
	}
	$lastSync = '';

	if (_isAllowed("sync-history.php")) {
		$syncHistory = "/common/reference/sync-history.php";
	} else {
		$syncHistory = "javascript:void(0);";
	}

	$syncLatestTime = $general->getLastSyncDateTime();

	if (empty($syncLatestTime)) {
		$syncHistoryDisplay = "display:none;";
	} else {
		$syncHistoryDisplay = "display:inline;";
	}

	?>
	<br>
	<div class="syncHistoryDiv" style="float:right;font-size:x-small;<?= $syncHistoryDisplay ?>" class="pull-right">
		<a href="<?= $syncHistory; ?>" class="text-muted">
			<?= _translate("Last synced at"); ?>
			<span class="lastSyncDateTime">
				<?= $syncLatestTime; ?>
			</span>
		</a>
	</div>
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
<script type="text/javascript" src="/assets/js/dayjs.min.js?v=<?= filemtime(WEB_ROOT . "/assets/js/dayjs.min.js") ?>"></script>
<script type="text/javascript" src='/assets/js/main.js?v=<?= filemtime(WEB_ROOT . "/assets/js/main.js") ?>'></script>
<script type="text/javascript" src="/assets/js/app.min.js"></script>
<script type="text/javascript" src="/assets/js/deforayValidation.js"></script>
<script type="text/javascript" src="/assets/js/jquery.maskedinput.js"></script>
<script type="text/javascript" src="/assets/js/jquery.blockUI.js"></script>


<script type="text/javascript">
	let remoteSync = false;

	window.additionalXHRParams = {
		layout: 0,
		'X-CSRF-Token': '<?php echo $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? $general->generateUUID(); ?>'
	};

	$.ajaxSetup({
		headers: window.additionalXHRParams
	});

	function setCrossLogin() {
		StorageHelper.storeInSessionStorage('crosslogin', 'true');
	}
	<?php if (!empty(trim((string) SYSTEM_CONFIG['remoteURL'])) && isset($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'vluser') { ?>
		remoteSync = true;

		function syncRemoteData() {
			if (!navigator.onLine) {
				alert("<?= _translate("Please connect to internet to sync with STS", true); ?>");
				return false;
			}

			if (remoteSync) {
				$.blockUI({
					message: "<h3><?= _translate("Preparing for STS sync.", true); ?><br><?= _translate("Please wait..."); ?></h3>"
				});
				$.ajax({
						url: "/scheduled-jobs/remote/commonDataSync.php",
					})
					.done(function(data) {
						console.log("Common Data Synced | STS -> LIS");
						$.unblockUI();
					})
					.fail(function() {
						$.unblockUI();
						alert("<?= _translate("Unable to do STS Sync. Please contact technical team for assistance.", true); ?>");
					})
					.always(function() {
						syncResults();
					});
			}
		}

		function syncResults() {

			$.blockUI({
				message: "<h3><?= _translate("Trying to sync Test Results", true); ?><br><?= _translate("Please wait..."); ?></h3>"
			});

			if (remoteSync) {
				$.ajax({
						url: "/scheduled-jobs/remote/resultsSync.php",
					})
					.done(function(data) {
						console.log("Results Synced | LIS -> STS");
						$.unblockUI();
					})
					.fail(function() {
						$.unblockUI();
						alert("<?= _translate("Unable to do STS Sync. Please contact technical team for assistance.", true); ?>");
					})
					.always(function() {
						syncRequests();
					});
			}
		}


		function syncRequests() {
			$.blockUI({
				message: "<h3><?= _translate("Trying to sync Test Requests", true); ?><br><?= _translate("Please wait..."); ?></h3>"
			});

			if (remoteSync) {
				$.ajax({
						url: "/scheduled-jobs/remote/requestsSync.php",
					})
					.done(function(data) {
						console.log("Requests Synced | STS -> LIS");
						$.unblockUI();
					})
					.fail(function() {
						$.unblockUI();
						alert("<?= _translate("Unable to do STS Sync. Please contact technical team for assistance."); ?>");
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
						alert("<?= _translate("Thank you.Your message has been submitted."); ?>");
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
					alert("<?= _translate("Thank you.Your message has been submitted.", true); ?>");
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
			(function getLastSyncDateTime() {
				let currentDateTime = new Date();
				$.ajax({
					url: '/scheduled-jobs/remote/getLastSyncTime.php',
					cache: false,
					success: function(lastSyncDateString) {
						if (lastSyncDateString != null && lastSyncDateString != undefined) {
							$('.lastSyncDateTime').html(lastSyncDateString);
							$('.syncHistoryDiv').show();
						}
					},
					error: function(data) {}
				});
				setTimeout(getLastSyncDateTime, 15 * 60 * 1000);
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

		if (empty($_SESSION['instanceFacilityName'])) {
		?> showModal('/addInstanceDetails.php', 900, 420);
		<?php } ?>

		$('.phone-number').on('change blur', function() {
			var phoneNumber = $(this).val();

			$.ajax({
				type: 'POST',
				url: '/includes/validatePhoneNumber.php',
				data: {
					phoneNumber: phoneNumber
				},
				success: function(response) {
					if (!response.isValid) {
						Toastify({
							text: "<?= _translate('Invalid phone number. Please enter full phone number with the proper country code', true) ?>",
							duration: 3000,
							style: {
								background: 'red',
							}
						}).showToast();
					}
				},
				error: function() {
					console.error("An error occurred while validating the phone number.");
				}
			});
		});

		$('.phone-number').on('focus', function() {
			if ($(this).val() == "") {
				$(this).val("<?php echo $countryCode ?? null; ?>")
			};
		});

		$('.patientId').on('change', function() {
			var patientId = $(this).val();

			var minLength = '<?= $minPatientIdLength ?? 0; ?>';

			if (patientId.length < minLength) {
				$(".lengthErr").remove();
				var txt = "<?= _translate('Please enter minimum length for Patient Id : ', true); ?>" + minLength;
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
				placeholder: "<?= _translate("Type one or more character to search", true); ?>",
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
</script>
</body>

</html>
