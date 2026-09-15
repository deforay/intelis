<?php

/**
 * The tab bar of a clinic report page, drawn from ClinicReportUtility::tabs()
 * so the pages and Spotlight list the same tabs. Set $crTestType first.
 *
 * @var string $crTestType
 */

use App\Utilities\ClinicReportUtility;

?>
<ul id="myTab" class="nav nav-tabs clinic-tabs">
    <?php foreach (ClinicReportUtility::tabs($crTestType) as $crIndex => $crTab) { ?>
        <li<?= $crIndex === 0 ? ' class="active"' : ''; ?>><a href="#<?= $crTab['pane']; ?>" data-toggle="tab" data-tab-name="<?= $crTab['name']; ?>"><?= htmlspecialchars($crTab['label'], ENT_QUOTES); ?></a></li>
    <?php } ?>
</ul>
