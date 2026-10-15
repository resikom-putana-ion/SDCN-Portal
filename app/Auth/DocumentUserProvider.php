<?php

namespace App\Auth;

use App\Models\User;
use App\Services\FirebaseAuth;
use App\Support\CloudData;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;

class DocumentUserProvider implements UserProvider
{
    public function retrieveById($identifier)
    {
        $user = User::find($identifier);
        if (! $user || $user->active !== true) return null;
        if ($user->usesFirebaseAuth()) {
            if (! $user->firebase_uid) return null;
            $account = app(FirebaseAuth::class)->user($user->firebase_uid);
            if (! $account || ($account['localId'] ?? '') !== $user->firebase_uid || ($account['disabled'] ?? false)
                || mb_strtolower($account['email'] ?? '') !== mb_strtolower($user->email)) return null;
            $user->password = app(FirebaseAuth::class)->version($account);
        }
        return $user;
    }
    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials['email'])) return null;
        $email = mb_strtolower(trim($credentials['email']));
        $user = $this->retrieveById(hash('sha256', $email));
        return $user && mb_strtolower($user->email) === $email ? $user : null;
    }
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if ($user->usesFirebaseAuth()) {
            if (! isset($credentials['password']) || ! $user->firebase_uid) return false;
            $result = app(FirebaseAuth::class)->signIn($user->email, $credentials['password']);
            if (! $result || $result['uid'] !== $user->firebase_uid || mb_strtolower($result['email']) !== mb_strtolower($user->email)) return false;
            // First sign-in after a bcrypt import may upgrade the hash on Firebase.
            $fresh = $this->retrieveById($user->id);
            if (! $fresh) return false;
            $user->password = $fresh->password;
            return true;
        }
        return isset($credentials['password']) && Hash::check($credentials['password'], $user->getAuthPassword());
    }
    public function retrieveByToken($identifier, $token)
    {
        $user = $this->retrieveById($identifier);
        if ($user?->usesFirebaseAuth() && ! hash_equals($user->password, (string) $user->firebase_remember_version)) return null;
        return $user && $user->remember_token && hash_equals($user->remember_token, hash('sha256', $token)) ? $user : null;
    }
    public function updateRememberToken(Authenticatable $user, $token)
    {
        CloudData::store()->transaction(function () use ($user, $token) {
            $row = CloudData::store()->get('admins', (string) $user->getAuthIdentifier());
            if (!$row) return;
            $row['remember_token'] = hash('sha256', $token);
            if ($user->usesFirebaseAuth()) $row['firebase_remember_version'] = $user->getAuthPassword();
            CloudData::store()->put('admins', (string) $user->getAuthIdentifier(), $row);
        });
    }
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false)
    {
        if ($user->usesFirebaseAuth()) return;
        if ($force || Hash::needsRehash($user->getAuthPassword())) $user->setPassword($credentials['password']);
    }
}
