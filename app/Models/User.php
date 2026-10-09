<?php

namespace App\Models;

use App\Support\CloudData;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends GenericUser implements CanResetPasswordContract
{
    use CanResetPassword, Notifiable;
    private string $rememberCookieToken = '';
    // The stored token is hashed; issue a fresh raw cookie token for each login.
    public function getRememberToken() { return $this->rememberCookieToken; }
    public function setRememberToken($value) { $this->rememberCookieToken = (string) $value; }
    public static function find($id): ?self
    {
        $row = CloudData::store()->get('admins', (string) $id);
        return $row ? new self($row + ['remember_token' => '', 'updated_at' => $row['created_at'] ?? null]) : null;
    }
    public static function all(): \Illuminate\Support\Collection
    {
        return collect(CloudData::collection('admins'))->map(fn ($row) => new self($row + ['updated_at' => $row['created_at'] ?? null]));
    }
    public function setPassword(string $password): void
    {
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
