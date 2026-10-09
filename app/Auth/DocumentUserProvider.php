<?php

namespace App\Auth;

use App\Models\User;
use App\Support\CloudData;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;

class DocumentUserProvider implements UserProvider
{
    public function retrieveById($identifier)
    {
        $user = User::find($identifier);
        return $user && $user->active === true ? $user : null;
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
        return isset($credentials['password']) && Hash::check($credentials['password'], $user->getAuthPassword());
    }
    public function retrieveByToken($identifier, $token)
    {
        $user = $this->retrieveById($identifier);
        return $user && $user->remember_token && hash_equals($user->remember_token, hash('sha256', $token)) ? $user : null;
    }
    public function updateRememberToken(Authenticatable $user, $token)
    {
        CloudData::store()->transaction(function () use ($user, $token) {
            $row = CloudData::store()->get('admins', (string) $user->getAuthIdentifier());
            if (!$row) return;
            $row['remember_token'] = hash('sha256', $token);
            CloudData::store()->put('admins', (string) $user->getAuthIdentifier(), $row);
        });
    }
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false)
    {
        if ($force || Hash::needsRehash($user->getAuthPassword())) $user->setPassword($credentials['password']);
    }
}
