<?php

namespace App\Services;

use Throwable;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Bulk facility upload in two steps: stage() reads the sheet and works out what
 * each row would do without writing anything; apply() writes a reviewed batch.
 * apply() re-plans every row against the database as it is at that moment, so
 * a row whose outcome changed since review is refused instead of written blind.
 *
 * Sheet layout (A-L) is FacilitiesService::bulkUploadHeadings(), which the
 * facility export also writes.
 */
final class FacilityImportService
{
    public const OPTION_DEFAULT = 'default';
    public const OPTION_NAME = 'facility_name_match';
    public const OPTION_CODE = 'facility_code_match';
    public const OPTION_NAME_CODE = 'facility_name_code_match';

    public const ACTION_INSERT = 'insert';
    public const ACTION_UPDATE = 'update';
    public const ACTION_UNCHANGED = 'unchanged';
    public const ACTION_SKIP = 'skip';
    public const ACTION_ERROR = 'error';

    private const COLUMNS = 12;
    private const MAX_ROWS = 20000;
    private const BATCH_TTL_SECONDS = 86400;

    /** Sheet column index => facility_details column, for the optional fields. */
    private const OPTIONAL_FIELDS = [
        2 => 'other_id',
        6 => 'address',
        7 => 'facility_emails',
        8 => 'facility_mobile_numbers',
        9 => 'latitude',
        10 => 'longitude',
    ];

    private array $facilitiesById = [];
    private array $idByName = [];
    private array $idByCode = [];
    private array $idByOtherId = [];
    private array $idByLooseName = [];
    private array $idsByDistrict = [];
    private array $idByCoordinates = [];
    private array $provinceIdByName = [];
    private array $districtIdByKey = [];
    private array $facilityTypes = [];

    public function __construct(
        private readonly DatabaseService $db,
        private readonly FacilitiesService $facilitiesService,
        private readonly CommonService $general
    ) {
    }

    public static function options(): array
    {
        return [self::OPTION_DEFAULT, self::OPTION_NAME, self::OPTION_CODE, self::OPTION_NAME_CODE];
    }

