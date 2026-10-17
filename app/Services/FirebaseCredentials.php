<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use GuzzleHttp\Client;
use RuntimeException;

class FirebaseCredentials
{
    // Process memory only: a Firestore cache must never recursively authenticate itself.
    private static array $tokens = [];

    public function data(): array
    {
        $encoded = config('school.firebase_credentials_json_base64');
        if (is_string($encoded) && $encoded !== '') {
            $json = base64_decode($encoded, true);
            if ($json === false) throw new RuntimeException('FIREBASE_CREDENTIALS_JSON_BASE64 tidak valid.');
        } else {
            $path = config('school.firebase_credentials');
            if (!is_string($path) || !is_file($path) || !is_readable($path)) {
                throw new RuntimeException('Kredensial server Firebase belum tersedia.');
            }
            $json = file_get_contents($path);
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Format JSON kredensial Firebase tidak valid.');
        }
        if (!is_array($data) || ($data['type'] ?? '') !== 'service_account'
            || !is_string($data['project_id'] ?? null) || $data['project_id'] !== config('school.firebase_project')
            || !is_string($data['client_email'] ?? null) || !filter_var($data['client_email'], FILTER_VALIDATE_EMAIL)
            || !is_string($data['private_key'] ?? null) || !str_contains($data['private_key'], '-----BEGIN PRIVATE KEY-----')) {
            throw new RuntimeException('Project ID, email, atau private key kredensial Firebase tidak sesuai.');
        }
        // Keep Google credential discovery confined to the configured service account.
        $data['token_uri'] = 'https://oauth2.googleapis.com/token';
        return $data;
    }

    public function httpOptions(): array
    {
        $bundle = config('school.firebase_ca_bundle');
        if ($bundle !== null && $bundle !== '' && (!is_string($bundle) || !is_file($bundle) || !is_readable($bundle))) {
            throw new RuntimeException('FIREBASE_CA_BUNDLE harus berupa path berkas sertifikat CA yang dapat dibaca.');
        }
        return ['verify'=>$bundle ?: true, 'timeout'=>20, 'connect_timeout'=>10];
    }

    public function accessToken(string $scope): string
    {
        $credentials = $this->data();
        $options = $this->httpOptions();
        $key = hash('sha256', json_encode([$credentials, $scope, $options], JSON_THROW_ON_ERROR));
        if ((self::$tokens[$key]['expires_at'] ?? 0) > time()) return self::$tokens[$key]['token'];
        $token = $this->fetchToken($credentials, $scope, $options);
        if (!is_string($token['access_token'] ?? null) || $token['access_token'] === '') {
            throw new RuntimeException('Autentikasi Firebase gagal.');
        }
        self::$tokens[$key] = ['token'=>$token['access_token'],
            'expires_at'=>time() + max(1, min(3600, (int) ($token['expires_in'] ?? 3600)) - 60)];
        return self::$tokens[$key]['token'];
    }

    protected function fetchToken(array $credentials, string $scope, array $options): array
    {
        $auth = new ServiceAccountCredentials($scope, $credentials);
        return $auth->fetchAuthToken(HttpHandlerFactory::build(new Client($options), false));
    }
}
