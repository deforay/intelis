<?php

/**
 * Spotlight Search partial.
 *
 * Self-contained: emits the CSS, the client-side menu data, the modal markup
 * and the JS in one block. Include once, right before </body>, on any page
 * that has already loaded jQuery and Font Awesome.
 *
 * Opens with Cmd/Ctrl+K. No-ops when there is no menu (e.g. logged-out pages)
 * so we don't bind an empty modal.
 *
 * @var \App\Services\CommonService $general optional; not required here.
 */

if (empty($_SESSION['menuItems'])) {
    return;
}

// Search synonyms for spotlight — invisible aids so users can reach a page by
// intent / clinical term, not just its exact menu label. These never render;
// they only feed the client-side searchText. Wrapped in _jsTranslate() so a
// deployment's translation file can localize them. Keyed by module + link patterns.
$spotlightModuleKeywords = [
    'dashboard' => ['home', 'overview', 'kpi', 'stats', 'summary'],
    'vl' => ['viral load', 'hiv rna', 'vl', 'plasma'],
    'eid' => ['early infant diagnosis', 'pcr', 'dbs', 'infant', 'baby', 'dna pcr'],
    'tb' => ['tuberculosis', 'genexpert', 'mtb', 'sputum', 'rif'],
    'covid19' => ['covid', 'covid-19', 'sars-cov-2', 'corona', 'coronavirus', 'antigen'],
    'hepatitis' => ['hepatitis', 'hep b', 'hep c', 'hbv', 'hcv', 'viral hepatitis'],
    'generic-tests' => ['other tests', 'lab test', 'generic'],
    'admin' => ['settings', 'configuration', 'setup', 'manage'],
];
$spotlightLinkKeywords = function (string $link, string $title): array {
    $hay = _toLowerCase($link . ' ' . $title);
    $map = [
        'add-request|addvlrequest|addsamples|add-samples|add new request' => ['register', 'new sample', 'enter sample', 'accession', 'create request'],
        'requests|view-requests' => ['worklist', 'pending', 'samples', 'test list'],
        'testresult|manual-results|enter result' => ['enter result', 'edit result', 'report value'],
        'approval|result-status' => ['approve', 'authorize', 'verify', 'release', 'sign off', 'review'],
        'failed' => ['hold', 'retest', 'repeat', 'rerun'],
        'print' => ['report', 'pdf', 'printout'],
        'export' => ['download', 'excel', 'csv', 'extract data'],
        'rejection|reject' => ['rejected', 'declined', 'discarded'],
        'manifest|referral' => ['referral', 'shipment', 'transfer', 'courier', 'dispatch'],
        'batch' => ['batches', 'worklist', 'run'],
        'import' => ['upload', 'load results', 'analyzer file'],
        'sample-status|sample status' => ['tracking', 'tat', 'turnaround', 'where is sample'],
        'users' => ['accounts', 'staff', 'login', 'people'],
        'roles' => ['permissions', 'access control', 'privileges'],
        'facilities' => ['labs', 'clinics', 'sites', 'health facility', 'testing lab'],
        'instruments' => ['analyzer', 'machine', 'equipment', 'device'],
        'audit-trail' => ['changes', 'history', 'who changed'],
        'activity-log' => ['user activity', 'logins', 'actions'],
        'sync' => ['api', 'data sync', 'sts', 'server'],
        'global-config' => ['settings', 'setup', 'preferences'],
        'geographical' => ['province', 'district', 'region', 'location'],
        'control' => ['qc', 'quality control'],
    ];
    $out = [];
    foreach ($map as $patterns => $words) {
        foreach (explode('|', $patterns) as $p) {
            if ($p !== '' && str_contains($hay, $p)) {
                $out = [...$out, ...$words];
                break;
            }
        }
    }
    return $out;
};
$spotlightKeywordsFor = function (string $module, string $link, string $title) use ($spotlightModuleKeywords, $spotlightLinkKeywords): array {
    $keywords = array_values(array_unique([
        ...($spotlightModuleKeywords[$module] ?? []),
        ...$spotlightLinkKeywords($link, $title),
    ]));
    return array_map(fn($k) => _jsTranslate($k), $keywords);
};

