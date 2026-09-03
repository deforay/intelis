<?php

declare(strict_types=1);

namespace App\Services;

use App\Utilities\DateUtility;
use App\Utilities\ImageResizeUtility;
use App\Utilities\LoggerUtility;
use App\Utilities\MiscUtility;
use DomainException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use InvalidArgumentException;
use RuntimeException;
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
     * A lab caller ($labId) may create users for its own lab and update users
     * that belong to it or to no lab yet (rows pushed before labs were stamped);
     * the write stamps the lab. A user caller ($selfUserId) may only update
     * its own profile, whatever userId or email the body names.
     *
     * Only the fields present in the profile are written, so an update that
     * omits the phone number keeps the phone number.
     *
     * @param array<string, mixed> $profile
     * @return array{userId: string, action: string}
     * @throws InvalidArgumentException when the profile cannot be applied (400)
     * @throws DomainException when the profile names a user outside the caller's scope (403)
     */
    public function receive(array $profile, ?int $labId = null, ?string $selfUserId = null): array
    {
        $profile = array_intersect_key($profile, array_flip(self::PROFILE_FIELDS));

        $userId = trim((string) ($profile['userId'] ?? ''));
        $email = trim((string) ($profile['email'] ?? ''));
        $userName = trim((string) ($profile['userName'] ?? ''));

        if ($userName === '') {
            throw new InvalidArgumentException(_translate('userName is required'));
        }

        if ($selfUserId !== null) {
            // A user token edits itself and nothing else.
            $userId = $selfUserId;
        } elseif ($userId === '' && $email === '') {
            throw new InvalidArgumentException(_translate('userId or email is required'));
        }

        $existing = null;
        if ($userId !== '') {
            $this->db->where('user_id', $userId);
            $existing = $this->db->getOne('user_details', ['user_id', 'testing_lab_id']);
        }
        if (empty($existing) && $selfUserId === null && $email !== '') {
            $this->db->where('email', $email);
            $existing = $this->db->getOne('user_details', ['user_id', 'testing_lab_id']);
        }
        if ($selfUserId !== null && empty($existing)) {
            throw new InvalidArgumentException(_translate('The user for this token no longer exists'));
        }

        if (!empty($existing) && $labId !== null) {
            $ownerLab = $existing['testing_lab_id'] === null ? null : (int) $existing['testing_lab_id'];
            if ($ownerLab !== null && $ownerLab !== $labId) {
                throw new DomainException(_translate('This user belongs to another lab'));
            }
        }

        $data = [
            'user_name' => $userName,
            'updated_datetime' => DateUtility::getCurrentDateTime(),
        ];
        if (array_key_exists('email', $profile)) {
            $data['email'] = $email !== '' ? $email : null;
        }
        if (array_key_exists('phoneNo', $profile)) {
            $phone = trim((string) $profile['phoneNo']);
            $data['phone_number'] = $phone !== '' ? $phone : null;
        }
        if (array_key_exists('interfaceUserName', $profile)) {
            $interfaceUserName = trim((string) $profile['interfaceUserName']);
            $data['interface_user_name'] = $interfaceUserName === ''
                ? null
                : json_encode(array_map('trim', explode(',', $interfaceUserName)));
        }
        if ($labId !== null) {
            $data['testing_lab_id'] = $labId;
        }

        if (!empty($existing)) {
            $targetId = (string) $existing['user_id'];
            $this->db->where('user_id', $targetId);
            if ($labId !== null) {
                // The ownership rule is part of the write itself, so two labs
                // claiming the same unstamped user at once cannot both win: the
                // second update matches no row, and the re-read below says so.
                $this->db->where('(testing_lab_id IS NULL OR testing_lab_id = ?)', [$labId]);
            }
            if ($this->db->update('user_details', $data) === false) {
                throw new InvalidArgumentException(_translate('The profile could not be saved'));
            }
            if ($labId !== null) {
                $this->db->where('user_id', $targetId);
                $owner = $this->db->getValue('user_details', 'testing_lab_id');
                if ($owner === null || (int) $owner !== $labId) {
                    throw new DomainException(_translate('This user belongs to another lab'));
                }
            }
            $action = 'updated';
        } else {
            $targetId = $userId !== '' ? $userId : MiscUtility::generateUUID();
            $data['user_id'] = $targetId;
            $data['status'] = 'inactive';
            if ($this->db->insert('user_details', $data) === false) {
                throw new InvalidArgumentException(_translate('The profile could not be saved'));
            }
            $action = 'created';
        }

        // The signature goes on disk only after the row is safely written, so a
        // failed save never replaces the signature the user already had.
        $signatureFile = $this->storeSignature($profile['signature'] ?? null, $targetId);
        if ($signatureFile !== null) {
            $this->db->where('user_id', $targetId);
            if ($this->db->update('user_details', ['user_signature' => $signatureFile]) === false) {
                throw new RuntimeException('The signature was stored but could not be recorded for ' . $targetId);
            }
        }

        return ['userId' => $targetId, 'action' => $action];
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
        $staged = $path . '.incoming';
        file_put_contents($staged, $bytes);
        if (!MiscUtility::isImageValid($staged)) {
            @unlink($staged);
            return null;
        }
        $resize = new ImageResizeUtility($staged);
        $resize->resizeToWidth(250);
        $resize->save($staged);
        if (!rename($staged, $path)) {
            @unlink($staged);
            return null;
        }

        return basename($path);
    }
}