    /**
     * Reads the uploaded sheet, plans every row and saves the batch for review.
     *
     * @return string Batch id to pass to loadBatch()/apply()
     */
    public function stage(string $xlsxPath, string $option, string $originalFileName): string
    {
        $rows = $this->readSheet($xlsxPath);
        $batchId = MiscUtility::generateRandomString(24);

        $batch = [
            'id' => $batchId,
            'userId' => (string) ($_SESSION['userId'] ?? ''),
            'option' => $option,
            'fileName' => $originalFileName,
            'createdOn' => DateUtility::getCurrentDateTime(),
            'rows' => $this->plan($rows, $option),
        ];

        $this->purgeExpiredBatches();
        $path = $this->batchPath($batchId);
        if ($path === null || file_put_contents($path, json_encode($batch, JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            throw new \RuntimeException('Unable to save the staged facility import');
        }
        return $batchId;
    }

    /** The staged batch, or null when the id is unknown, expired or belongs to someone else. */
    public function loadBatch(string $batchId): ?array
    {
        $path = $this->batchPath($batchId);
        if ($path === null || !is_file($path) || filemtime($path) < time() - self::BATCH_TTL_SECONDS) {
            return null;
        }
        $batch = json_decode((string) file_get_contents($path), true);
        if (!is_array($batch) || ($batch['userId'] ?? null) !== (string) ($_SESSION['userId'] ?? '')) {
            return null;
        }
        return $batch;
    }

    public function discardBatch(string $batchId): void
    {
        $path = $this->batchPath($batchId);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Writes the rows of a reviewed batch that the reviewer ticked. Rows are
     * re-planned first; a row is written only when it still plans to the same
     * action and the same changes as reviewed.
     *
     * @param list<int> $selectedRows Sheet row numbers to write
     * @return array{inserted:int, updated:int, unchanged:int, excluded:int, failed:list<array>}
     */
    public function apply(array $batch, array $selectedRows): array
    {
        $selected = array_flip(array_map('intval', $selectedRows));
        $reviewed = $batch['rows'];
        $current = $this->plan(array_map(fn($r) => ['row' => $r['row'], 'values' => $r['values']], $reviewed), $batch['option']);

        $result = ['inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'excluded' => 0, 'failed' => []];
        $instanceId = $_SESSION['instanceId'] ?? $this->general->getInstanceId() ?? '';

        foreach ($current as $i => $row) {
            $was = $reviewed[$i];
            if (!in_array($was['action'], [self::ACTION_INSERT, self::ACTION_UPDATE], true)) {
                if ($was['action'] === self::ACTION_UNCHANGED) {
                    $result['unchanged']++;
                }
                continue;
            }
            if (!isset($selected[(int) $was['row']])) {
                $result['excluded']++;
                continue;
            }
            if ($row['action'] !== $was['action'] || $row['changes'] != $was['changes']) {
                $row['messages'][] = _translate('Facility data changed after review. Upload the file again.');
                $result['failed'][] = $row;
                continue;
            }

            try {
                $data = $row['data'];
                $data['facility_state_id'] = $this->facilitiesService->getOrCreateProvince($data['facility_state']);
                $data['facility_district_id'] = $this->facilitiesService->getOrCreateDistrict($data['facility_district'], null, $data['facility_state_id']);
                $data['updated_datetime'] = DateUtility::getCurrentDateTime();

                if ($row['action'] === self::ACTION_INSERT) {
                    if (empty($data['facility_code']) && (int) $data['facility_type'] === 2) {
                        $data['facility_code'] = $this->facilitiesService->generateFacilityCode($data['facility_name']);
                    }
                    $data['vlsm_instance_id'] = $instanceId;
                    $data['status'] ??= 'active';
                    $ok = $this->db->insert('facility_details', $data);
                } else {
                    $this->db->where('facility_id', $row['facilityId']);
                    $ok = $this->db->update('facility_details', $data);
                }

                if ($ok === false) {
                    throw new \RuntimeException((string) $this->db->getLastError());
                }
                $result[$row['action'] === self::ACTION_INSERT ? 'inserted' : 'updated']++;
            } catch (Throwable $e) {
                LoggerUtility::logError('Bulk facility import row failed: ' . $e->getMessage(), [
                    'row' => $row['row'],
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $row['messages'][] = _translate('Could not be saved. Check that the name, code and external code are not already used.');
                $result['failed'][] = $row;
            }
        }

        return $result;
    }

    /**
     * The blank upload sheet, headings in the current language. The name carries
     * a hash of the headings, so a new language or heading gets a new file; it is
     * written to a temporary name and renamed so a concurrent download never
     * reads a half-written file.
     */
    public function templateFile(): string
    {
        $headings = FacilitiesService::bulkUploadHeadings();
        $path = $this->batchDirectory() . DIRECTORY_SEPARATOR
            . 'Facilities_Bulk_Upload_Excel_Format-' . substr(hash('sha256', json_encode($headings)), 0, 12) . '.xlsx';
        if (!is_file($path)) {
            $tmp = $path . '.' . MiscUtility::generateRandomString(8) . '.tmp';
            $writer = new Writer();
            $writer->openToFile($tmp);
            $writer->addRow(Row::fromValues($headings));
            $writer->close();
            rename($tmp, $path);
        }
        return $path;
    }

    /**
     * Writes rows (as planned) to an xlsx in the upload layout plus a "Problems"
     * column, so they can be corrected and uploaded again.
     */
    public function writeRowsFile(array $rows, string $filePath): void
    {
        $writer = new Writer();
        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues([...FacilitiesService::bulkUploadHeadings(), _translate('Problems')]));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues([...$row['values'], implode(' ', [...$row['messages'], ...($row['warnings'] ?? [])])]));
        }
        $writer->close();
    }

    /** @return list<array{row:int, values:list<string>}> */
    private function readSheet(string $xlsxPath): array
    {
        $reader = new Reader();
        $reader->open($xlsxPath);
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rowNumber = 0;
                foreach ($sheet->getRowIterator() as $sheetRow) {
                    $rowNumber++;
                    if ($rowNumber === 1) {
                        continue; // headings
                    }
                    $values = [];
                    foreach (array_slice(array_pad($sheetRow->toArray(), self::COLUMNS, ''), 0, self::COLUMNS) as $value) {
                        $values[] = $this->cellToString($value);
                    }
                    if (implode('', $values) === '') {
                        continue;
                    }
                    if (count($rows) >= self::MAX_ROWS) {
                        throw new \InvalidArgumentException(sprintf(_translate('The file has more than %d facilities. Split it into smaller files.'), self::MAX_ROWS));
                    }
                    $rows[] = ['row' => $rowNumber, 'values' => $values];
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }
        return $rows;
    }

    private function cellToString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
            return (string) (int) $value;
        }
        return trim(html_entity_decode((string) $value));
    }

    /** @param list<array{row:int, values:list<string>}> $rows */
    private function plan(array $rows, string $option): array
    {
        $this->loadReferenceData();

        $planned = [];
        $seen = ['name' => [], 'code' => [], 'otherId' => [], 'looseName' => []];

        foreach ($rows as $input) {
            $v = $input['values'];
            $row = [
                'row' => $input['row'],
                'values' => $v,
                'action' => self::ACTION_ERROR,
                'facilityId' => null,
                'changes' => [],
                'messages' => [],
                'warnings' => [],
                'data' => [],
            ];

            $name = $v[0];
            $rawCode = $v[1];
            // A code already on file is kept exactly as stored (older codes can
            // predate the current format); only new codes are normalised.
            $storedCodeId = $rawCode !== '' ? ($this->idByCode[strtoupper($rawCode)] ?? null) : null;
            $code = $storedCodeId !== null
                ? (string) $this->facilitiesById[$storedCodeId]['facility_code']
                : $this->facilitiesService->sanitizeFacilityCode($rawCode);
            $otherId = $v[2];
            $province = $v[3];
            $district = $v[4];
            $type = $this->resolveFacilityType($v[5]);
            $status = strtolower($v[11]);

            $errors = [];
            if ($name === '') {
                $errors[] = _translate('Facility Name is required.');
            }
            if ($province === '') {
                $errors[] = _translate('Province/State is required.');
            }
            if ($district === '') {
                $errors[] = _translate('District/County is required.');
            }
            if ($type === null) {
                $errors[] = _translate('Facility Type must be 1 (Health Facility), 2 (Testing Lab) or 3 (Collection Site).');
            }
            if ($status !== '' && !in_array($status, ['active', 'inactive'], true)) {
                $errors[] = _translate('Status must be active or inactive.');
            }
            if ($v[9] !== '' && (!is_numeric($v[9]) || abs((float) $v[9]) > 90)) {
                $errors[] = _translate('Latitude must be a number between -90 and 90.');
            }
            if ($v[10] !== '' && (!is_numeric($v[10]) || abs((float) $v[10]) > 180)) {
                $errors[] = _translate('Longitude must be a number between -180 and 180.');
            }
            if ($rawCode !== '' && $code === '') {
                $errors[] = _translate('Facility Code has no usable letters or digits.');
            } elseif ($storedCodeId === null && $code !== $rawCode) {
                $row['messages'][] = sprintf(_translate('Facility Code will be saved as %s.'), $code);
            }

            $nameKey = mb_strtolower($name);
            $repeatLabels = [
                'name' => _translate('Facility Name'),
                'code' => _translate('Facility Code'),
                'otherId' => _translate('External Facility Code'),
            ];
            foreach (['name' => $nameKey, 'code' => strtoupper($code), 'otherId' => mb_strtolower($otherId)] as $kind => $key) {
                if ($key === '') {
                    continue;
                }
                if (isset($seen[$kind][$key])) {
                    $errors[] = sprintf(_translate('%s is repeated from row %d.'), $repeatLabels[$kind], $seen[$kind][$key]);
                } else {
                    $seen[$kind][$key] = $input['row'];
                }
            }

            $looseName = $this->looseName($name);
            // Exact repeats are already errors; this catches "St. Mary's" vs "St Marys".
            if ($looseName !== '' && ($seen['name'][$nameKey] ?? null) === $input['row']) {
                if (isset($seen['looseName'][$looseName])) {
                    $row['warnings'][] = sprintf(_translate('Name looks like the facility in row %d. Check this is not the same facility twice.'), $seen['looseName'][$looseName]);
                } else {
                    $seen['looseName'][$looseName] = $input['row'];
                }
            }

            if ($errors !== []) {
                $row['messages'] = [...$errors, ...$row['messages']];
                $planned[] = $row;
                continue;
            }

            $byName = $this->idByName[$nameKey] ?? null;
            $byCode = $code !== '' ? ($this->idByCode[strtoupper($code)] ?? null) : null;

            // Which existing facility (if any) this row updates, per the chosen option.
            $targetId = null;
            $skipReason = null;
            switch ($option) {
                case self::OPTION_NAME:
                    $targetId = $byName;
                    break;
                case self::OPTION_CODE:
                    $targetId = $byCode;
                    if ($code === '' && $byName !== null) {
                        $skipReason = _translate('Facility Code is blank, so this row cannot be matched by code.');
                    }
                    break;
                case self::OPTION_NAME_CODE:
                    if ($code === '' && $byName !== null) {
                        $skipReason = _translate('Facility Code is blank, so this row cannot be matched by code.');
                    } elseif ($byName !== null && $byName === $byCode) {
                        $targetId = $byName;
                    } elseif ($byName !== null || $byCode !== null) {
                        $skipReason = _translate('Facility Name and Facility Code do not both match the same facility.');
                    }
                    break;
                default:
                    if ($byName !== null || $byCode !== null) {
                        $skipReason = _translate('A facility with this name or code already exists.');
                    }
            }

            if ($skipReason !== null) {
                $row['action'] = self::ACTION_SKIP;
                $row['facilityId'] = $byName ?? $byCode;
                $row['messages'][] = $skipReason;
                $planned[] = $row;
                continue;
            }

            // A value used by a different facility would break its unique index.
            $conflicts = [];
            if ($byName !== null && $byName !== $targetId) {
                $conflicts[] = _translate('Facility Name is already used by another facility.');
            }
            if ($byCode !== null && $byCode !== $targetId) {
                $conflicts[] = sprintf(_translate('Facility Code is already used by %s.'), $this->facilitiesById[$byCode]['facility_name']);
            }
            $byOtherId = $otherId !== '' ? ($this->idByOtherId[mb_strtolower($otherId)] ?? null) : null;
            if ($byOtherId !== null && $byOtherId !== $targetId) {
                $conflicts[] = sprintf(_translate('External Facility Code is already used by %s.'), $this->facilitiesById[$byOtherId]['facility_name']);
            }
            if ($conflicts !== []) {
                $row['messages'] = [...$conflicts, ...$row['messages']];
                $planned[] = $row;
                continue;
            }

            $data = [
                'facility_name' => $name,
                'facility_type' => $type,
            ];
            if ($code !== '') {
                $data['facility_code'] = $code;
            }
            if ($status !== '') {
                $data['status'] = $status;
            }
            foreach (self::OPTIONAL_FIELDS as $col => $field) {
                // Blank optional cells leave the stored value alone on update.
                if ($v[$col] !== '') {
                    $data[$field] = $v[$col];
                }
            }

            $provinceId = $this->provinceIdByName[mb_strtolower($province)] ?? null;
            $districtId = $provinceId !== null ? ($this->districtIdByKey[$provinceId . '|' . mb_strtolower($district)] ?? null) : null;
            if ($provinceId === null) {
                $row['messages'][] = sprintf(_translate('New Province/State %s will be added.'), $province);
            }
            if ($districtId === null) {
                $row['messages'][] = sprintf(_translate('New District/County %s will be added.'), $district);
            }

            if ($targetId === null) {
                $row['action'] = self::ACTION_INSERT;
                if ($code === '' && $type === 2) {
                    $row['messages'][] = _translate('A Facility Code will be generated for this testing lab.');
                }
                $row['warnings'] = [...$row['warnings'], ...$this->possibleDuplicateWarnings($name, $districtId, $data)];
            } else {
                $existing = $this->facilitiesById[$targetId];
                $row['facilityId'] = $targetId;
                // Compared by the linked place, not the text copy kept on the facility.
                if ($provinceId === null || $provinceId !== (int) $existing['facility_state_id']) {
                    $row['changes']['facility_state'] = [(string) $existing['facility_state'], $province];
                }
                if ($districtId === null || $districtId !== (int) $existing['facility_district_id']) {
                    $row['changes']['facility_district'] = [(string) $existing['facility_district'], $district];
                }
                foreach ($data as $field => $value) {
                    $old = (string) ($existing[$field] ?? '');
                    if ($old !== (string) $value) {
                        $row['changes'][$field] = [$old, (string) $value];
                    }
                }
                $row['action'] = $row['changes'] === [] ? self::ACTION_UNCHANGED : self::ACTION_UPDATE;
                $row['warnings'] = [...$row['warnings'], ...$this->riskyChangeWarnings($existing, $row['changes'])];
            }

            $row['data'] = $data + ['facility_state' => $province, 'facility_district' => $district];
            $planned[] = $row;
        }

        return $planned;
    }

    /**
     * Flags a new facility that is probably one already on file under a
     * slightly different name, or at the same spot.
     *
     * @return list<string>
     */
    private function possibleDuplicateWarnings(string $name, ?int $districtId, array $data): array
    {
        $warnings = [];
        $looseName = $this->looseName($name);

        if ($looseName !== '' && isset($this->idByLooseName[$looseName])) {
            $match = $this->facilitiesById[$this->idByLooseName[$looseName]];
            $warnings[] = sprintf(_translate('Name is almost the same as existing facility %s. Check this is a new facility.'), $match['facility_name']);
        } elseif ($looseName !== '' && $districtId !== null) {
            foreach ($this->idsByDistrict[$districtId] ?? [] as $id) {
                $other = $this->looseName((string) $this->facilitiesById[$id]['facility_name']);
                if ($other !== '' && $this->similarity($looseName, $other) >= 85) {
                    $warnings[] = sprintf(_translate('Name is close to %s in the same district. Check this is a new facility.'), $this->facilitiesById[$id]['facility_name']);
                    break;
                }
            }
        }

        $spot = $this->coordinateKey($data['latitude'] ?? null, $data['longitude'] ?? null);
        if ($spot !== null && ($this->idByCoordinates[$spot] ?? null) !== null) {
            $warnings[] = sprintf(_translate('Coordinates are the same as existing facility %s.'), $this->facilitiesById[$this->idByCoordinates[$spot]]['facility_name']);
        }

        return $warnings;
    }

    /**
     * Flags updates that are legitimate but often a sign the row was matched to
     * the wrong facility or edited by mistake.
     *
     * @param array<string, array{0:string, 1:string}> $changes
     * @return list<string>
     */
    private function riskyChangeWarnings(array $existing, array $changes): array
    {
        $warnings = [];

        if (isset($changes['facility_name'])) {
            [$old, $new] = $changes['facility_name'];
            if ($this->similarity($this->looseName($old), $this->looseName($new)) < 60) {
                $warnings[] = sprintf(_translate('Facility Name changes a lot, from %s to %s. Check this row is the same facility.'), $old, $new);
            }
        }
        if (isset($changes['facility_type'])) {
            $warnings[] = sprintf(
                _translate('Facility Type changes from %s to %s.'),
                $this->facilityTypes[$changes['facility_type'][0]] ?? $changes['facility_type'][0],
                $this->facilityTypes[$changes['facility_type'][1]] ?? $changes['facility_type'][1]
            );
        }
        if (isset($changes['facility_code']) && $changes['facility_code'][0] !== '' && (int) $existing['facility_type'] === 2) {
            $warnings[] = _translate('Facility Code of a testing lab changes. The code is part of the sample codes this lab generates.');
        }
        if (isset($changes['other_id']) && $changes['other_id'][0] !== '') {
            $warnings[] = _translate('External Facility Code changes. Other systems that match on the old code will no longer find this facility.');
        }
        if (isset($changes['facility_state'])) {
            $warnings[] = _translate('Facility moves to a different Province/State.');
        }
        if (($changes['status'][1] ?? null) === 'inactive') {
            $warnings[] = _translate('Facility will be made inactive.');
        }
        if (
            (isset($changes['latitude']) || isset($changes['longitude']))
            && $this->coordinateKey($existing['latitude'], $existing['longitude']) !== null
        ) {
            $lat = $changes['latitude'][1] ?? $existing['latitude'];
            $lng = $changes['longitude'][1] ?? $existing['longitude'];
            $km = $this->distanceKm((float) $existing['latitude'], (float) $existing['longitude'], (float) $lat, (float) $lng);
            if ($km > 50) {
                $warnings[] = sprintf(_translate('Coordinates move the facility about %d km.'), (int) round($km));
            }
        }

        return $warnings;
    }

    /**
     * Coordinates rounded to about 100 m, or null when missing or a 0,0
     * placeholder (which many facilities share and would match everything).
     */
    private function coordinateKey(mixed $lat, mixed $lng): ?string
    {
        if (!is_numeric($lat) || !is_numeric($lng) || (abs((float) $lat) < 0.001 && abs((float) $lng) < 0.001)) {
            return null;
        }
        return round((float) $lat, 3) . '|' . round((float) $lng, 3);
    }

    /** Name reduced to letters and digits, for spotting near-identical names. */
    private function looseName(string $name): string
    {
        return str_replace('-', '', $this->facilitiesService->sanitizeFacilityCode($name, 255));
    }

    /** Percentage similarity of two strings (0-100). */
    private function similarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 100.0;
        }
        similar_text($a, $b, $percent);
        return $percent;
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function resolveFacilityType(string $value): ?int
    {
        if (isset($this->facilityTypes[$value])) {
            return (int) $value;
        }
        // Accept the type name too ("Testing Lab"), as older sheets carried it.
        foreach ($this->facilityTypes as $id => $typeName) {
            if ($value !== '' && strcasecmp($typeName, $value) === 0) {
                return (int) $id;
            }
        }
        return null;
    }

