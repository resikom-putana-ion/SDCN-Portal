<?php

namespace App\Services;

use App\Exceptions\FirebaseAuthException;
use App\Support\CloudData;

class FirebaseAccountMigration
{
    public function __construct(private FirebaseAuth $auth) {}

    public function inspect(array $row): array
    {
        $uid = $row['firebase_uid'] ?? 'sdcn-'.$row['id'];
        $existing = $this->auth->lookup(['email' => [$row['email']], 'localId' => [$uid]]);
        foreach ($existing as $remote) {
            $marker = json_decode($remote['customAttributes'] ?? '{}', true)['sdcn_migration_id'] ?? null;
            if (($remote['localId'] ?? '') !== $uid || mb_strtolower($remote['email'] ?? '') !== mb_strtolower($row['email'])
                || (empty($row['firebase_uid']) && $marker !== $row['id'])) {
                throw new FirebaseAuthException('ACCOUNT_CONFLICT');
            }
        }
        if (! $existing && (! empty($row['firebase_uid']) || password_get_info($row['password'] ?? '')['algoName'] !== 'bcrypt')) {
            throw new FirebaseAuthException('MIGRATION_SOURCE_INVALID');
        }
        return ['uid' => $uid, 'exists' => count($existing) > 0];
    }

    public function migrate(array $row): void
    {
        $plan = $this->inspect($row);
        if (! $plan['exists']) $this->auth->import($plan['uid'], $row);
        // A successful import response is not sufficient; verify the remote identity.
        $remote = $this->auth->user($plan['uid']);
        if (! $remote || mb_strtolower($remote['email'] ?? '') !== mb_strtolower($row['email'])) {
            throw new FirebaseAuthException('MIGRATION_VERIFY_FAILED');
        }
        CloudData::transaction(function () use ($row, $plan) {
            $current = CloudData::store()->get('admins', $row['id']);
            if (! $current || ($current['password'] ?? '') !== ($row['password'] ?? '')
                || $current['email'] !== $row['email'] || $current['active'] !== $row['active']) {
                throw new FirebaseAuthException('ACCOUNT_CHANGED_DURING_MIGRATION');
            }
            $current['firebase_uid'] = $plan['uid'];
            $current['password'] = '';
            $current['remember_token'] = '';
            $current['firebase_remember_version'] = '';
            $current['updated_at'] = now()->toIso8601String();
            CloudData::store()->put('admins', $row['id'], $current);
        });
    }
}
