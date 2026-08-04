<?php

use App\Utilities\TurnaroundTimeUtility;

/**
 * "How is Turnaround Time (TAT) calculated?" reference for the per-module
 * Sample Status pages.
 *
 * Rendered as a small muted link that opens a modal, so the explanation is
 * there when someone wants it and takes up no room on the page when they
 * don't.
 *
 * Include it inside the page's .row, after the charts:
 *   require APPLICATION_PATH . '/includes/turnaround-time-methodology.php';
 */
?>
<div class="col-xs-12" style="margin-bottom:15px;">
	<a href="#" data-toggle="modal" data-target="#tatMethodologyModal" style="font-size:12px;color:#999;text-decoration:none;">
		<em class="fa-solid fa-circle-question"></em>&nbsp;<?php echo _translate("How is Turnaround Time (TAT) calculated?"); ?>
	</a>
</div>

<div class="modal fade" id="tatMethodologyModal" tabindex="-1" role="dialog" aria-labelledby="tatMethodologyModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="<?php echo _translate('Close'); ?>"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="tatMethodologyModalLabel">
					<?php echo _translate("How is Turnaround Time (TAT) calculated?"); ?>
				</h4>
			</div>
			<div class="modal-body">
				<p>
					<?php echo _translate("Turnaround time is the number of days between two milestones in a sample's journey. The chart plots the average for each milestone pair, month by month."); ?>
				</p>
				<table aria-describedby="table" class="table table-condensed" aria-hidden="true">
					<thead>
						<tr>
							<th style="width:32%;">
								<?php echo _translate("Measure"); ?>
							</th>
							<th>
								<?php echo _translate("Calculated as"); ?>
							</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach (TurnaroundTimeUtility::methodology() as $stage) { ?>
							<tr>
								<td><?php echo $stage['label']; ?></td>
								<td><?php echo $stage['description']; ?></td>
							</tr>
						<?php } ?>
					</tbody>
				</table>
				<p><strong><?php echo _translate("Points to note"); ?></strong></p>
				<ul>
					<?php foreach (TurnaroundTimeUtility::methodologyNotes() as $note) { ?>
						<li><?php echo $note; ?></li>
					<?php } ?>
				</ul>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default btn-sm" data-dismiss="modal">
					<?php echo _translate("Close"); ?>
				</button>
			</div>
		</div>
	</div>
</div>
