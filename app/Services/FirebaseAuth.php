<?php

namespace App\Services;

use App\Exceptions\FirebaseAuthException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class FirebaseAuth
{
    private const BASE = 'https://identitytoolkit.googleapis.com/';

    public function enabled(): bool { return (bool) config('school.firebase_auth_enabled'); }

    public function configuration(): array
    {
        return $this->request('GET', 'admin/v2/projects/'.$this->project().'/config', [], true);
    }

    public function lookup(array $criteria): array
    {
        return $this->request('POST', 'v1/projects/'.$this->project().'/accounts:lookup', $criteria, true)['users'] ?? [];
    }

    public function user(string $uid): ?array
    {
        return $this->lookup(['localId' => [$uid]])[0] ?? null;
    }

    public function signIn(string $email, #[\SensitiveParameter] string $password): ?array
    {
        try {
            $result = $this->request('POST', 'v1/accounts:signInWithPassword', [
                'email' => $email, 'password' => $password, 'returnSecureToken' => true,
            ]);
        } catch (FirebaseAuthException $e) {
            if (in_array($e->reason, ['INVALID_LOGIN_CREDENTIALS', 'INVALID_PASSWORD', 'EMAIL_NOT_FOUND', 'USER_DISABLED'], true)) return null;
            throw $e;
        }
        // Tokens stay on the server and are discarded; Laravel issues its own session.
        return isset($result['localId'], $result['email'])
            ? ['uid' => $result['localId'], 'email' => $result['email']] : null;
    }

    public function sendReset(string $email): void
    {
        $this->request('POST', 'v1/accounts:sendOobCode', ['requestType' => 'PASSWORD_RESET', 'email' => $email]);
    }

    public function sendVerification(string $uid, string $email, #[\SensitiveParameter] string $password): bool
    {
        try {
            $result = $this->request('POST', 'v1/accounts:signInWithPassword', [
                'email' => $email, 'password' => $password, 'returnSecureToken' => true,
            ]);
        } catch (FirebaseAuthException $e) {
            if (in_array($e->reason, ['INVALID_LOGIN_CREDENTIALS', 'INVALID_PASSWORD', 'EMAIL_NOT_FOUND', 'USER_DISABLED'], true)) return false;
            throw $e;
        }
        if (($result['localId'] ?? '') !== $uid || mb_strtolower($result['email'] ?? '') !== mb_strtolower($email)
            || empty($result['idToken'])) return false;
        // Use the token only for this request; never store it in the Laravel session.
        $this->request('POST', 'v1/accounts:sendOobCode', ['requestType' => 'VERIFY_EMAIL', 'idToken' => $result['idToken']]);
        return true;
    }

    public function create(string $uid, string $email, string $name, #[\SensitiveParameter] string $password, bool $active): void
    {
        $this->request('POST', 'v1/accounts:signUp', [
            'targetProjectId' => $this->project(), 'localId' => $uid, 'email' => $email,
            'displayName' => $name, 'password' => $password, 'disabled' => ! $active,
        ], true);
    }

    public function update(string $uid, array $attributes): void
    {
        $this->request('POST', 'v1/projects/'.$this->project().'/accounts:update', ['localId' => $uid] + $attributes, true);
    }

    public function delete(string $uid): void
    {
        $this->request('POST', 'v1/projects/'.$this->project().'/accounts:delete', ['localId' => $uid], true);
    }

    public function import(string $uid, array $row): void
    {
        if (password_get_info($row['password'] ?? '')['algoName'] !== 'bcrypt') {
            throw new FirebaseAuthException('UNSUPPORTED_PASSWORD_HASH');
        }
        $result = $this->request('POST', 'v1/projects/'.$this->project().'/accounts:batchCreate', [
            'hashAlgorithm' => 'BCRYPT', 'sanityCheck' => true, 'allowOverwrite' => false,
            'users' => [[
                'localId' => $uid, 'email' => $row['email'], 'displayName' => $row['name'],
                'passwordHash' => base64_encode($row['password']), 'disabled' => ! $row['active'],
                'customAttributes' => json_encode(['sdcn_migration_id' => $row['id']], JSON_THROW_ON_ERROR),
            ]],
        ], true);
        if (! empty($result['error'])) throw new FirebaseAuthException('IMPORT_FAILED');
    }

    public function version(array $account): string
    {
        return hash_hmac('sha256', implode('|', [
            $account['localId'], $account['validSince'] ?? '', $account['passwordUpdatedAt'] ?? '',
        ]), (string) config('app.key'));
    }

    private function project(): string { return rawurlencode((string) config('school.firebase_project')); }

    private function request(string $method, string $path, #[\SensitiveParameter] array $data, bool $admin = false): array
    {
        try {
            $credentials = app(FirebaseCredentials::class);
            $http = Http::withOptions($credentials->httpOptions())->acceptJson()->withHeaders(['X-Firebase-Locale' => 'id']);
            if ($admin) {
                $http = $http->withToken($credentials->accessToken('https://www.googleapis.com/auth/cloud-platform'));
            } else {
                $key = config('school.firebase_api_key');
                if (! is_string($key) || $key === '') throw new FirebaseAuthException('MISSING_API_KEY');
                $http = $http->withQueryParameters(['key' => $key]);
            }
            $response = $http->send($method, self::BASE.$path, $method === 'GET' ? [] : ['json' => $data]);
        } catch (FirebaseAuthException $e) {
            throw $e;
        } catch (ConnectionException|\RuntimeException $e) {
            throw new FirebaseAuthException;
        }
        if (! $response->successful()) {
            $reason = explode(' : ', (string) $response->json('error.message', 'UNAVAILABLE'))[0];
            // Only a short machine-readable code may leave this boundary.
            throw new FirebaseAuthException(preg_match('/^[A-Z_]{3,80}$/', $reason) ? $reason : 'UNAVAILABLE');
        }
        $result = $response->json();
        if (! is_array($result)) throw new FirebaseAuthException('INVALID_RESPONSE');
        return $result;
    }
}