// Flatten menu for spotlight - includes parent menus with expandable children
$flattenMenuForSpotlight = function (array $menuItems, array $parentPath = []) use (&$flattenMenuForSpotlight, $spotlightKeywordsFor): array {
    $flatList = [];
    foreach ($menuItems as $menu) {
        $menuTitle = _jsTranslate($menu['display_text']);
        $currentPath = $parentPath;

        // Skip headers but process their children
        if (($menu['is_header'] ?? 'no') === 'yes') {
            $currentPath[] = $menuTitle;
            if (!empty($menu['children'])) {
                $flatList = [...$flatList, ...$flattenMenuForSpotlight($menu['children'], $currentPath)];
            }
            continue;
        }

        $link = $menu['link'] ?? '';
        $hasChildren = ($menu['has_children'] ?? 'no') === 'yes' && !empty($menu['children']);
        $hasValidLink = $link !== '' && $link !== '#' && !str_starts_with($link, '#');

        $category = !empty($parentPath) ? end($parentPath) : _jsTranslate('Navigation');
        $subcategory = count($parentPath) > 1 ? implode(' → ', array_slice($parentPath, 0, -1)) : '';

        // Parent menu with children - make it expandable
        if ($hasChildren) {
            $actions = [];
            foreach ($menu['children'] as $child) {
                $childLink = $child['link'] ?? '';
                if ($childLink !== '' && $childLink !== '#' && !str_starts_with($childLink, '#')) {
                    $actions[] = [
                        'label' => _jsTranslate($child['display_text']),
                        'url' => $childLink,
                        'icon' => $child['icon'] ?? 'fa-solid fa-arrow-right',
                    ];
                }
            }
            if (!empty($actions)) {
                $flatList[] = [
                    'id' => 'menu-' . $menu['id'],
                    'title' => $menuTitle,
                    'url' => $actions[0]['url'], // Default to first child
                    'icon' => $menu['icon'] ?? 'fa-solid fa-folder',
                    'category' => $category,
                    'subcategory' => $subcategory,
                    'module' => $menu['module'] ?? '',
                    'sortOrder' => (int) ($menu['sort_order'] ?? 0),
                    'keywords' => $spotlightKeywordsFor($menu['module'] ?? '', $link, $menuTitle),
                    'actions' => $actions,
                    'isExpandable' => true,
                ];
            }
            // Also process children recursively for deeper nesting
            $currentPath[] = $menuTitle;
            $flatList = [...$flatList, ...$flattenMenuForSpotlight($menu['children'], $currentPath)];
        } elseif ($hasValidLink) {
            // Regular menu item with direct link
            $flatList[] = [
                'id' => 'menu-' . $menu['id'],
                'title' => $menuTitle,
                'url' => $link,
                'icon' => $menu['icon'] ?? 'fa-solid fa-file',
                'category' => $category,
                'subcategory' => $subcategory,
                'module' => $menu['module'] ?? '',
                'sortOrder' => (int) ($menu['sort_order'] ?? 0),
                'keywords' => $spotlightKeywordsFor($menu['module'] ?? '', $link, $menuTitle),
            ];
        }
    }
    return $flatList;
};
$spotlightCacheKey = 'spotlight_menu_' . ($_SESSION['userId'] ?? 'default') . '_' . ($_SESSION['APP_LOCALE'] ?? 'en');
$spotlightData = \App\Utilities\MemoUtility::memo($spotlightCacheKey, fn() => $flattenMenuForSpotlight($_SESSION['menuItems'] ?? []), 300);
?>
<link rel="stylesheet" media="all" type="text/css"
    href="/assets/css/spotlight-search.css?v=<?= filemtime(WEB_ROOT . '/assets/css/spotlight-search.css'); ?>" />

<script>
    window.spotlightData = <?= \App\Utilities\JsonUtility::encodeUtf8Json($spotlightData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.spotlightUserId = '<?= $_SESSION['userId'] ?? 'default'; ?>';
    window.spotlightTranslations = {
        recent: '<?= _jsTranslate('Recent'); ?>',
        noResults: '<?= _jsTranslate('No results found'); ?>'
    };
</script>

<!-- Spotlight Search Modal -->
<div id="spotlightModal" class="spotlight-modal" style="display: none;">
    <div class="spotlight-backdrop"></div>
    <div class="spotlight-dialog">
        <div class="spotlight-search-wrapper">
            <i class="fa-solid fa-magnifying-glass spotlight-icon"></i>
            <input type="text" id="spotlightInput" class="spotlight-input"
                placeholder="<?= _translate('Search menus, actions...'); ?>" autocomplete="off"
                spellcheck="false" role="combobox" aria-expanded="false" aria-autocomplete="list"
                aria-controls="spotlightResults" aria-activedescendant=""
                aria-label="<?= _translate('Quick Search'); ?>">
            <span class="spotlight-shortcut">ESC</span>
        </div>
        <div id="spotlightResults" class="spotlight-results" role="listbox"
            aria-label="<?= _translate('Search results'); ?>"></div>
        <div class="spotlight-footer">
            <span><kbd>↑</kbd><kbd>↓</kbd> <?= _translate('Navigate'); ?></span>
            <span><kbd>Enter</kbd> <?= _translate('Open'); ?></span>
            <span><kbd>Esc</kbd> <?= _translate('Close'); ?></span>
        </div>
    </div>
</div>

<script type="text/javascript"
    src="/assets/js/spotlight-search.js?v=<?= filemtime(WEB_ROOT . '/assets/js/spotlight-search.js'); ?>"></script>
