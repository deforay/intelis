<?php

use App\Services\AppMenuService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

// Excluded from AclMiddleware: this page has no privilege of its own. It lists
// the per-test reference pages from the menu table, already filtered by active
// module and by role, and refuses anyone who may open none of them.

/** @var AppMenuService $appMenuService */
$appMenuService = ContainerRegistry::get(AppMenuService::class);
$hub = $appMenuService->getHub('/admin/test-settings.php');
if ($hub === null) {
    throw new SystemException(_translate('You do not have permission to access this page or resource.'), 403);
}

// One tab per test type that has at least one page this user can open.
$tabs = [];
foreach ($hub['hub_children'] as $group) {
    $links = [];
    $pages = [];
    foreach ($group['children'] ?? [] as $item) {
        $link = (string) ($item['link'] ?? '');
        if ($link === '' || str_starts_with($link, '#')) {
            continue;
        }
        $links[] = ['text' => $item['display_text'], 'link' => $link];
        $pages = [...$pages, $link, ...array_filter(explode(',', (string) ($item['inner_pages'] ?? '')))];
    }
    if ($links !== []) {
        $tabs[] = [
            'id' => 'test-settings-' . (int) $group['id'],
            'text' => $group['display_text'],
            'icon' => $group['icon'] ?: 'fa-solid fa-vial',
            'links' => $links,
            'pages' => $pages,
        ];
    }
}

$title = _translate("Test Settings") . " - " . _translate("Admin");
require_once APPLICATION_PATH . '/header.php';
?>
<style>
    #testSettings .ts-links {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 10px;
        margin: 15px 0 5px;
    }

    #testSettings .ts-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border: 1px solid #e4e8ec;
        border-radius: 3px;
        background-color: #f8fafb;
        color: #333;
        font-weight: 600;
    }

    #testSettings .ts-link:hover,
    #testSettings .ts-link:focus {
        border-color: #3c8dbc;
        background-color: #eaf3fa;
        color: #2a6d96;
        text-decoration: none;
    }

    #testSettings .ts-link .fa-angle-right {
        margin-left: auto;
        color: #9aa5ad;
    }
</style>
<div class="content-wrapper" id="testSettings">
    <section class="content-header">
        <h1><em class="fa-solid fa-sliders"></em> <?= _htmlTranslate("Test Settings"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?= _htmlTranslate("Home"); ?></a></li>
            <li><?= _htmlTranslate("Admin"); ?></li>
            <li class="active"><?= _htmlTranslate("Test Settings"); ?></li>
        </ol>
    </section>
    <section class="content">
        <div class="box box-primary">
            <div class="box-body">
                <ul class="nav nav-tabs" role="tablist">
                    <?php foreach ($tabs as $i => $tab) { ?>
                        <li role="presentation" class="<?= $i === 0 ? 'active' : ''; ?>">
                            <a href="#<?= $tab['id']; ?>" aria-controls="<?= $tab['id']; ?>" role="tab" data-toggle="tab"
                                data-pages="<?= htmlspecialchars(implode(',', $tab['pages']), ENT_QUOTES, 'UTF-8'); ?>">
                                <em class="<?= htmlspecialchars((string) $tab['icon'], ENT_QUOTES, 'UTF-8'); ?>"></em>
                                <?= _htmlTranslate($tab['text']); ?>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
                <div class="tab-content">
                    <?php foreach ($tabs as $i => $tab) { ?>
                        <div role="tabpanel" class="tab-pane<?= $i === 0 ? ' active' : ''; ?>" id="<?= $tab['id']; ?>">
                            <div class="ts-links">
                                <?php foreach ($tab['links'] as $item) { ?>
                                    <a class="ts-link" href="<?= htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= _htmlTranslate($item['text']); ?>
                                        <em class="fa-solid fa-angle-right" aria-hidden="true"></em>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </section>
</div>
<script>
    $(function() {
        // Open the tab named in the URL, else the tab of the settings page the
        // user just came back from, else the one they last used.
        const storageKey = 'testSettingsTab';
        const $tabs = $('#testSettings a[data-toggle="tab"]');
        // Compared as strings, never built into a selector: the hash and the
        // stored value come from outside the page.
        const tabFor = id => $tabs.filter(function() {
            return $(this).attr('href') === '#' + id;
        });
        let $open = tabFor(window.location.hash.substring(1));

        if (!$open.length && document.referrer) {
            try {
                const from = new URL(document.referrer);
                if (from.origin === window.location.origin) {
                    const candidates = [from.pathname + from.search, from.pathname];
                    $open = $tabs.filter(function() {
                        const pages = String($(this).data('pages') || '').split(',');
                        return candidates.some(page => pages.includes(page));
                    }).first();
                }
            } catch (e) {
                $open = $();
            }
        }

        if (!$open.length) {
            try {
                $open = tabFor(localStorage.getItem(storageKey));
            } catch (e) {
                $open = $();
            }
        }

        if ($open.length) {
            $open.tab('show');
        }

        $tabs.on('shown.bs.tab', function(e) {
            const id = $(e.target).attr('href').substring(1);
            try {
                localStorage.setItem(storageKey, id);
            } catch (err) {
                // Remembering the tab is a convenience only.
            }
            history.replaceState(null, '', '#' + id);
        });
    });
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
