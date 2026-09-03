<?php

declare(strict_types=1);

namespace App\Services;

use App\Utilities\DateUtility;
use App\Utilities\ImageResizeUtility;
use App\Utilities\LoggerUtility;
use App\Utilities\MiscUtility;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use InvalidArgumentException;
use Throwable;

/**
 * A user profile travelling from a LIS to its STS.
 *
 * The LIS pushes when a user is added or edited (push()). The STS applies it
 * (receive()) under rules that keep a pushed profile from ever granting access:
 * login id, role and password are never written, an existing user keeps its
 * status, and a new user arrives inactive.
 *
 * The push goes to /api/v2/user/profile with the lab's own STS token, the same
 * credential results-sender uses. An STS that has not been updated answers 404
 * there, so the push falls back to the v1.1 endpoint until every STS has v2.
 * The legacy endpoint is retired once its log shows no callers.
 */
final class UserProfileSyncService
{
    public const V2_PATH = '/api/v2/user/profile';

    private const LEGACY_PATH = '/api/v1.1/user/save-user-profile.php';

    /** Fields a pushed profile may carry. Anything else is dropped on both ends. */
    private const PROFILE_FIELDS = ['userId', 'userName', 'email', 'phoneNo', 'interfaceUserName', 'signature'];

    public function __construct(
        private readonly DatabaseService $db,
        private readonly CommonService $commonService,
        private readonly UsersService $usersService,
    ) {
    }

    /**
     * Apply a pushed profile on the receiving instance.
     *
     * @param array<string, mixed> $profile
     * @return array{userId: string, action: string}
     * @throws InvalidArgumentException when the profile cannot be applied
     */
    public function receive(array $profile): array
    {
        $profile = array_intersect_key($profile, array_flip(self::PROFILE_FIELDS));

        $userId = isset($profile['userId']) ? trim((string) $profile['userId']) : '';
        if ($userId !== '' && MiscUtility::isBase64($userId)) {
            $userId = (string) base64_decode($userId, true);
        }
        $email = isset($profile['email']) ? trim((string) $profile['email']) : '';
        $userName = isset($profile['userName']) ? trim((string) $profile['userName']) : '';

        if ($userName === '') {
            throw new InvalidArgumentException('userName is required');
        }
        if ($userId === '' && $email === '') {
            throw new InvalidArgumentException('userId or email is required');
        }

        $existing = null;
        if ($userId !== '') {
            $this->db->where('user_id', $userId);
            $existing = $this->db->getOne('user_details', ['user_id']);
        }
        if (empty($existing) && $email !== '') {
            $this->db->where('email', $email);
            $existing = $this->db->getOne('user_details', ['user_id']);
        }

        $interfaceUserName = trim((string) ($profile['interfaceUserName'] ?? ''));
        $phone = trim((string) ($profile['phoneNo'] ?? ''));
        $data = [
            'user_name' => $userName,
            'email' => $email !== '' ? $email : null,
            'phone_number' => $phone !== '' ? $phone : null,
            'interface_user_name' => $interfaceUserName === ''
                ? null
                : json_encode(array_map('trim', explode(',', $interfaceUserName))),
            'updated_datetime' => DateUtility::getCurrentDateTime(),
        ];

        $targetId = !empty($existing['user_id'])
            ? (string) $existing['user_id']
            : ($userId !== '' ? $userId : MiscUtility::generateUUID());
        $signatureFile = $this->storeSignature($profile['signature'] ?? null, $targetId);
        if ($signatureFile !== null) {
            $data['user_signature'] = $signatureFile;
        }

        if (!empty($existing['user_id'])) {
            $this->db->where('user_id', $targetId);
            if ($this->db->update('user_details', $data) === false) {
                throw new InvalidArgumentException('The profile could not be saved');
            }
            return ['userId' => $targetId, 'action' => 'updated'];
        }

        $data['user_id'] = $targetId;
        $data['status'] = 'inactive';
        if ($this->db->insert('user_details', $data) === false) {
            throw new InvalidArgumentException('The profile could not be saved');
        }
        return ['userId' => $targetId, 'action' => 'created'];
    }

