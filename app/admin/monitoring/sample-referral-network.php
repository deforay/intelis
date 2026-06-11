<?php

use App\Services\TestsService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

$title = _translate("Sample Referral Network");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$labNameList = $facilitiesService->getTestingLabs();
$provinces = $geolocationService->getProvinces("yes");
$activeTests = TestsService::getActiveTests();
?>
<link rel="stylesheet" href="/assets/plugins/leaflet/leaflet.css" />
<link rel="stylesheet" href="/assets/plugins/leaflet/MarkerCluster.css" />
<link rel="stylesheet" href="/assets/plugins/leaflet/MarkerCluster.Default.css" />
<link rel="stylesheet" href="/assets/css/tom-select.css" />
<style>
    #referralMap {
        height: 75vh;
        min-height: 520px;
        width: 100%;
        border: 1px solid #d2d6de;
        border-radius: 3px;
        background: #aadaff;
    }

    /* Expanded view via the native Fullscreen API (preferred). */
    #referralMap:fullscreen {
        width: 100%;
        height: 100%;
        background: #aadaff;
    }

    /* Fallback for browsers without the Fullscreen API: fixed overlay. */
    #referralMap.referral-map-fullscreen {
        position: fixed;
        top: 0;
        left: 0;
        width: 100% !important;
        height: 100% !important;
        z-index: 10500;
        border-radius: 0;
    }

    .referral-legend {
        background: #fff;
        padding: 8px 10px;
        line-height: 22px;
        border-radius: 4px;
        box-shadow: 0 0 6px rgba(0, 0, 0, .3);
        font-size: 13px;
    }

    .referral-legend .dot {
        display: inline-block;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        margin-right: 6px;
        vertical-align: middle;
    }

    .referral-legend .line {
        display: inline-block;
        width: 16px;
        height: 3px;
        margin-right: 6px;
        vertical-align: middle;
        border-radius: 2px;
    }

    .referral-legend label {
        display: block;
        font-weight: normal;
        margin: 8px 0 0;
        padding-top: 6px;
        border-top: 1px solid #eee;
        cursor: pointer;
    }

    /* Larger, more readable marker popup. */
    .leaflet-popup-content {
        font-size: 13.5px;
        line-height: 1.5;
        min-width: 200px;
        margin: 12px 16px;
    }

    .referral-popup strong {
        font-size: 15px;
    }

    .referral-popup .rp-instr {
        margin-top: 8px;
        padding-top: 6px;
        border-top: 1px solid #eee;
    }

    .referral-popup .rp-instr-head {
        font-weight: 600;
        margin-bottom: 2px;
    }

    .referral-popup .rp-instr ul {
        margin: 0;
        padding-left: 18px;
        max-height: 160px;
        overflow-y: auto;
    }

    .referral-popup .rp-instr li {
        margin: 1px 0;
    }

    .referral-popup .rp-instr-count {
        font-weight: 700;
        color: #00695c;
    }

    .referral-popup .rp-instr-off {
        color: #b71c1c;
        font-size: 11px;
        font-style: italic;
    }

    .referral-popup .rp-instr-none {
        margin-top: 8px;
        padding-top: 6px;
        border-top: 1px solid #eee;
        color: #888;
        font-style: italic;
    }

    /* Larger, easier-to-hit map control buttons (expand, etc.). */
    .leaflet-bar a.referral-ctrl-btn {
        font-size: 17px;
        line-height: 32px;
        width: 32px;
        height: 32px;
    }

    .referral-focus-hint {
        width: 250px;
        font-size: 13px;
    }

    .referral-focus-hint .rl-head {
        color: #777;
        font-size: 12px;
        margin: 2px 0 4px;
    }

    .referral-focus-hint .rl-list {
        max-height: 260px;
        overflow-y: auto;
        margin-bottom: 6px;
    }

    .referral-focus-hint .rl-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        padding: 1px 0;
        border-bottom: 1px solid #f0f0f0;
        line-height: 18px;
    }

    .referral-focus-hint .rl-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .referral-focus-hint .rl-count {
        font-weight: 700;
        white-space: nowrap;
    }

    .referral-focus-hint .rl-more {
        color: #777;
        padding-top: 2px;
    }

    .referral-stat {
        font-size: 22px;
        font-weight: bold;
    }

    .referral-filters label {
        font-weight: 700;
        margin-bottom: 4px;
    }

    /* Tom Select copies the `.form-control` class onto its wrapper, which then
       draws a second box around the real control. Neutralise the outer box. */
    .ts-wrapper.form-control,
    .ts-wrapper.form-select {
        padding: 0;
        height: auto;
        border: 0;
        box-shadow: none;
    }

    /* The Test Type list sits just above the map; Leaflet panes/controls have a
       high z-index, so lift the open dropdown above them or it renders behind
       the map. */
    .referral-filters .ts-dropdown {
        z-index: 10001;
    }

    .select2-selection__choice {
        color: black !important;
    }
