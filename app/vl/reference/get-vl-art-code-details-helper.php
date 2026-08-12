<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$tableName = "r_vl_art_regimen";
$primaryKey = "art_id";

// Index-aligned with the <th> order in vl-art-code-details.php, because DataTables sends
// sort and search positions as absolute column indexes. null marks a column with no
// column behind it -- "Maps To" is assembled from r_vl_art_regimen_alias -- so sorting
// and searching skip it rather than indexing past the end of this array.
$aColumns = ['art_code', 'headings', 'art_source', null, 'art_status'];

/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;

$sOffset = $sLimit = null;
if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
    $sOffset = $_POST['iDisplayStart'];
    $sLimit = $_POST['iDisplayLength'];
}



$sOrder = "";
if (isset($_POST['iSortCol_0'])) {
    $sOrder = "";
    for ($i = 0; $i < (int) $_POST['iSortingCols']; $i++) {
        $sortCol = (int) $_POST['iSortCol_' . $i];
        // Unsortable, or a column with nothing behind it. Skipping keeps a click on
        // "Maps To" from reaching $aColumns[3] and putting "" into the ORDER BY.
        if (!isset($aColumns[$sortCol]) || $_POST['bSortable_' . $sortCol] != "true") {
            continue;
        }
        $sortDir = strtoupper((string) ($_POST['sSortDir_' . $i] ?? '')) === 'DESC' ? 'DESC' : 'ASC';
        $sOrder .= $aColumns[$sortCol] . " " . $sortDir . ", ";
    }
    $sOrder = rtrim($sOrder, ", ");
}


// Bound, not interpolated. sSearch is operator-supplied and reached the query as a raw
// string, so a quote in the search box terminated the LIKE literal.
$sWhere = "";
$queryParams = [];
if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
    $searchArray = array_filter(explode(" ", (string) $_POST['sSearch']), fn($s): bool => trim($s) !== '');
    $searchableColumns = array_values(array_filter($aColumns));
    $clauses = [];
    foreach ($searchArray as $search) {
        $columnClauses = [];
        foreach ($searchableColumns as $column) {
            $columnClauses[] = "$column LIKE ?";
            $queryParams[] = '%' . $search . '%';
        }
        $clauses[] = '(' . implode(' OR ', $columnClauses) . ')';
    }
    $sWhere = implode(' AND ', $clauses);
}




$sQuery = "SELECT * FROM $tableName";

if ($sWhere !== '' && $sWhere !== '0') {
    $sWhere = ' WHERE ' . $sWhere;
    $sQuery = $sQuery . ' ' . $sWhere;
}

if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . (int) $sOffset . ',' . (int) $sLimit;
}

[$rResult, $resultCount] = $db->getDataAndCount($sQuery, $queryParams);

// Materialised because the rows are needed twice: once to look up their aliases in a
// single query, and again to render. getDataAndCount hands back a generator by default.
$rResult = is_array($rResult) ? $rResult : iterator_to_array($rResult);

$canEdit = _isAllowed("/vl/reference/add-vl-art-code-details.php") && $general->isLISInstance() === false;

// Aliases for the codes on this page, in one query rather than one per row.
//
// Matched on external_code against values already in hand, so the comparison stays inside
// r_vl_art_regimen_alias and is bound. Nothing joins external_code to art_code in SQL --
// see the collation note in sys/migrations/5.6.4.sql.
$aliasMap = [];
$pageCodes = array_values(array_filter(array_map(fn($r): string => (string) ($r['art_code'] ?? ''), $rResult), fn($c): bool => $c !== ''));
if ($pageCodes !== []) {
    $placeholders = implode(',', array_fill(0, count($pageCodes), '?'));
    $aliasRows = $db->rawQuery(
        "SELECT external_code, art_id FROM r_vl_art_regimen_alias WHERE external_code IN ($placeholders)",
        $pageCodes
    );
    foreach (($aliasRows ?: []) as $aliasRow) {
        $aliasMap[(string) $aliasRow['external_code']] = (int) $aliasRow['art_id'];
    }
}

// Every regimen offered as a mapping target. Not filtered to active: a target that was
// retired from the dropdown is still a legitimate thing to group an external code under
// for reporting, and hiding it would silently drop an existing mapping from the select.
//
// Loaded whether or not the viewer can edit, because a read-only viewer still has to be
// shown the name of an existing mapping rather than a blank cell.
$targets = ($aliasMap === [] && !$canEdit)
    ? []
    : ($db->rawQuery("SELECT art_id, art_code FROM $tableName ORDER BY art_code") ?: []);


$output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $resultCount, "iTotalDisplayRecords" => $resultCount, "aaData" => []];

foreach ($rResult as $aRow) {
    $status = '<select class="form-control" name="status[]" id="' . $aRow['art_id'] . '" title="' . _translate("Please select status") . '" onchange="updateStatus(this,\'' . $aRow['art_status'] . '\')">
               <option value="active" ' . ($aRow['art_status'] == "active" ? "selected=selected" : "") . '>' . _translate("Active") . '</option>
               <option value="inactive" ' . ($aRow['art_status'] == "inactive" ? "selected=selected" : "") . '>' . _translate("Inactive") . '</option>
               </select>';

    // Where the code came from. Blank for everything that predates art_source, which is
    // every regimen defined by the programme or shipped in the seed.
    $source = trim((string) ($aRow['art_source'] ?? ''));
    if ($source === '') {
        $sourceLabel = '<span class="text-muted">&mdash;</span>';
    } else {
        $sourceLabel = '<span class="label label-warning" title="'
            . _translate("Registered automatically from a value this instance received, not defined here")
            . '">' . htmlspecialchars($source) . '</span>';
    }

    $artCode = (string) ($aRow['art_code'] ?? '');
    $mappedTo = $aliasMap[$artCode] ?? null;

    if ($canEdit) {
        // Self-mapping is meaningless, so a code is never offered as its own target.
        $options = '<option value="">' . _translate("-- Not mapped --") . '</option>';
        foreach ($targets as $target) {
            if ((int) $target['art_id'] === (int) $aRow['art_id']) {
                continue;
            }
            $options .= '<option value="' . (int) $target['art_id'] . '"'
                . ($mappedTo === (int) $target['art_id'] ? ' selected="selected"' : '')
                . '>' . htmlspecialchars((string) $target['art_code']) . '</option>';
        }
        $mapsTo = '<select class="form-control" name="mapsTo[]" id="alias_' . (int) $aRow['art_id'] . '"'
            . ' data-art-code="' . htmlspecialchars($artCode) . '"'
            . ' title="' . _translate("Group this code with another regimen when reporting") . '"'
            . ' onchange="updateAlias(this)">' . $options . '</select>';
    } elseif ($mappedTo !== null) {
        $mapsTo = htmlspecialchars((string) array_reduce(
            $targets,
            fn($carry, $t) => (int) $t['art_id'] === $mappedTo ? $t['art_code'] : $carry,
            ''
        ));
    } else {
        $mapsTo = '<span class="text-muted">&mdash;</span>';
    }

    $row = [];
    $row[] = $aRow['art_code'];
    $row[] = ($aRow['headings']);
    $row[] = $sourceLabel;
    $row[] = $mapsTo;
    if ($canEdit) {
        $row[] = $status;
    } else {
        $row[] = $aRow['art_status'];
    }
    $output['aaData'][] = $row;
}

echo json_encode($output);
