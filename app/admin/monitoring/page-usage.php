<?php
use App\Services\UsersService;
use App\Registries\ContainerRegistry;
$title = _translate("Page Usage") . " - " . _translate("Admin");
require_once APPLICATION_PATH . '/header.php';
/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$userNameList = $usersService->getAllUsers(null, null, 'drop-down');
?>
<div class="content-wrapper">
<section class="content-header">
<h1><em class="fa-solid fa-stopwatch"></em> <?= _translate("Page Usage"); ?></h1>
</section>
<section class="content">
<div class="box box-primary">
<div class="box-body">
<p class="text-muted">
<?= _translate("Time counts only while the page is the tab in front of the
user. It is not the length of the login session."); ?>
</p>
<div class="row">
<div class="col-md-3 form-group">
<label for="dateRange"><?= _translate("Date Range"); ?></label>
<input type="text" class="form-control" id="dateRange" readonly />
</div>
<div class="col-md-3 form-group">
<label for="userId"><?= _translate("User"); ?></label>
<select class="form-control" id="userId">
<option value=""><?= _translate("All users"); ?></option>
<?php foreach ($userNameList as $userId => $userName) { ?><option value="<?= htmlspecialchars((string) $userId, ENT_QUOTES,
'UTF-8'); ?>"><?= htmlspecialchars((string) $userName, ENT_QUOTES, 'UTF-8'); ?></option>
<?php } ?>
</select>
</div>
<div class="col-md-3 form-group">
<label for="groupBy"><?= _translate("Group By"); ?></label>
<select class="form-control" id="groupBy">
<option value="user-page"><?= _translate("User and page"); ?>
</option>
<option value="user"><?= _translate("User"); ?></option>
<option value="page"><?= _translate("Page"); ?></option>
</select>
</div>
<div class="col-md-3 form-group">
<label for="search"><?= _translate("Search"); ?></label>
<input type="text" class="form-control" id="search" placeholder="<?=
_translate('User or page'); ?>" />
</div>
</div>
<p id="usageTotals" class="text-muted"></p>
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead>
<tr>
<th><?= _translate("User"); ?></th>
<th><?= _translate("User Role"); ?></th>
<th><?= _translate("Page"); ?></th>
<th><?= _translate("Module"); ?></th>
<th><?= _translate("Page Opens"); ?></th>
<th><?= _translate("Time Spent"); ?></th>
<th><?= _translate("Average Per Open"); ?></th>
<th><?= _translate("Session Hash"); ?></th>
<th><?= _translate("Last Seen"); ?></th>
</tr>
</thead>
<tbody id="usageRows"></tbody>
</table>
</div>
</div>
</div>
</section>
</div>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="<?=
_asset('/assets/plugins/daterangepicker/daterangepicker.js') ?>"></script><script type="text/javascript">
var usageState = { dateRange: '', userId: '', sessHash: '', groupBy: 'user-page', search: '' };
function usageEscape(value) {
    return $('<div/>').text(value == null ? '' : value).html();
}
function loadUsage() {
    $.post('/admin/monitoring/get-page-usage.php', usageState, function (data) {
    var rows = (data && data.rows) || [];
    var html = '';
    rows.forEach(function (row) {
    html += '<tr>' +
    '<td>' + usageEscape(row.userName) + '</td>' +
    '<td>' + usageEscape(row.role) + '</td>' +
    '<td title="' + usageEscape(row.pageUrl) + '">' + usageEscape(row.pageName) +
    '</td>' +
    '<td>' + usageEscape(row.module) + '</td>' +
    '<td>' + usageEscape(row.visits) + '</td>' +
    '<td title="' + usageEscape(row.seconds) + ' seconds">' +
    usageEscape(row.timeSpent) + '</td>' +
    '<td>' + usageEscape(row.average) + '</td>' +
    '<td><a href="#" onclick=filterSessionHash("'+row.sessionHash+'")>' + usageEscape(row.sessionHash) + '</a></td>' +
    '<td>' + usageEscape(row.lastSeen) + '</td>' +
    '</tr>';
    });
    $('#usageRows').html(html || '<tr><td colspan="8"><?= _translate("No page usage recorded for this filter."); ?></td></tr>');
    var totals = (data && data.totals) || {};
    $('#usageTotals').text(
    (totals.users || 0) + ' <?= _translate("users"); ?>, ' +
    (totals.pages || 0) + ' <?= _translate("pages"); ?>, ' +
    (totals.visits || 0) + ' <?= _translate("page opens"); ?>, ' +
    (totals.timeSpent || '-') + ' <?= _translate("in total"); ?>'
    );
    }, 'json');
}
$(document).ready(function () {
    $('#dateRange').daterangepicker({
        locale: { format: 'DD-MMM-YYYY' },
        showDropdowns: true,
        maxDate: moment(),
        startDate: moment().subtract(29, 'days'),
        endDate: moment(),
        ranges: {'Today': [moment(), moment()],
        'Last 7 Days': [moment().subtract(6, 'days'), moment()],
        'Last 30 Days': [moment().subtract(29, 'days'), moment()],
        'This Month': [moment().startOf('month'), moment().endOf('month')]
        }
    }, function (start, end) {
        usageState.dateRange = start.format('DD-MMM-YYYY') + ' to ' + end.format('DD-MMM-YYYY');
        $('#dateRange').val(usageState.dateRange);
        loadUsage();
    });
usageState.dateRange = moment().subtract(29, 'days').format('DD-MMM-YYYY') + ' to ' +
moment().format('DD-MMM-YYYY');
$('#dateRange').val(usageState.dateRange);
$('#userId, #groupBy').on('change', function () {
usageState.userId = $('#userId').val();
usageState.groupBy = $('#groupBy').val();
loadUsage();
});
var searchTimer;
$('#search').on('keyup', function () {
window.clearTimeout(searchTimer);
searchTimer = window.setTimeout(function () {
usageState.search = $('#search').val();
loadUsage();
}, 400);
});
loadUsage();
});
function filterSessionHash($sessHash)
{
    if(confirm("Do you want to filter by this session?"))
    {
        usageState.sessHash = $sessHash;
        loadUsage();
    }
    else{
        return false;
    }
}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';