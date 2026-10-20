<?php

declare(strict_types=1);

use App\Services\TbPoolService;
use App\Registries\ContainerRegistry;

/**
 * The pooling choice on a TB batch's position page. Samples are pooled in the order
 * of the list above it, and the list shows the pool each sample goes in.
 */

/** @var array<string, mixed> $batchInfo */
/** @var int|string $id */

/** @var TbPoolService $tbPoolService */
$tbPoolService = ContainerRegistry::get(TbPoolService::class);

$poolAttributes = json_decode((string) ($batchInfo['batch_attributes'] ?? ''), true);
$poolSize = (int) (is_array($poolAttributes) ? ($poolAttributes['pool_size'] ?? 0) : 0);
$poolsLocked = $tbPoolService->isBatchLocked($batchInfo);
// Fixed pools are shown as they are stored, whatever the order of the list.
$storedPools = [];
if ($poolsLocked) {
    foreach ($tbPoolService->poolCodesOfBatch((int) $id, (string) $batchInfo['batch_code']) as $tbId => $poolCode) {
        $storedPools['s_' . $tbId] = $poolCode;
    }
}
$runAloneIds = array_map(
    static fn(int $tbId): string => 's_' . $tbId,
    array_keys($tbPoolService->runAloneIds((int) $id, (string) $batchInfo['batch_code']))
);
?>
<style>
	#sortableRow li[data-pool]::after {
		content: "  |  " attr(data-pool);
		font-weight: normal;
		color: #31708f;
	}
</style>
<div class="row" style="margin-bottom:20px;">
	<div class="col-lg-6 col-lg-offset-3">
		<label for="poolSize"><?= _translate("GeneXpert sputum pooling"); ?></label>
		<select class="form-control" name="poolSize" id="poolSize" <?= $poolsLocked ? 'disabled' : ''; ?>>
			<option value="0" <?= $poolSize < 2 ? "selected='selected'" : ''; ?>><?= _translate("Do not pool"); ?></option>
			<?php foreach (range(2, TbPoolService::MAX_POOL_SIZE) as $size) { ?>
				<option value="<?= $size; ?>" <?= $poolSize === $size ? "selected='selected'" : ''; ?>>
					<?= sprintf(_translate("%d samples per pool"), $size); ?>
				</option>
			<?php } ?>
		</select>
		<small class="help-block">
			<?php if ($poolsLocked) {
				echo _translate("The worksheet with these pools was printed, or a pool has a result, so the pools cannot be changed.");
			} else {
				echo _translate("Samples are pooled in the order of the list. Scan the pool code from the worksheet as the cartridge's sample ID. A sample that was in a pool before is tested on its own.");
			} ?>
		</small>
	</div>
</div>
<script>
	(function () {
		const runAlone = new Set(<?= json_encode($runAloneIds); ?>);
		const storedPools = <?= json_encode((object) $storedPools); ?>;
		const locked = <?= json_encode($poolsLocked); ?>;
		const individually = <?= json_encode(_translate("Test individually")); ?>;
		const poolLabel = <?= json_encode(_translate("Pool")); ?>;

		// Fixed pools show their stored codes. Otherwise this mirrors
		// TbPoolService::group(): pools in list order, controls and samples pooled
		// before left out, and a last pool of one tested on its own.
		window.labelTbPools = function () {
			const size = parseInt($("#poolSize").val(), 10) || 0;
			const samples = $("#sortableRow li").filter(function () {
				return this.id.indexOf("s_") === 0;
			});
			samples.removeAttr("data-pool");
			if (locked) {
				samples.each(function () {
					this.setAttribute("data-pool", storedPools[this.id] || individually);
				});
				return;
			}
			if (size < 2) {
				return;
			}
			let pool = 1;
			let current = [];
			samples.each(function () {
				if (runAlone.has(this.id)) {
					this.setAttribute("data-pool", individually);
					return;
				}
				current.push(this);
				this.setAttribute("data-pool", poolLabel + " " + pool);
				if (current.length === size) {
					pool++;
					current = [];
				}
			});
			if (current.length === 1) {
				current[0].setAttribute("data-pool", individually);
			}
		};

		$(function () {
			$("#poolSize").on("change", window.labelTbPools);
			$("#sortableRow").on("sortupdate", window.labelTbPools);
			window.labelTbPools();
		});
	})();
</script>
