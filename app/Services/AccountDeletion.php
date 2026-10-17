<?php

namespace App\Services;

use App\Exceptions\FirebaseAuthException;
use App\Models\User;
use App\Support\CloudData;
use App\Support\School;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AccountDeletion
{
    public function preview(string $actorId, array $ids): array
    {
        $actor = User::find($actorId);
        abort_unless($actor?->active === true && $actor->hasFullSchoolAccess(), 403);
        $accounts = [];
        foreach ($ids as $id) {
            $account = User::find($id);
            abort_unless($account, 404);
            if ($actorId === $id) $this->invalid('Akun yang sedang Anda gunakan tidak dapat dihapus.');
            if ($account->isOwner() && ! $actor->isOwner()) $this->invalid('Hanya Owner yang boleh menghapus akun Owner lain.');
            $accounts[] = $account;
        }
        if (collect($accounts)->contains(fn ($user) => $user->isOwner() && $user->active === true)
            && ! User::all()->contains(fn ($user) => $user->isOwner() && $user->active === true && ! in_array((string) $user->id, $ids, true))) {
            $this->invalid('Sisakan minimal satu Owner aktif. Owner aktif terakhir tidak dapat dihapus.');
        }
        return $accounts;
    }

    public function delete(string $actorId, array $ids): array
    {
        // Validate the entire selection before any side effect, then block access and
        // edits atomically. External API calls must never run in a retried transaction.
        $accounts = CloudData::transaction(function () use ($actorId, $ids) {
            $accounts = $this->preview($actorId, $ids);
            foreach ($accounts as $account) {
                $row = CloudData::store()->get('admins', $account->id);
                if (empty($row['deletion_pending'])) {
                    School::log('Memulai penghapusan akun '.$row['name'], 'users', 'Akses dicabut');
                }
                $row['active'] = false;
                $row['remember_token'] = '';
                $row['firebase_remember_version'] = '';
                $row['deletion_pending'] = true;
                $row['updated_at'] = now()->toIso8601String();
                CloudData::store()->put('admins', $account->id, $row);
            }
            return $accounts;
        });

        $deleted = 0;
        $failed = 0;
        foreach ($accounts as $account) {
            try {
                if ($account->firebase_uid) {
                    try { app(FirebaseAuth::class)->delete($account->firebase_uid); }
                    catch (FirebaseAuthException $e) {
                        // A previous request may have deleted Firebase before a timeout.
                        if ($e->reason !== 'USER_NOT_FOUND') throw $e;
                    }
                }
                if ($account->photo_path) app(FirestoreFileStorage::class)->delete($account->photo_path);
                CloudData::transaction(function () use ($account) {
                    $row = CloudData::store()->get('admins', $account->id);
                    if (! $row) return;
                    if (empty($row['deletion_pending']) || ($row['firebase_uid'] ?? null) !== $account->firebase_uid) {
                        throw new \RuntimeException('Identitas akun berubah saat penghapusan.');
                    }
                    CloudData::store()->delete('admins', $account->id);
                    School::log('Menghapus akun '.(User::ROLES[$row['role']] ?? $row['role']).': '.$row['name'], 'users', 'Dihapus permanen');
                });
                $deleted++;
            } catch (\Throwable $e) {
                // Keep the disabled pending record so a subsequent deletion can finish.
                Log::warning('Account deletion requires retry.', ['account_id' => $account->id, 'type' => $e::class]);
                $failed++;
            }
        }
        return compact('deleted', 'failed');
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['accounts' => $message]);
    }
}