    /**
     * Push a profile from this LIS to its STS. Returns false, after logging, when
     * this instance has no STS, is not a LIS, or the STS rejected the push.
     *
     * @param array<string, mixed> $profile the submitted user form, userId as stored
     */
    public function push(array $profile, ?string $signaturePath = null): bool
    {
        $remoteUrl = rtrim((string) $this->commonService->getRemoteURL(), '/');
        if ($remoteUrl === '' || !$this->commonService->isLISInstance()) {
            return false;
        }

        // Never let a LIS form set credentials or roles on the STS.
        foreach (['loginId', 'password', 'hashAlgorithm', 'role', 'status'] as $unsetKey) {
            unset($profile[$unsetKey]);
        }
        $profile = array_intersect_key($profile, array_flip(self::PROFILE_FIELDS));

        if ($signaturePath !== null && $signaturePath !== '' && MiscUtility::isImageValid($signaturePath)) {
            $profile['signature'] = [
                'filename' => basename($signaturePath),
                'content' => base64_encode((string) file_get_contents($signaturePath)),
            ];
        }

        $labId = (int) ($this->commonService->getSystemConfig('sc_testing_lab_id') ?? 0);
        $token = (string) ($this->commonService->getSTSToken() ?? '');
        $client = new Client(['connect_timeout' => 10, 'timeout' => 60]);

        if ($labId > 0 && $token !== '') {
            try {
                $client->post($remoteUrl . self::V2_PATH, [
                    'headers' => ['Authorization' => "Bearer $token"],
                    'json' => ['labId' => $labId, 'profile' => $profile],
                ]);
                return true;
            } catch (ClientException $e) {
                if ($e->getResponse()->getStatusCode() !== 404) {
                    LoggerUtility::logError('User profile push rejected by the STS: ' . $e->getMessage());
                    return false;
                }
                // The STS predates v2; use the endpoint it has.
            } catch (Throwable $e) {
                LoggerUtility::logError('User profile push failed: ' . $e->getMessage());
                return false;
            }
        }

        return $this->pushLegacy($client, $remoteUrl, $profile, $signaturePath);
    }

    /** @param array<string, mixed> $profile */
    private function pushLegacy(Client $client, string $remoteUrl, array $profile, ?string $signaturePath): bool
    {
        if (isset($profile['userId'])) {
            $profile['userId'] = base64_encode((string) $profile['userId']);
        }
        if (isset($profile['signature']['content'], $profile['signature']['filename'])) {
            $profile['signature_image_content'] = $profile['signature']['content'];
            $profile['signature_image_filename'] = $profile['signature']['filename'];
            unset($profile['signature']);
        }
        $multipart = [
            ['name' => 'post', 'contents' => json_encode($profile)],
            ['name' => 'x-api-key', 'contents' => MiscUtility::generateRandomString(18)],
        ];
        if ($signaturePath !== null && $signaturePath !== '' && MiscUtility::isImageValid($signaturePath)) {
            $multipart[] = [
                'name' => 'sign',
                'contents' => fopen($signaturePath, 'r'),
                'filename' => basename($signaturePath),
                'headers' => ['Content-Type' => mime_content_type($signaturePath) ?: 'application/octet-stream'],
            ];
        }
        try {
            $client->post($remoteUrl . self::LEGACY_PATH, ['multipart' => $multipart]);
            return true;
        } catch (Throwable $e) {
            LoggerUtility::logError('User profile push (legacy endpoint) failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param mixed $signature ['filename' => string, 'content' => base64 string] or null
     */
    private function storeSignature(mixed $signature, string $userId): ?string
    {
        if (
            !is_array($signature) || empty($signature['content']) || empty($signature['filename'])
            || !defined('UPLOAD_PATH')
        ) {
            return null;
        }
        $bytes = base64_decode((string) $signature['content'], true);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        $extension = strtolower(pathinfo((string) $signature['filename'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'jpg', 'jpeg', 'gif'], true)) {
            return null;
        }

        $dir = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'users-signature';
        MiscUtility::makeDirectory($dir);
        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '', $userId);
        $path = realpath($dir) . DIRECTORY_SEPARATOR . 'usign-' . $safeId . '.' . $extension;
        file_put_contents($path, $bytes);
        if (!MiscUtility::isImageValid($path)) {
            @unlink($path);
            return null;
        }
        $resize = new ImageResizeUtility($path);
        $resize->resizeToWidth(250);
        $resize->save($path);

        return basename($path);
    }
}
