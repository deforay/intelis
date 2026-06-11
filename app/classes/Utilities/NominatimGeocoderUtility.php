<?php

namespace App\Utilities;

/**
 * Lightweight, country-agnostic geocoding helper built on the public
 * OpenStreetMap Nominatim service.
 *
 * Used to approximate facility coordinates where none are stored. This is a
 * best-effort, "as approximate as possible" geocoder — it is NOT a substitute
 * for surveyed GPS coordinates.
 *
 * Nominatim usage policy (https://operations.osmfoundation.org/policies/nominatim/):
 *   - send a valid, identifying User-Agent
 *   - no more than 1 request/second
 * The caller is responsible for pacing bulk lookups (see bin/geocode-facilities.php).
 */
final class NominatimGeocoderUtility
{
    /** Golden angle in degrees — used for the phyllotaxis ("sunflower") spread. */
    public const GOLDEN_ANGLE = 137.50776405003785;

    private static string $endpoint = 'https://nominatim.openstreetmap.org/search';
    private static string $userAgent = 'IntelisLIS-FacilityGeocoder/1.0 (+https://intelis.org)';

    /** Override the Nominatim endpoint (e.g. to point at a self-hosted instance). */
    public static function setEndpoint(string $endpoint): void
    {
        self::$endpoint = rtrim($endpoint, '?&');
    }

    /**
     * Geocode a free-text place query.
     *
     * @param string      $query       e.g. "Nyamata DH, Bugesera, Eastern, Rwanda"
     * @param string|null $countryCode optional ISO 3166-1 alpha-2 (e.g. "rw") to constrain results
     *
     * @return array{lat: float, lon: float, display_name: string}|null
     */
    public static function geocode(string $query, ?string $countryCode = null): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $params = [
            'q' => $query,
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 0,
        ];
        if (!empty($countryCode)) {
            $params['countrycodes'] = strtolower($countryCode);
        }

        $raw = self::httpGet(self::$endpoint . '?' . http_build_query($params));
        if ($raw === null) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data[0]['lat'], $data[0]['lon'])) {
            return null;
        }

        $lat = (float) $data[0]['lat'];
        $lon = (float) $data[0]['lon'];
        if (!self::isValidCoord($lat, $lon)) {
            return null;
        }

        return [
            'lat' => $lat,
            'lon' => $lon,
            'display_name' => (string) ($data[0]['display_name'] ?? ''),
        ];
    }

    /**
     * Normalise a configured country name (or slug) into something Nominatim
     * recognises, plus its ISO 3166-1 alpha-2 code.
     *
     * Long official names poison free-text geocoding: "Republic of Cameroon"
     * matches a restaurant in Singapore and "<place>, Republic of Cameroon"
     * returns nothing, so every lookup fails. The short common name ("Cameroon")
     * resolves correctly, and the ISO code lets us pass `countrycodes` as a hard
     * filter. Known VLSM countries (form names and slugs) are mapped explicitly;
     * anything else falls back to stripping common state-form prefixes.
     *
     * @return array{iso: ?string, name: string}
     */
    public static function normalizeCountry(string $country): array
    {
        $key = strtolower(trim($country));
        if ($key === '') {
            return ['iso' => null, 'name' => ''];
        }

        // Keyed by both the official form_name and the slug used in
        // s_available_country_forms, plus a few obvious aliases.
        static $map = [
            'south sudan' => ['ss', 'South Sudan'],
            'ssudan' => ['ss', 'South Sudan'],
            'sierra leone' => ['sl', 'Sierra Leone'],
            'sierra-leone' => ['sl', 'Sierra Leone'],
            'democratic republic of the congo' => ['cd', 'Democratic Republic of the Congo'],
            'dr congo' => ['cd', 'Democratic Republic of the Congo'],
            'drc' => ['cd', 'Democratic Republic of the Congo'],
            'republic of cameroon' => ['cm', 'Cameroon'],
            'cameroon' => ['cm', 'Cameroon'],
            'cameroun' => ['cm', 'Cameroon'],
            'papua new guinea' => ['pg', 'Papua New Guinea'],
            'png' => ['pg', 'Papua New Guinea'],
            'rwanda' => ['rw', 'Rwanda'],
            'burkina faso' => ['bf', 'Burkina Faso'],
            'burkina-faso' => ['bf', 'Burkina Faso'],
        ];
        if (isset($map[$key])) {
            return ['iso' => $map[$key][0], 'name' => $map[$key][1]];
        }

        // Unknown country: best-effort strip of "Republic of", "Kingdom of",
        // etc. so a long official name still geocodes. No ISO code available.
        $clean = preg_replace(
            '/^(the\s+)?(democratic\s+|federal\s+|united\s+)?(people\'?s\s+)?(republic\s+of\s+(the\s+)?|kingdom\s+of\s+|state\s+of\s+)/i',
            '',
            trim($country)
        );
        $clean = trim((string) $clean);
        return ['iso' => null, 'name' => $clean !== '' ? $clean : trim($country)];
    }

    /** Reject coordinates that are out of range or the (0,0) "null island". */
    public static function isValidCoord(float $lat, float $lon): bool
    {
        if ($lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0) {
            return false;
        }
        return !(abs($lat) < 1e-9 && abs($lon) < 1e-9);
    }

    /**
     * Deterministic phyllotaxis ("sunflower") offset.
     *
     * When several facilities share the same fallback centroid (e.g. a district
     * centre), placing them all on the exact same point makes them un-clickable.
     * This fans them out into an evenly-filled disc around the centroid using the
     * golden angle, so the spread is uniform and reproducible across runs.
     *
     * @param int $index 0-based rank of this facility among those sharing the centroid
     * @param int $count total facilities sharing the centroid (controls disc radius)
     *
     * @return array{lat: float, lon: float}
     */
    public static function sunflowerOffset(float $lat, float $lon, int $index, int $count, float $maxRadiusDeg = 0.06): array
    {
        if ($count <= 1) {
            return ['lat' => $lat, 'lon' => $lon];
        }

        $r = $maxRadiusDeg * sqrt(($index + 0.5) / $count);
        $theta = deg2rad($index * self::GOLDEN_ANGLE);

        $dLat = $r * cos($theta);
        // Correct longitude for latitude so the disc stays visually circular.
        $cosLat = cos(deg2rad($lat));
        $dLon = ($cosLat > 1e-6) ? ($r * sin($theta)) / $cosLat : $r * sin($theta);

        return ['lat' => $lat + $dLat, 'lon' => $lon + $dLon];
    }

    private static function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_USERAGENT => self::$userAgent,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $res = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($res === false || $code >= 400) {
                return null;
            }
            return (string) $res;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: " . self::$userAgent . "\r\nAccept: application/json\r\n",
                'timeout' => 20,
            ],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        return $res === false ? null : $res;
    }
}