    private function loadReferenceData(): void
    {
        $this->facilitiesById = $this->idByName = $this->idByCode = $this->idByOtherId = [];
        $this->idByLooseName = $this->idsByDistrict = $this->idByCoordinates = [];
        $this->provinceIdByName = $this->districtIdByKey = $this->facilityTypes = [];

        $facilities = $this->db->rawQueryGenerator(
            'SELECT facility_id, facility_name, facility_code, other_id, facility_state, facility_district, facility_state_id, facility_district_id,
                    facility_type, status, address, facility_emails, facility_mobile_numbers, latitude, longitude
             FROM facility_details'
        );
        foreach ($facilities as $f) {
            $id = (int) $f['facility_id'];
            unset($f['facility_id']);
            $this->facilitiesById[$id] = $f;
            $this->idByName[mb_strtolower(trim((string) $f['facility_name']))] = $id;
            if (!empty($f['facility_code'])) {
                $this->idByCode[strtoupper((string) $f['facility_code'])] = $id;
            }
            $looseName = $this->looseName((string) $f['facility_name']);
            if ($looseName !== '') {
                $this->idByLooseName[$looseName] ??= $id;
            }
            $spot = $this->coordinateKey($f['latitude'], $f['longitude']);
            if ($spot !== null) {
                // Spots shared by several facilities (district centres, copied
                // coordinates) say nothing about duplicates; null them out.
                $this->idByCoordinates[$spot] = array_key_exists($spot, $this->idByCoordinates) ? null : $id;
            }
            if (!empty($f['facility_district_id'])) {
                $this->idsByDistrict[(int) $f['facility_district_id']][] = $id;
            }
            if (!empty($f['other_id'])) {
                $this->idByOtherId[mb_strtolower(trim((string) $f['other_id']))] = $id;
            }
        }

        foreach ($this->db->rawQuery('SELECT geo_id, geo_name, geo_parent FROM geographical_divisions') as $g) {
            $key = mb_strtolower(trim((string) $g['geo_name']));
            if ((int) $g['geo_parent'] === 0) {
                $this->provinceIdByName[$key] ??= (int) $g['geo_id'];
            } else {
                $this->districtIdByKey[(int) $g['geo_parent'] . '|' . $key] ??= (int) $g['geo_id'];
            }
        }

        foreach ($this->db->rawQuery('SELECT facility_type_id, facility_type_name FROM facility_type') as $t) {
            $this->facilityTypes[(string) $t['facility_type_id']] = (string) $t['facility_type_name'];
        }
    }

    private function batchDirectory(): string
    {
        $dir = VAR_TEMP_PATH . DIRECTORY_SEPARATOR . 'facility-import';
        MiscUtility::makeDirectory($dir);
        return $dir;
    }

    private function batchPath(string $batchId): ?string
    {
        if (!preg_match('/^[A-Za-z0-9]{24}$/', $batchId)) {
            return null;
        }
        return $this->batchDirectory() . DIRECTORY_SEPARATOR . $batchId . '.json';
    }

    private function purgeExpiredBatches(): void
    {
        foreach (glob($this->batchDirectory() . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file) && !str_contains($file, 'Facilities_Bulk_Upload_Excel_Format') && filemtime($file) < time() - self::BATCH_TTL_SECONDS) {
                @unlink($file);
            }
        }
    }
}
