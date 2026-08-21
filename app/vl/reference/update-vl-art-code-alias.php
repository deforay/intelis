<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/*
 * Maps one ART regimen code onto another for reporting, or clears that mapping.
 *
 * Purely additive, and deliberately so. Nothing here edits an art_code or rewrites a
 * form_vl row: current_regimen stores the code text rather than a reference to art_id, so
 * changing either would reinterpret history — the same reason the reference page has no
 * Edit button. A mapping records that two codes name the same regimen; it does not claim
 * that a sample was recorded under a code it was not.
 *
 * The mapping is therefore read-time only. VlService::resolveArtRegimen() does not consult
 * this table, so what an incoming request stores is unaffected by any mapping made here,
 * before or after. Reporting that groups by regimen joins through it.
 *
 * Deactivating a mapped code is NOT a follow-up step. The request and result forms build
 * the dropdown from art_status = 'active', so retiring a code that samples still hold puts
 * those samples back to an empty dropdown, where the next save writes the blank over the
 * stored value.
 */

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "r_vl_art_regimen_alias";
$result = '';

try {
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    // Same gate as the status control on this page.
    if (!_isAllowed("/vl/reference/add-vl-art-code-details.php") || $general->isLISInstance() !== false) {
        http_response_code(403);
        echo '';
        return;
    }

    $artId = (int) ($_POST['id'] ?? 0);
    $targetId = (int) ($_POST['mapsTo'] ?? 0);

    if ($artId <= 0) {
        throw new InvalidArgumentException('Missing ART regimen id');
    }

    // Resolve the code from its id rather than trusting the posted string, so the alias
    // key always matches a real row.
    $source = $db->rawQueryOne("SELECT art_id, art_code FROM r_vl_art_regimen WHERE art_id = ?", [$artId]);
    if (empty($source['art_code'])) {
        throw new InvalidArgumentException('Unknown ART regimen id');
    }
    $externalCode = (string) $source['art_code'];

    if ($targetId <= 0) {
        // Cleared.
        $db->where('external_code', $externalCode);
        $db->delete($tableName);

        $general->activityLog(
            'Update art code alias',
            $_SESSION['userName'] . ' removed the reporting mapping for ART regimen ' . $externalCode,
            'vl-reference'
        );
        $result = 'cleared';
    } else {
        if ($targetId === $artId) {
            throw new InvalidArgumentException('An ART regimen cannot map to itself');
        }

        $target = $db->rawQueryOne("SELECT art_id, art_code FROM r_vl_art_regimen WHERE art_id = ?", [$targetId]);
        if (empty($target['art_code'])) {
            throw new InvalidArgumentException('Unknown mapping target');
        }

        // A mapping onto a code that is itself mapped would make regimen grouping depend
        // on how far the chain is followed. One hop only; remap the first code instead.
        $targetAlias = $db->rawQueryOne(
            "SELECT alias_id FROM $tableName WHERE external_code = ?",
            [(string) $target['art_code']]
        );
        if (!empty($targetAlias)) {
            throw new InvalidArgumentException('Target is itself mapped to another regimen');
        }

        $data = [
            'external_code' => $externalCode,
            'art_id' => $targetId,
            'alias_source' => 'admin',
            // The whole session user id, uncast. mapped_by was an INT until 5.7.30 and
            // silently kept only the leading digits of the UUID, so who mapped a code
            // was discarded rather than recorded. Do not narrow it again.
            'mapped_by' => $_SESSION['userId'] ?? null,
            'updated_datetime' => DateUtility::getCurrentDateTime(),
        ];

        // external_code is UNIQUE: one incoming string resolves to one regimen, so a
        // re-map replaces the row rather than adding a second.
        $existing = $db->rawQueryOne("SELECT alias_id FROM $tableName WHERE external_code = ?", [$externalCode]);
        if (!empty($existing['alias_id'])) {
            $db->where('alias_id', $existing['alias_id']);
            $db->update($tableName, $data);
        } else {
            $db->insert($tableName, $data);
        }

        $general->activityLog(
            'Update art code alias',
            $_SESSION['userName'] . ' mapped ART regimen ' . $externalCode . ' to ' . $target['art_code'] . ' for reporting',
            'vl-reference'
        );
        $result = 'mapped';
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    LoggerUtility::logError('Unable to update ART regimen alias: ' . $e->getMessage());
    $result = '';
} catch (Throwable $exc) {
    http_response_code(500);
    LoggerUtility::logError($exc->getMessage(), [
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'last_db_error' => $db->getLastError(),
    ]);
    $result = '';
}

echo $result;
