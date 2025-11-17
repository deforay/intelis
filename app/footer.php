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
			<small class="pull-right" style="font-weight:bold;">&nbsp;&nbsp;
				<?= "v" . VERSION; ?>
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
</body>

</html>