</style>
<div class="content-wrapper">
    <section class="content-header">
        <h1><em class="fa-solid fa-diagram-project"></em>
            <?php echo _translate("Sample Referral Network"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Sample Referral Network"); ?></li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header with-border">
                        <span class="text-muted hidden-xs">
                            <em class="fa-solid fa-hand-pointer"></em>&nbsp;<?= _translate("Click any lab or facility on the map to see only its referral links."); ?>
                        </span>
                        <button id="toggleFilters" class="btn btn-default btn-sm pull-right">
                            <em class="fa-solid fa-filter"></em>&nbsp;<?= _translate("Filters"); ?>
                        </button>
                    </div>

                    <div class="box-body referral-filters" id="filterPanel" style="display:none;">
                        <div class="row">
                            <div class="col-md-3 col-sm-6 form-group">
                                <label for="dateRange"><?= _translate('Date Range'); ?></label>
                                <input type="text" id="dateRange" name="dateRange" class="form-control daterangefield"
                                    placeholder="<?php echo _translate('Enter date range'); ?>" style="background:#fff;" />
                            </div>
                            <div class="col-md-3 col-sm-6 form-group">
                                <label for="state"><?= _translate('Province/State'); ?></label>
                                <select class="form-control" id="state" onchange="getByProvince()" name="state"
                                    multiple="multiple">
                                    <?= $general->generateSelectOptions($provinces); ?>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6 form-group">
                                <label for="district"><?= _translate("District/County"); ?></label>
                                <select class="form-control" id="district" name="district" multiple="multiple"></select>
                            </div>
                            <div class="col-md-3 col-sm-6 form-group">
                                <label for="labName"><?= _translate("Name of the Testing Lab"); ?></label>
                                <select class="form-control" id="labName" name="labName" multiple="multiple">
                                    <?= $general->generateSelectOptions($labNameList); ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 col-sm-6 form-group">
                                <label for="testType"><?= _translate("Test Type"); ?></label>
                                <select id="testType" name="testType" class="form-control" multiple="multiple">
                                    <?php foreach ($activeTests as $testType) {
                                        echo '<option value="' . htmlspecialchars($testType) . '">'
                                            . htmlspecialchars(TestsService::getTestName($testType)) . '</option>';
                                    } ?>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6 form-group">
                                <label for="markerFilter"><?= _translate("Show on map"); ?></label>
                                <select id="markerFilter" name="markerFilter" class="form-control">
                                    <option value="all"><?= _translate("All"); ?></option>
                                    <option value="labs"><?= _translate("Testing Labs only"); ?></option>
                                    <option value="sites"><?= _translate("Referring Facilities only"); ?></option>
                                </select>
                            </div>
                            <div class="col-md-6 col-sm-6 form-group">
                                <div class="hidden-xs" style="height:27px;" aria-hidden="true"></div>
                                <button onclick="applyFilters();" class="btn btn-primary">
                                    <em class="fa-solid fa-magnifying-glass"></em>&nbsp;<?php echo _translate("Search"); ?>
                                </button>
                                <button class="btn btn-default"
                                    onclick="document.location.href = document.location"><?php echo _translate("Reset"); ?></button>
                            </div>
                        </div>
                    </div>

                    <div class="box-body">
                        <div class="row" style="margin-bottom:10px;">
                            <div class="col-sm-3 text-center">
                                <div class="referral-stat" id="statSamples">-</div>
                                <small><?= _translate("Samples Referred"); ?></small>
                            </div>
                            <div class="col-sm-3 text-center">
                                <div class="referral-stat" id="statSites">-</div>
                                <small><?= _translate("Referring Facilities"); ?></small>
                            </div>
                            <div class="col-sm-3 text-center">
                                <div class="referral-stat" id="statLabs">-</div>
                                <small><?= _translate("Testing Labs"); ?></small>
                            </div>
                            <div class="col-sm-3 text-center">
                                <div class="referral-stat" id="statFlows">-</div>
                                <small><?= _translate("Referral Links"); ?></small>
                            </div>
                        </div>

                        <div id="referralMap"></div>
                        <div id="mapNote" class="text-muted"
                            style="display:none;font-size:12px;margin-top:6px;text-align:right;"></div>

                        <h4 style="margin-top:20px;"><em class="fa-solid fa-table"></em>
                            <?= _translate("Referrals by Lab and Test Type"); ?></h4>
                        <table aria-describedby="summary" id="referralSummaryTable"
                            class="table table-bordered table-striped" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th><?= _translate("Referring Facility"); ?></th>
                                    <th><?= _translate("District/County"); ?></th>
                                    <th><?= _translate("Province/State"); ?></th>
                                    <th><?= _translate("Testing Lab"); ?></th>
                                    <th><?= _translate("Test Type"); ?></th>
                                    <th><?= _translate("Samples Referred"); ?></th>
                                    <th><?= _translate("Latest Request"); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script src="/assets/plugins/leaflet/leaflet.js"></script>
<script src="/assets/plugins/leaflet/leaflet.markercluster.js"></script>
<script src="/assets/js/tom-select.complete.min.js"></script>
<script src="/assets/js/dom-to-image-more.min.js"></script>
<script type="text/javascript">
    // Base tile layer — OpenStreetMap. Swap this URL for a self-hosted/offline
    // tile server in deployments without internet access.
    var TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    var TILE_ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
    var MAX_FLOWS_RENDERED = 600; // keep the map responsive on large networks
    var LAB_COLOR = '#dd4b39', SITE_COLOR = '#3c8dbc';
    // Default flow colour, and a distinct colour for lab -> lab referrals
    // (one testing lab onward-referring to another).
    var FLOW_COLOR = '#00838f', LAB_FLOW_COLOR = '#8e24aa';

    var map, flowLayer, markerLayer, summaryTable, fsBtn, snapBtn, focusHintEl;
    var mapNodesById = {}, mapFlows = [], focusedId = null, showAllLines = false;
    // 'all' | 'labs' | 'sites' — restricts which marker type is shown on the map.
    var markerFilter = 'all';
    var tsState, tsDistrict, tsLab, tsTest;

    // Escape DB-sourced text before it goes into Leaflet popup/tooltip HTML.
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function filterPayload() {
        return {
            dateRange: $('#dateRange').val(),
            state: $('#state').val(),
            district: $('#district').val(),
            labName: $('#labName').val(),
            testType: $('#testType').val()
        };
    }

    // Plain colour markers (red = testing lab, blue = referring facility).
    function markerStyle(isLab) {
        return {
            radius: isLab ? 8 : 4,
            color: '#fff',
            weight: 1.5,
            fillColor: isLab ? LAB_COLOR : SITE_COLOR,
            fillOpacity: 0.9
        };
    }

    // bringToFront throws if the marker is currently inside a cluster (not on
    // the map), so guard it.
    function safeFront(marker) {
        try { marker.bringToFront(); } catch (e) { /* clustered / not rendered */ }
    }

    // Render a lab's configured instruments for the marker popup. Each line is
    // the analyser name, with a "× N" badge when more than one physical machine
    // of that type is configured (from the instrument_machines sub-table).
    function instrumentsHtml(instruments) {
        if (!instruments || !instruments.length) {
            return '<div class="rp-instr rp-instr-none"><?= _translate("No instruments configured", true); ?></div>';
        }
        var rows = '';
        instruments.forEach(function (ins) {
            var count = (ins.machines && ins.machines > 1)
                ? ' <span class="rp-instr-count">&times; ' + Number(ins.machines) + '</span>'
                : '';
            var inactive = (ins.status && ins.status !== 'active')
                ? ' <span class="rp-instr-off"><?= _translate("inactive", true); ?></span>'
                : '';
            rows += '<li>' + esc(ins.name) + count + inactive + '</li>';
        });
        return '<div class="rp-instr"><div class="rp-instr-head"><?= _translate("Instruments", true); ?></div>'
            + '<ul>' + rows + '</ul></div>';
    }

    function nodeVisible(n) {
        return markerFilter === 'all'
            || (markerFilter === 'labs' && n.isLab)
            || (markerFilter === 'sites' && !n.isLab);
    }

    // (Re)populate the cluster layer with only the markers allowed by the
    // current Show filter. Markers are built once in loadMap and kept in
    // mapNodesById, so switching the filter never re-fetches data.
    function renderMarkers() {
        markerLayer.clearLayers();
        Object.keys(mapNodesById).forEach(function (k) {
            var item = mapNodesById[k];
            if (nodeVisible(item.n)) {
                markerLayer.addLayer(item.marker);
                if (item.n.isLab) { safeFront(item.marker); }
            }
        });
    }

    function initMap() {
        map = L.map('referralMap', { worldCopyJump: true }).setView([0, 20], 2);
        L.tileLayer(TILE_URL, { maxZoom: 19, attribution: TILE_ATTR, crossOrigin: true }).addTo(map);
        // Cluster markers so dense city points (hundreds of facilities on the
        // same coarse coordinate) collapse into count bubbles only when very
        // zoomed out (continent/country view), and separate as soon as you zoom
        // in to regional level.
        markerLayer = L.markerClusterGroup({
            chunkedLoading: true,
            showCoverageOnHover: false,
            maxClusterRadius: 35,
            spiderfyOnMaxZoom: true,
            disableClusteringAtZoom: 7
        }).addTo(map);
        flowLayer = L.layerGroup().addTo(map);

        // Clicking empty map clears any focused catchment.
        map.on('click', function () { if (focusedId !== null) { clearFocus(); } });

        // Keep the button icon and map size in sync with native fullscreen
        // (covers the browser's own Esc-to-exit too).
        document.addEventListener('fullscreenchange', afterFsChange);
        document.addEventListener('webkitfullscreenchange', afterFsChange);

        var legend = L.control({ position: 'bottomright' });
        legend.onAdd = function () {
            var div = L.DomUtil.create('div', 'referral-legend');
            div.innerHTML = '<span class="dot" style="background:' + LAB_COLOR + '"></span><?= _translate("Testing Lab", true); ?><br>'
                + '<span class="dot" style="background:' + SITE_COLOR + '"></span><?= _translate("Referring Facility", true); ?><br>'
                + '<span class="line" style="background:' + LAB_FLOW_COLOR + '"></span><?= _translate("Lab-to-lab referral", true); ?>'
                + '<label><input type="checkbox" id="toggleLines"> <?= _translate("Show all referral lines", true); ?></label>';
            L.DomEvent.disableClickPropagation(div);
            return div;
        };
        legend.addTo(map);

        // Focus hint (shown only while a node is focused).
        var focusControl = L.control({ position: 'topright' });
        focusControl.onAdd = function () {
            focusHintEl = L.DomUtil.create('div', 'referral-legend referral-focus-hint');
            focusHintEl.style.display = 'none';
            L.DomEvent.disableClickPropagation(focusHintEl);
            return focusHintEl;
        };
        focusControl.addTo(map);

        // Expand + snapshot controls (stacked, top-left).
        var toolControl = L.control({ position: 'topleft' });
        toolControl.onAdd = function () {
            var div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');

            fsBtn = L.DomUtil.create('a', 'referral-ctrl-btn', div);
            fsBtn.href = '#';
            fsBtn.title = '<?= _translate("Toggle expanded view", true); ?>';
            fsBtn.innerHTML = '<em class="fa-solid fa-expand"></em>';
            L.DomEvent.on(fsBtn, 'click', function (e) { L.DomEvent.stop(e); toggleFullscreen(); });

            snapBtn = L.DomUtil.create('a', 'referral-ctrl-btn', div);
            snapBtn.href = '#';
            snapBtn.title = '<?= _translate("Download map as image", true); ?>';
            snapBtn.innerHTML = '<em class="fa-solid fa-camera"></em>';
            L.DomEvent.on(snapBtn, 'click', function (e) { L.DomEvent.stop(e); captureSnapshot(); });

            return div;
        };
        toolControl.addTo(map);
    }

    // Capture the map exactly as shown and download it as a PNG. The Leaflet
    // button bars are excluded so the snapshot stays clean (legend is kept).
    function captureSnapshot() {
        var node = document.getElementById('referralMap');
        if (typeof domtoimage === 'undefined') {
            alert('<?= _translate("Image capture library not loaded.", true); ?>');
            return;
        }
        snapBtn.innerHTML = '<em class="fa-solid fa-spinner fa-spin"></em>';
        // Render at 2x for a crisp image (dom-to-image rasterises at 1x by default).
        var scale = 2;
        var w = node.offsetWidth, h = node.offsetHeight;
        domtoimage.toBlob(node, {
            bgcolor: '#ffffff',
            width: w * scale,
            height: h * scale,
            style: {
                transform: 'scale(' + scale + ')',
                transformOrigin: 'top left',
                width: w + 'px',
                height: h + 'px'
            },
            filter: function (el) {
                return !(el.classList && el.classList.contains('leaflet-bar'));
            }
        }).then(function (blob) {
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'sample-referral-network-' + moment().format('YYYY-MM-DD-HHmm') + '.png';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(a.href);
            snapBtn.innerHTML = '<em class="fa-solid fa-camera"></em>';
        }).catch(function () {
            snapBtn.innerHTML = '<em class="fa-solid fa-camera"></em>';
            alert('<?= _translate("Could not capture the map image.", true); ?>');
        });
    }

    function nativeFsElement() {
        return document.fullscreenElement || document.webkitFullscreenElement || null;
    }

    function afterFsChange() {
        var el = document.getElementById('referralMap');
        var on = nativeFsElement() === el || el.classList.contains('referral-map-fullscreen');
        if (fsBtn) {
            fsBtn.innerHTML = on ? '<em class="fa-solid fa-compress"></em>' : '<em class="fa-solid fa-expand"></em>';
        }
        setTimeout(function () { map.invalidateSize(); }, 200);
    }

    function toggleFullscreen(forceOff) {
        var el = document.getElementById('referralMap');
        var inFs = nativeFsElement() === el || el.classList.contains('referral-map-fullscreen');

        if (forceOff || inFs) {
            // Exit.
            if (document.exitFullscreen) { document.exitFullscreen(); }
            else if (document.webkitExitFullscreen) { document.webkitExitFullscreen(); }
            else { el.classList.remove('referral-map-fullscreen'); afterFsChange(); }
            return;
        }
        // Enter — prefer the native Fullscreen API (robust against ancestor transforms).
        if (el.requestFullscreen) { el.requestFullscreen(); }
        else if (el.webkitRequestFullscreen) { el.webkitRequestFullscreen(); }
        else { el.classList.add('referral-map-fullscreen'); afterFsChange(); }
    }

    function flowWeight(count) {
        return Math.max(1, Math.min(7, Math.log10(count + 1) * 2));
    }

    function drawFlow(f, opts) {
        var a = mapNodesById[f.from], b = mapNodesById[f.to];
        if (!a || !b) { return; }
        // A line is only drawn when both endpoints are visible under the current
        // Show filter, so "Testing Labs only" drops facility -> lab lines and
        // leaves just the lab -> lab referrals.
        if (!nodeVisible(a.n) || !nodeVisible(b.n)) { return; }
        // Lab -> lab onward-referrals get their own colour regardless of context.
        var labToLab = a.n.isLab && b.n.isLab;
        var color = labToLab ? LAB_FLOW_COLOR : (opts.color || FLOW_COLOR);
        var line = L.polyline([[a.n.lat, a.n.lng], [b.n.lat, b.n.lng]], {
            color: color, weight: flowWeight(f.count), opacity: opts.opacity || 0.3,
            bubblingMouseEvents: false
        });
        line.bindTooltip(esc(a.n.name) + ' &rarr; ' + esc(b.n.name) + '<br>'
            + Number(f.count).toLocaleString() + ' <?= _translate("samples", true); ?>'
            + (f.latest ? '<br><?= _translate("Latest", true); ?>: ' + f.latest : ''));
        line.addTo(flowLayer);
    }

    function drawAllFlows() {
        for (var i = 0; i < mapFlows.length && i < MAX_FLOWS_RENDERED; i++) {
            drawFlow(mapFlows[i], { opacity: 0.25 });
        }
    }

    // Default lines view (none, or all if the toggle is on) when nothing is focused.
    function redrawLines() {
        flowLayer.clearLayers();
        if (showAllLines) { drawAllFlows(); }
        updateMapNote();
    }

    function updateMapNote() {
        var note = '';
        if (showAllLines && mapFlows.length > MAX_FLOWS_RENDERED) {
            note = '<?= _translate("Showing the busiest", true); ?> ' + MAX_FLOWS_RENDERED
                + ' <?= _translate("referral links; the table below lists all of them.", true); ?>';
        }
        $('#mapNote').html(note).toggle(note !== '');
    }

    // Show only the clicked node's referral links; dim everything else.
    function focusNode(id) {
        focusedId = id;
        flowLayer.clearLayers();
        var connected = {};
        connected[id] = true;
        var links = [];   // the other endpoint of each incident flow + its count
        mapFlows.forEach(function (f) {
            if (f.from === id || f.to === id) {
                connected[f.from] = true;
                connected[f.to] = true;
                drawFlow(f, { opacity: 0.75, color: '#00695c' });
                var other = mapNodesById[f.from === id ? f.to : f.from];
                if (other) { links.push({ name: other.n.name, count: f.count }); }
            }
        });
        Object.keys(mapNodesById).forEach(function (k) {
            var item = mapNodesById[k];
            if (connected[item.n.id]) {
                item.marker.setStyle(markerStyle(item.n.isLab));
                safeFront(item.marker);
            } else {
                item.marker.setStyle({ opacity: 0.12, fillOpacity: 0.08 });
            }
        });

        var node = mapNodesById[id].n;
        // A referring facility lists the labs it sends to; a lab lists the
        // facilities it receives from. Names + counts, busiest first.
        var heading = node.isLab
            ? '<?= _translate("Receives from", true); ?>'
            : '<?= _translate("Refers to", true); ?>';
        links.sort(function (a, b) { return b.count - a.count; });
        var cap = 15, rows = '';
        links.slice(0, cap).forEach(function (l) {
            rows += '<div class="rl-row"><span class="rl-name">' + esc(l.name) + '</span>'
                + '<span class="rl-count">' + Number(l.count).toLocaleString() + '</span></div>';
        });
        if (links.length > cap) {
            rows += '<div class="rl-more">… ' + (links.length - cap) + ' <?= _translate("more", true); ?></div>';
        }
        focusHintEl.innerHTML = '<strong>' + esc(node.name) + '</strong>'
            + '<div class="rl-head">' + heading + ' (' + links.length + ')</div>'
            + '<div class="rl-list">' + rows + '</div>'
            + '<a href="#" onclick="clearFocus();return false;"><?= _translate("Show all", true); ?></a>';
        focusHintEl.style.display = 'block';
    }

    function clearFocus() {
        focusedId = null;
        Object.keys(mapNodesById).forEach(function (k) {
            var item = mapNodesById[k];
            item.marker.setStyle(markerStyle(item.n.isLab));
            if (item.n.isLab) { safeFront(item.marker); }
        });
        redrawLines();
        if (focusHintEl) { focusHintEl.style.display = 'none'; }
    }

    function loadMap() {
        $.post('/admin/monitoring/get-referral-map-data.php', filterPayload(), function (resp) {
            markerLayer.clearLayers();
            flowLayer.clearLayers();
            mapNodesById = {};
            focusedId = null;
            if (focusHintEl) { focusHintEl.style.display = 'none'; }

            var nodes = resp.nodes || [];
            mapFlows = resp.flows || [];
            var totalSamples = 0, labs = 0, sites = 0;
            var bounds = [];

            nodes.forEach(function (n) {
                bounds.push([n.lat, n.lng]);
                if (n.isLab) { labs++; } else { sites++; }

                var popup = '<div class="referral-popup"><strong>' + esc(n.name) + '</strong><br>'
                    + (n.district ? esc(n.district) + ', ' : '') + esc(n.province || '') + '<br>'
                    + '<?= _translate("Samples referred out", true); ?>: ' + Number(n.samplesSent).toLocaleString();
                if (n.isLab) {
                    popup += '<br><?= _translate("Samples received for testing", true); ?>: '
                        + Number(n.samplesReceived).toLocaleString();
                    popup += instrumentsHtml(n.instruments);
                }
                popup += '</div>';

                var marker = L.circleMarker([n.lat, n.lng],
                    Object.assign({ bubblingMouseEvents: false }, markerStyle(n.isLab)))
                    .bindPopup(popup);
                // Only focus when the marker is genuinely standalone (or
                // spiderfied). When it is collapsed inside a cluster bubble its
                // SVG circle can still sit under the bubble and capture a click
                // meant for the cluster — getVisibleParent returns the cluster
                // in that case, so we ignore the click and let the cluster
                // zoom/spiderfy as usual.
                marker.on('click', function () {
                    if (markerLayer.getVisibleParent(marker) !== marker) { return; }
                    focusNode(n.id);
                });
                mapNodesById[n.id] = { n: n, marker: marker };
            });

            // Add markers to the cluster layer honouring the current Show filter.
            renderMarkers();

            mapFlows.forEach(function (f) { totalSamples += f.count; });

            $('#statSamples').text(totalSamples.toLocaleString());
            $('#statSites').text(sites.toLocaleString());
            $('#statLabs').text(labs.toLocaleString());
            $('#statFlows').text(mapFlows.length.toLocaleString());

            redrawLines();
            if (bounds.length) {
                map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
            }
        }, 'json').fail(function () {
            $('#mapNote').html('<?= _translate("Unable to load the referral map data.", true); ?>').show();
        });
    }

    function loadTable() {
        if (summaryTable) {
            summaryTable.fnDraw();
            return;
        }
        summaryTable = $('#referralSummaryTable').dataTable({
            bAutoWidth: false,
            bProcessing: true,
            bServerSide: true,
            sAjaxSource: '/admin/monitoring/get-referral-summary.php',
            aaSorting: [[5, 'desc']],
            fnServerData: function (sSource, aoData, fnCallback) {
                var f = filterPayload();
                Object.keys(f).forEach(function (k) { aoData.push({ name: k, value: f[k] }); });
                $.ajax({ dataType: 'json', type: 'POST', url: sSource, data: aoData, success: fnCallback });
            }
        });
    }

    function applyFilters() {
        loadMap();
        loadTable();
    }

    // Replace a Tom Select's options from a server-rendered <option> HTML string.
    function loadTomFromOptionHtml(ts, html) {
        var tmp = document.createElement('select');
        tmp.innerHTML = html || '';
        ts.clear(true);
        ts.clearOptions();
        Array.prototype.forEach.call(tmp.options, function (o) {
            if (o.value !== '') { ts.addOption({ value: o.value, text: o.text }); }
        });
        ts.refreshOptions(false);
    }

    function getByProvince() {
        $.post('/common/get-by-province-id.php', {
            provinceId: $('#state').val(), districts: true, facilities: false, labs: true
        }, function (data) {
            var obj = $.parseJSON(data);
            loadTomFromOptionHtml(tsDistrict, obj['districts']);
            loadTomFromOptionHtml(tsLab, obj['labs']);
        });
    }

    $(document).ready(function () {
        var tsOpts = { plugins: ['remove_button'], placeholder: '<?= _translate("-- All --", true); ?>' };
        tsState = new TomSelect('#state', tsOpts);
        tsDistrict = new TomSelect('#district', tsOpts);
        tsLab = new TomSelect('#labName', tsOpts);
        tsTest = new TomSelect('#testType', tsOpts);

        $('#dateRange').daterangepicker({
            locale: { format: 'DD-MMM-YYYY', separator: ' to ' },
            startDate: moment().subtract(179, 'days'),
            endDate: moment(),
            maxDate: moment(),
            ranges: {
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'Last 90 Days': [moment().subtract(89, 'days'), moment()],
                'Last 180 Days': [moment().subtract(179, 'days'), moment()],
                'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')],
                'Current Year To Date': [moment().startOf('year'), moment()],
                'All Time': [moment('2000-01-01'), moment()]
            }
        });

        $('#toggleFilters').on('click', function () { $('#filterPanel').slideToggle(150); });

        $(document).on('change', '#toggleLines', function () {
            showAllLines = this.checked;
            if (focusedId === null) { redrawLines(); }
        });

        $(document).on('change', '#markerFilter', function () {
            markerFilter = this.value;
            // Drop any active focus so a now-hidden node can't stay highlighted.
            // clearFocus() redraws lines itself; otherwise redraw so the new
            // visibility filter is applied (e.g. labs-only leaves only lab->lab lines).
            if (focusedId !== null) { clearFocus(); }
            else { redrawLines(); }
            renderMarkers();
        });

        // Esc exits the expanded map view.
        $(document).on('keyup', function (e) {
            if (e.key === 'Escape' && document.getElementById('referralMap').classList.contains('referral-map-fullscreen')) {
                toggleFullscreen(true);
            }
        });

        initMap();
        applyFilters();
    });
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
