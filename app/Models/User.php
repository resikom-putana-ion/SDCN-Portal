<?php

namespace App\Models;

use App\Notifications\SchoolPasswordReset;
use App\Support\CloudData;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends GenericUser implements CanResetPasswordContract
{
    use CanResetPassword, Notifiable;

    public function usesFirebaseAuth(): bool
    {
        return (bool) config('school.firebase_auth_enabled') || ! empty($this->attributes['firebase_uid']);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new SchoolPasswordReset($token));
    }

    public const ROLES = [
        'owner' => 'Owner',
        'admin' => 'Admin',
        'keuangan' => 'Keuangan',
        'akademik' => 'Akademik',
    ];

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function hasFullSchoolAccess(): bool
    {
        return in_array($this->role, ['owner', 'admin'], true);
    }

    public function canDeleteAccount(self $account): bool
    {
        return $this->active === true && $this->hasFullSchoolAccess()
            && (string) $this->id !== (string) $account->id
            && ($this->isOwner() || ! $account->isOwner());
    }

    private string $rememberCookieToken = '';
    // The stored token is hashed; issue a fresh raw cookie token for each login.
    public function getRememberToken() { return $this->rememberCookieToken; }
    public function setRememberToken($value) { $this->rememberCookieToken = (string) $value; }
    public static function find($id): ?self
    {
        $row = CloudData::store()->get('admins', (string) $id);
        return $row ? new self($row + ['password' => '', 'firebase_uid' => null, 'firebase_remember_version' => '', 'remember_token' => '', 'deletion_pending' => false, 'photo_path' => null, 'updated_at' => $row['created_at'] ?? null]) : null;
    }
    public static function all(): \Illuminate\Support\Collection
    {
        return collect(CloudData::collection('admins'))->map(fn ($row) => new self($row + ['deletion_pending' => false, 'updated_at' => $row['created_at'] ?? null]));
    }
    public function setPassword(string $password): void
    {
        if ($this->usesFirebaseAuth()) {
            throw new \LogicException('Gunakan pemulihan kata sandi Firebase untuk akun ini.');
        }
        CloudData::store()->transaction(function () use ($password) {
            $row = CloudData::store()->get('admins', (string) $this->id);
            abort_unless($row, 404);
            $row['password'] = Hash::make($password);
            $row['remember_token'] = '';
            $row['updated_at'] = now()->toIso8601String();
            CloudData::store()->put('admins', (string) $this->id, $row);
            $this->password = $row['password'];
        });
    }
}
