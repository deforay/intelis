<?php

use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$title = _translate("User Activity Log");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$userNameList = $usersService->getAllUsers(null, null, 'drop-down');

$actions = $db->rawQueryGenerator("SELECT DISTINCT event_type FROM activity_log");

$actionList = [];
foreach ($actions as $list) {
	$actionList[$list['event_type']] = (str_replace("-", " ", (string) $list['event_type']));
}

?>
<style>
	.audit-toolbar {
		display: flex;
		flex-wrap: wrap;
		gap: 12px 16px;
		align-items: flex-end;
		padding: 14px 16px;
		background: #fff;
		border-bottom: 1px solid #edf2f7;
	}

	.audit-toolbar .field {
		display: flex;
		flex-direction: column;
		gap: 3px;
	}

	.audit-toolbar label {
		font-size: 11px;
		font-weight: 600;
		color: #718096;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		margin: 0;
	}

	.audit-toolbar input,
	.audit-toolbar select {
		height: 34px;
		border: 1px solid #cbd5e0;
		border-radius: 6px;
		padding: 0 9px;
		font-size: 13px;
		background: #fff;
		min-width: 150px;
	}

	.audit-source {
		display: inline-flex;
		border: 1px solid #cbd5e0;
		border-radius: 6px;
		overflow: hidden;
	}

	.audit-source .src-btn {
		border: 0;
		background: #fff;
		padding: 0 12px;
		height: 34px;
		font-size: 12px;
		font-weight: 600;
		color: #4a5568;
		cursor: pointer;
	}

	.audit-source .src-btn.is-active {
		background: #4299e1;
		color: #fff;
	}

	.reset-btn {
		height: 34px;
		border: 1px solid #cbd5e0;
		border-radius: 6px;
		background: #f7fafc;
		color: #4a5568;
		font-size: 12px;
		font-weight: 600;
		padding: 0 14px;
		cursor: pointer;
	}

	.audit-search {
		position: relative;
		margin: 12px 16px 0;
	}

	.audit-search .fa-magnifying-glass {
		position: absolute;
		left: 12px;
		top: 11px;
		color: #a0aec0;
		font-size: 13px;
	}

	.audit-search input {
		width: 100%;
		height: 38px;
		border: 1px solid #cbd5e0;
		border-radius: 8px;
		padding: 0 14px 0 34px;
		font-size: 14px;
	}

	.audit-summary {
		padding: 10px 16px 0;
		font-size: 12px;
		color: #718096;
	}

	.audit-feed {
		padding: 8px 16px 16px;
	}

	.audit-feed .day-header {
		padding: 14px 0 8px;
		font-size: 12px;
		font-weight: 600;
		color: #718096;
		text-transform: uppercase;
		letter-spacing: 0.06em;
	}

	.audit-feed .day-header .count {
		color: #a0aec0;
		font-weight: 500;
		margin-left: 6px;
	}

	.audit-item {
		display: grid;
		grid-template-columns: 36px 1fr auto;
		gap: 14px;
		padding: 12px 14px;
		border: 1px solid #edf2f7;
		border-radius: 8px;
		background: #fff;
		margin-bottom: 8px;
		align-items: center;
		cursor: pointer;
		transition: border-color .15s, box-shadow .15s;
	}

	.audit-item:hover {
		border-color: #cbd5e0;
		box-shadow: 0 2px 4px rgba(0, 0, 0, .04);
	}

	.audit-icon {
		width: 36px;
		height: 36px;
		border-radius: 8px;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 14px;
		color: #fff;
	}

	.audit-icon.create { background: #38a169; }
	.audit-icon.delete { background: #e53e3e; }
	.audit-icon.update { background: #d69e2e; }
	.audit-icon.import { background: #3182ce; }
	.audit-icon.download { background: #319795; }
	.audit-icon.message { background: #805ad5; }
	.audit-icon.login { background: #2b6cb0; }
	.audit-icon.logout { background: #4a5568; }
	.audit-icon.login-fail { background: #c05621; }
	.audit-icon.other { background: #718096; }

	.audit-action {
		font-size: 14px;
		color: #1a202c;
		font-weight: 500;
		line-height: 1.4;
		word-break: break-word;
	}

	.audit-meta {
		margin-top: 5px;
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 10px;
		font-size: 12px;
		color: #718096;
	}

	.audit-user {
		display: inline-flex;
		align-items: center;
		gap: 6px;
	}

	.audit-avatar {
		width: 20px;
		height: 20px;
		border-radius: 50%;
		background: #4299e1;
		color: #fff;
		font-size: 10px;
		font-weight: 600;
		display: flex;
		align-items: center;
		justify-content: center;
		text-transform: uppercase;
	}

	.audit-user-name {
		color: #4a5568;
		font-weight: 500;
	}

	.audit-context {
		display: inline-block;
		padding: 2px 8px;
		border-radius: 999px;
		background: #edf2f7;
		color: #4a5568;
		font-size: 11px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.03em;
	}

	.audit-role {
		display: inline-block;
		padding: 1px 7px;
		border-radius: 4px;
		font-size: 10px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		background: #e2e8f0;
		color: #475569;
		border: 1px solid #cbd5e0;
	}

	.audit-ip,
	.audit-ua {
		font-size: 11px;
		color: #94a3b8;
	}

	.audit-ip {
		font-family: ui-monospace, Menlo, monospace;
	}

	.session-pill {
		font-family: ui-monospace, Menlo, monospace;
		font-size: 10.5px;
		color: #475569;
		background: #f1f5f9;
		border: 1px solid #e2e8f0;
		border-radius: 10px;
		padding: 1px 8px;
		cursor: pointer;
		white-space: nowrap;
	}

	.session-pill:hover {
		background: #e2e8f0;
		color: #1e293b;
	}

	.session-pill em {
		margin-right: 3px;
		opacity: 0.65;
	}

	.audit-time {
		font-size: 12px;
		color: #a0aec0;
		white-space: nowrap;
		align-self: start;
	}

	.audit-empty,
	.audit-loading {
		text-align: center;
		color: #a0aec0;
		padding: 40px 0;
		font-size: 14px;
	}

	.audit-pager {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 10px 16px;
		border-top: 1px solid #edf2f7;
		font-size: 12px;
		color: #718096;
	}

	.audit-pager button {
		border: 1px solid #cbd5e0;
		background: #fff;
		border-radius: 6px;
		height: 30px;
		padding: 0 12px;
		font-size: 12px;
		cursor: pointer;
	}

	.audit-pager button:disabled {
		opacity: 0.45;
		cursor: default;
	}

	/* detail modal */
	.audit-modal-backdrop {
		display: none;
		position: fixed;
		inset: 0;
		background: rgba(0, 0, 0, .4);
		z-index: 1050;
		align-items: center;
		justify-content: center;
	}

	.audit-modal-backdrop.is-open {
		display: flex;
	}

	.audit-modal {
		background: #fff;
		border-radius: 10px;
		width: 560px;
		max-width: 94vw;
		max-height: 90vh;
		overflow: auto;
		padding: 18px 20px;
		box-shadow: 0 10px 40px rgba(0, 0, 0, .2);
	}

	.am-head {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: 12px;
	}

	.am-title {
		font-size: 15px;
		font-weight: 600;
		margin: 0;
		color: #1a202c;
	}

	.am-close {
		border: 0;
		background: none;
		font-size: 22px;
		line-height: 1;
		color: #a0aec0;
		cursor: pointer;
	}

	.am-time {
		font-size: 12px;
		color: #718096;
		margin: 4px 0 14px;
	}

	.am-grid {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 12px 18px;
	}

	.am-label {
		font-size: 10px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: #a0aec0;
	}

	.am-value {
		font-size: 13px;
		color: #2d3748;
		word-break: break-word;
	}

	.am-value.mono {
		font-family: ui-monospace, Menlo, monospace;
		font-size: 12px;
	}

	.am-section-label {
		margin: 16px 0 4px;
		font-size: 10px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: #a0aec0;
	}

	.am-statement {
		font-size: 13px;
		color: #2d3748;
		background: #f7fafc;
		border: 1px solid #edf2f7;
		border-radius: 6px;
		padding: 10px 12px;
		word-break: break-word;
	}

	.am-actions {
		margin-top: 16px;
		display: flex;
		gap: 10px;
		justify-content: flex-end;
	}

	.am-btn {
		border: 1px solid #cbd5e0;
		background: #fff;
		border-radius: 6px;
		height: 34px;
		padding: 0 14px;
		font-size: 13px;
		cursor: pointer;
	}

	.am-btn.primary {
		background: #4299e1;
		border-color: #4299e1;
		color: #fff;
	}
</style>
<div class="content-wrapper">
	<section class="content-header">
		<h1><span class="fa-solid fa-file-lines"></span>
			<?php echo _translate("User Activity Log"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><span class="fa-solid fa-chart-pie"></span>
					<?php echo _translate("Home"); ?>
				</a></li>
			<li class="active"><?php echo _translate("Activity Log"); ?></li>
		</ol>
	</section>

	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="audit-toolbar">
						<div class="field">
							<label><?php echo _translate("Show"); ?></label>
							<div class="audit-source" id="auditSource">
								<button type="button" class="src-btn is-active" data-source="all"><?php echo _translate("All"); ?></button>
								<button type="button" class="src-btn" data-source="actions"><?php echo _translate("Actions"); ?></button>
								<button type="button" class="src-btn" data-source="logins"><?php echo _translate("Logins"); ?></button>
							</div>
						</div>
						<div class="field">
							<label for="dateRange"><?php echo _translate("Date Range"); ?></label>
							<input type="text" id="dateRange" placeholder="<?php echo _translate('Any date'); ?>" readonly style="background:#fff;" />
						</div>
						<div class="field">
							<label for="typeOfAction"><?php echo _translate("Type"); ?></label>
							<select id="typeOfAction" class="form-control select2">
								<?php echo $general->generateSelectOptions($actionList, null, '-- All --'); ?>
							</select>
						</div>
						<div class="field">
							<label for="userName"><?php echo _translate("User"); ?></label>
							<select id="userName" class="form-control select2">
								<?php echo $general->generateSelectOptions($userNameList, null, '-- All --'); ?>
							</select>
						</div>
						<div class="field">
							<label for="sessionHash"><?php echo _translate("Session"); ?></label>
							<input type="search" id="sessionHash" placeholder="<?php echo _translate('Session hash'); ?>" autocomplete="off" />
						</div>
						<button type="button" class="reset-btn" id="resetBtn"><?php echo _translate("Reset"); ?></button>
					</div>

					<div class="audit-search">
						<span class="fa-solid fa-magnifying-glass"></span>
						<input type="search" id="searchBox" placeholder="<?php echo _translate('Search activity — name, code, or keyword…'); ?>" autocomplete="off" />
					</div>

					<div class="audit-summary" id="auditSummary"></div>
					<div class="audit-feed" id="auditFeed">
						<div class="audit-loading"><?php echo _translate("Loading…"); ?></div>
					</div>
					<div class="audit-pager" id="auditPager" style="display:none;">
						<span id="pageInfo"></span>
						<span>
							<button type="button" id="prevPage"><?php echo _translate("Previous"); ?></button>
							<button type="button" id="nextPage"><?php echo _translate("Next"); ?></button>
						</span>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

<div class="audit-modal-backdrop" id="auditModalBackdrop" aria-hidden="true">
	<div class="audit-modal" role="dialog" aria-modal="true">
		<div class="am-head">
			<h3 class="am-title" id="amTitle"></h3>
			<button type="button" class="am-close" id="amClose">&times;</button>
		</div>
		<div class="am-time" id="amTime"></div>
		<div class="am-grid">
			<div><div class="am-label"><?php echo _translate("User"); ?></div><div class="am-value" id="amUser"></div></div>
			<div><div class="am-label"><?php echo _translate("Email"); ?></div><div class="am-value mono" id="amEmail"></div></div>
			<div><div class="am-label"><?php echo _translate("Role"); ?></div><div class="am-value" id="amRole"></div></div>
			<div><div class="am-label"><?php echo _translate("Context"); ?></div><div class="am-value" id="amContext"></div></div>
			<div><div class="am-label"><?php echo _translate("IP Address"); ?></div><div class="am-value mono" id="amIp"></div></div>
			<div><div class="am-label"><?php echo _translate("Session Hash"); ?></div><div class="am-value mono" id="amSession"></div></div>
			<div><div class="am-label"><?php echo _translate("Browser"); ?></div><div class="am-value" id="amBrowser"></div></div>
			<div><div class="am-label"><?php echo _translate("OS"); ?></div><div class="am-value" id="amOs"></div></div>
		</div>
		<div class="am-section-label"><?php echo _translate("Action"); ?></div>
		<div class="am-statement" id="amAction"></div>
		<div class="am-actions">
			<button type="button" class="am-btn primary" id="amFilterSession"><?php echo _translate("Filter by this session"); ?></button>
			<button type="button" class="am-btn" id="amDismiss"><?php echo _translate("Close"); ?></button>
		</div>
	</div>
</div>

<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
	var feedUrl = "/admin/monitoring/get-activity-log.php";
	var actionIcons = {
		create: 'fa-plus', delete: 'fa-trash', update: 'fa-pen', import: 'fa-file-import',
		download: 'fa-download', message: 'fa-envelope', login: 'fa-right-to-bracket',
		logout: 'fa-right-from-bracket', 'login-fail': 'fa-triangle-exclamation', other: 'fa-bolt'
	};
	var state = { page: 1, pageSize: 25, source: 'all', dateRange: '', type: '', createdBy: '', sessionHash: '', search: '' };
	var currentItems = [];
	var fetchSeq = 0;
	var searchDebounce, sessionDebounce;
	var $feed, $summary, $pager, $pageInfo, $prev, $next, $modal;

	function escapeHtml(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function parseBrowserOS(ua) {
		if (!ua) return { browser: '', os: '' };
		var browser = '', os = '';
		if (/Edg\//.test(ua)) browser = 'Edge';
		else if (/OPR\/|Opera/.test(ua)) browser = 'Opera';
		else if (/Chrome\//.test(ua)) browser = 'Chrome';
		else if (/Safari\//.test(ua) && !/Chrome/.test(ua)) browser = 'Safari';
		else if (/Firefox\//.test(ua)) browser = 'Firefox';
		else if (/MSIE|Trident/.test(ua)) browser = 'IE';
		if (/Windows NT 10/.test(ua)) os = 'Windows 10';
		else if (/Windows NT 11/.test(ua)) os = 'Windows 11';
		else if (/Windows/.test(ua)) os = 'Windows';
		else if (/Mac OS X/.test(ua)) os = 'macOS';
		else if (/Android/.test(ua)) os = 'Android';
		else if (/iPhone|iPad|iOS/.test(ua)) os = 'iOS';
		else if (/Linux/.test(ua)) os = 'Linux';
		return { browser: browser, os: os };
	}

	function deviceLabel(item) {
		var p = parseBrowserOS(item.userAgent || '');
		if (p.browser && p.os) return p.browser + ' on ' + p.os;
		return p.browser || p.os || '';
	}

	function renderItem(item, idx) {
		var iconClass = actionIcons[item.actionType] || actionIcons.other;
		var roleHtml = item.userRole ? '<span class="audit-role">' + escapeHtml(item.userRole) + '</span>' : '';
		var ctxHtml = item.context ? '<span class="audit-context">' + escapeHtml(item.context) + '</span>' : '';
		var ipHtml = item.ipAddress ? '<span class="audit-ip" title="IP address">' + escapeHtml(item.ipAddress) + '</span>' : '';
		var dev = deviceLabel(item);
		var uaHtml = dev ? '<span class="audit-ua" title="' + escapeHtml(item.userAgent || dev) + '">' + escapeHtml(dev) + '</span>' : '';
		var sessHtml = item.sessionHash
			? '<span class="session-pill" data-session="' + escapeHtml(item.sessionHash) + '" title="Click to filter by this session"><em class="fa-solid fa-fingerprint"></em>' + escapeHtml(item.sessionHash.substring(0, 8)) + '</span>'
			: '';
		return '' +
			'<div class="audit-item" data-idx="' + idx + '">' +
			'<div class="audit-icon ' + escapeHtml(item.actionType) + '"><em class="fa-solid ' + iconClass + '"></em></div>' +
			'<div class="audit-body">' +
			'<div class="audit-action">' + escapeHtml(item.action) + '</div>' +
			'<div class="audit-meta">' +
			'<span class="audit-user" title="' + escapeHtml(item.userEmail) + '"><span class="audit-avatar">' + escapeHtml(item.userInitials) + '</span><span class="audit-user-name">' + escapeHtml(item.userName) + '</span></span>' +
			roleHtml + ctxHtml + ipHtml + uaHtml + sessHtml +
			'</div></div>' +
			'<div class="audit-time">' + escapeHtml(item.time) + '</div>' +
			'</div>';
	}

	function renderFeed(payload) {
		currentItems = payload.items || [];
		if (currentItems.length === 0) {
			$feed.html('<div class="audit-empty"><?php echo _translate("No activity matches the current filters."); ?></div>');
			$summary.html('<strong>0</strong> <?php echo _translate("entries"); ?>');
			$pager.hide();
			return;
		}
		currentItems.forEach(function (it, i) { it.__idx = i; });
		var groups = [], current = null;
		currentItems.forEach(function (it) {
			if (!current || current.key !== it.dateKey) {
				current = { key: it.dateKey, label: it.dateLabel, items: [] };
				groups.push(current);
			}
			current.items.push(it);
		});
		var html = '';
		groups.forEach(function (g) {
			html += '<div class="day-group"><div class="day-header">' + escapeHtml(g.label) + ' <span class="count">' + g.items.length + '</span></div>';
			g.items.forEach(function (it) { html += renderItem(it, it.__idx); });
			html += '</div>';
		});
		$feed.html(html);

		var start = (payload.page - 1) * payload.pageSize + 1;
		var end = Math.min(start + currentItems.length - 1, payload.total);
		$summary.html('<strong>' + payload.total.toLocaleString() + '</strong> <?php echo _translate("entries"); ?>');
		$pageInfo.html('<?php echo _translate("Showing"); ?> <strong>' + start + '</strong>–<strong>' + end + '</strong> <?php echo _translate("of"); ?> <strong>' + payload.total.toLocaleString() + '</strong>');
		$prev.prop('disabled', payload.page <= 1);
		$next.prop('disabled', payload.page >= payload.totalPages);
		$pager.show();
	}

	function fetchFeed() {
		var seq = ++fetchSeq;
		$feed.html('<div class="audit-loading"><em class="fa-solid fa-circle-notch fa-spin"></em> <?php echo _translate("Loading…"); ?></div>');
		$.ajax({
			url: feedUrl, type: 'POST', dataType: 'json',
			data: {
				page: state.page, pageSize: state.pageSize, source: state.source,
				dateRange: state.dateRange, type: state.type, createdBy: state.createdBy,
				sessionHash: state.sessionHash, search: state.search
			}
		}).done(function (payload) {
			if (seq !== fetchSeq) return;
			renderFeed(payload);
		}).fail(function () {
			if (seq !== fetchSeq) return;
			$feed.html('<div class="audit-empty"><?php echo _translate("Failed to load activity. Please retry."); ?></div>');
			$pager.hide();
		});
	}

	function openDetailModal(item) {
		$('#amTitle').text(item.action || '');
		$('#amTime').text((item.dateLabel || '') + ' • ' + (item.time || ''));
		$('#amUser').text(item.userName || '—');
		$('#amEmail').text(item.userEmail || '—');
		$('#amRole').text(item.userRole || '—');
		$('#amContext').text(item.context || '—');
		$('#amIp').text(item.ipAddress || '—');
		$('#amSession').text(item.sessionHash || '—');
		$('#amAction').text(item.action || '');
		var p = parseBrowserOS(item.userAgent || '');
		$('#amBrowser').text(p.browser || '—').attr('title', item.userAgent || '');
		$('#amOs').text(p.os || '—');
		if (item.sessionHash) { $('#amFilterSession').data('session', item.sessionHash).show(); }
		else { $('#amFilterSession').hide(); }
		$modal.addClass('is-open').attr('aria-hidden', 'false');
	}

	function closeDetailModal() { $modal.removeClass('is-open').attr('aria-hidden', 'true'); }

	$(document).ready(function () {
		$feed = $('#auditFeed'); $summary = $('#auditSummary'); $pager = $('#auditPager');
		$pageInfo = $('#pageInfo'); $prev = $('#prevPage'); $next = $('#nextPage'); $modal = $('#auditModalBackdrop');

		$('#userName, #typeOfAction').select2({ width: '180px' });

		$('#dateRange').daterangepicker({
			locale: { cancelLabel: "<?= _translate('Clear', true); ?>", format: 'DD-MMM-YYYY', separator: ' to ' },
			autoUpdateInput: false, showDropdowns: true, alwaysShowCalendars: false, maxDate: moment(),
			ranges: {
				'Today': [moment(), moment()],
				'Last 7 Days': [moment().subtract(6, 'days'), moment()],
				'Last 30 Days': [moment().subtract(29, 'days'), moment()],
				'This Month': [moment().startOf('month'), moment().endOf('month')],
				'Last 90 Days': [moment().subtract(89, 'days'), moment()],
				'Current Year To Date': [moment().startOf('year'), moment()]
			}
		}, function (start, end) {
			state.dateRange = start.format('DD-MMM-YYYY') + ' to ' + end.format('DD-MMM-YYYY');
			$('#dateRange').val(state.dateRange);
			state.page = 1; fetchFeed();
		});
		$('#dateRange').on('cancel.daterangepicker', function () {
			$(this).val(''); state.dateRange = ''; state.page = 1; fetchFeed();
		});

		$('#typeOfAction').on('change', function () { state.type = $(this).val() || ''; state.page = 1; fetchFeed(); });
		$('#userName').on('change', function () { state.createdBy = $(this).val() || ''; state.page = 1; fetchFeed(); });

		$('#sessionHash').on('input', function () {
			var v = $(this).val();
			clearTimeout(sessionDebounce);
			sessionDebounce = setTimeout(function () { state.sessionHash = v.trim(); state.page = 1; fetchFeed(); }, 250);
		});

		$('#searchBox').on('input', function () {
			var v = $(this).val();
			clearTimeout(searchDebounce);
			searchDebounce = setTimeout(function () { state.search = v; state.page = 1; fetchFeed(); }, 250);
		});

		$('#auditSource').on('click', '.src-btn', function () {
			var src = $(this).data('source');
			if (src === state.source) return;
			$('#auditSource .src-btn').removeClass('is-active');
			$(this).addClass('is-active');
			state.source = src; state.page = 1; fetchFeed();
		});

		$('#resetBtn').on('click', function () {
			$('#dateRange').val(''); $('#sessionHash').val(''); $('#searchBox').val('');
			$('#typeOfAction').val('').trigger('change.select2');
			$('#userName').val('').trigger('change.select2');
			$('#auditSource .src-btn').removeClass('is-active').filter('[data-source="all"]').addClass('is-active');
			state = { page: 1, pageSize: 25, source: 'all', dateRange: '', type: '', createdBy: '', sessionHash: '', search: '' };
			fetchFeed();
		});

		$prev.on('click', function () { if (state.page > 1) { state.page -= 1; fetchFeed(); } });
		$next.on('click', function () { state.page += 1; fetchFeed(); });

		$feed.on('click', '.session-pill', function (e) {
			e.stopPropagation();
			var sh = $(this).data('session');
			$('#sessionHash').val(sh); state.sessionHash = sh; state.page = 1; fetchFeed();
		});
		$feed.on('click', '.audit-item', function () {
			var idx = parseInt($(this).data('idx'), 10);
			if (!isNaN(idx) && currentItems[idx]) openDetailModal(currentItems[idx]);
		});

		$('#amClose, #amDismiss').on('click', closeDetailModal);
		$modal.on('click', function (e) { if (e.target === this) closeDetailModal(); });
		$(document).on('keydown', function (e) { if (e.key === 'Escape' && $modal.hasClass('is-open')) closeDetailModal(); });
		$('#amFilterSession').on('click', function () {
			var sh = $(this).data('session') || '';
			if (!sh) return;
			$('#sessionHash').val(sh); state.sessionHash = sh; state.page = 1; closeDetailModal(); fetchFeed();
		});

		fetchFeed();
	});
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
