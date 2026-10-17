<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Exceptions\FirebaseAuthException;
use App\Services\FirebaseAuth;
use App\Services\AccountDeletion;
use App\Support\CloudData;
use App\Support\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserManagementController extends Controller
{
    private function pageData(string $title): array
    {
        return ['title' => $title, 'group' => 'settings', 'screen' => 'pengguna', 'roles' => User::ROLES];
    }

    public function index()
    {
        return view('admin.users.index', $this->pageData('Pengguna & Izin') + [
            'users' => User::all()->sortBy(fn ($user) => mb_strtolower($user->name))->values(),
        ]);
    }

    public function create()
    {
        return view('admin.users.form', $this->pageData('Tambah Akun') + ['account' => null]);
    }

    public function edit(string $account)
    {
        $user = User::find($account);
        abort_unless($user, 404);

        return view('admin.users.form', $this->pageData('Kelola Akun') + ['account' => $user]);
    }

    public function store(Request $request)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate($this->rules() + [
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ], $this->messages());
        // The account holder fills in their own identity after verifying their email.
        $data['name'] = mb_substr(explode('@', $data['email'])[0], 0, 150);
        $id = hash('sha256', $data['email']);
        $this->authorizeManager($request);
        if (User::find($id)) {
            throw ValidationException::withMessages(['email' => 'Email ini sudah digunakan oleh akun lain.']);
        }
        $firebase = app(FirebaseAuth::class);
        $uid = $firebase->enabled() ? 'sdcn-'.Str::uuid() : null;
        $password = $uid ? '' : Hash::make($data['password']);
        if ($uid) {
            try {
                $firebase->create($uid, $data['email'], $data['name'], $data['password'], (bool) $data['active']);
            } catch (FirebaseAuthException $e) {
                throw ValidationException::withMessages(['email' => $e->reason === 'EMAIL_EXISTS'
                    ? 'Email ini sudah terdaftar di layanan akun. Gunakan email lain atau hubungi pengelola.'
                    : 'Akun belum dapat dibuat di layanan autentikasi. Silakan coba lagi.']);
            }
        }

        try {
            CloudData::transaction(function () use ($request, $data, $id, $password, $uid) {
                $this->authorizeManager($request);
                $created = CloudData::store()->create('admins', $id, [
                    'name' => $data['name'], 'email' => $data['email'], 'password' => $password,
                    'role' => $data['role'], 'active' => (bool) $data['active'], 'remember_token' => '',
                    'firebase_uid' => $uid,
                    'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(),
                ]);
                if (!$created) {
                    throw ValidationException::withMessages(['email' => 'Email ini sudah digunakan oleh akun lain.']);
                }
                School::log('Menambahkan akun '.User::ROLES[$data['role']].': '.$data['name'], 'users', 'Tersimpan');
            });
        } catch (\Throwable $e) {
            // Firebase calls stay outside retried Firestore transactions. Compensate only
            // the account created by this request, never an existing email/UID.
            if ($uid) {
                try {
                    // A timed-out Firestore commit may already have succeeded.
                    $saved = User::find($id);
                    if ($saved?->firebase_uid !== $uid) $firebase->delete($uid);
                }
                catch (\Throwable) { Log::warning('Firebase account creation needs reconciliation.', ['uid' => $uid]); }
            }
            throw $e;
        }

        return redirect('/admin/pengguna')->with('success', 'Akun '.User::ROLES[$data['role']].' berhasil ditambahkan.');
    }

    public function update(Request $request, string $account)
    {
        $data = $request->validate($this->rules(), $this->messages());
        CloudData::transaction(function () use ($request, $account, $data) {
            $this->authorizeManager($request);
            $row = CloudData::store()->get('admins', $account);
            abort_unless($row, 404);
            if (! empty($row['deletion_pending'])) {
                throw ValidationException::withMessages(['active' => 'Akun sedang dihapus. Selesaikan penghapusan melalui daftar pengguna.']);
            }
            if (($row['role'] ?? '') === 'owner' && ($row['active'] ?? false) === true
                && ($data['role'] !== 'owner' || !(bool) $data['active'])) {
                $otherOwner = User::all()->contains(fn ($user) => (string) $user->id !== $account && $user->isOwner() && $user->active === true);
                if (!$otherOwner) {
                    throw ValidationException::withMessages(['role' => 'Sisakan minimal satu Owner aktif. Tambahkan atau aktifkan Owner lain terlebih dahulu.']);
                }
            }
            if ($row['role'] !== $data['role'] || $row['active'] !== (bool) $data['active']) {
                $row['remember_token'] = '';
            }
            $row = array_replace($row, [
                'role' => $data['role'], 'active' => (bool) $data['active'],
                'updated_at' => now()->toIso8601String(),
            ]);
            CloudData::store()->put('admins', $account, $row);
            School::log('Mengubah akun '.$row['name'].' menjadi '.User::ROLES[$data['role']], 'users', $row['active'] ? 'Aktif' : 'Nonaktif');
        });

        $updated = User::find($account);
        if ($updated->firebase_uid) {
            try {
                app(FirebaseAuth::class)->update($updated->firebase_uid, [
                    'disableUser' => ! $updated->active,
                ]);
            } catch (FirebaseAuthException) {
                // The local active flag is authoritative and already denies dashboard access.
                return back()->withErrors(['active' => 'Perubahan sekolah tersimpan, tetapi sinkronisasi layanan akun gagal. Coba simpan kembali.']);
            }
        }

        if ($account === (string) $request->user()->id && !(bool) $data['active']) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->with('success', 'Akun Anda telah dinonaktifkan.');
        }

        if ($account === (string) $request->user()->id && ! $updated->hasFullSchoolAccess()) {
            return redirect('/admin')->with('success', 'Role Anda telah diperbarui. Menu menyesuaikan akses baru.');
        }

        return redirect('/admin/pengguna')->with('success', 'Perubahan akun berhasil disimpan.');
    }

    private function authorizeManager(Request $request): void
    {
        // Recheck inside the transaction so revoked managers cannot commit stale access.
        $manager = User::find($request->user()->id);
        abort_unless($manager?->hasFullSchoolAccess() && $manager?->active === true, 403, 'Hanya Owner dan Admin yang dapat mengelola akun pengguna.');
    }

    public function confirmDelete(Request $request, AccountDeletion $deletion, ?string $account = null)
    {
        $ids = $account !== null ? [$account] : $this->deletionIds($request);
        return view('admin.users.delete', $this->pageData('Hapus Akun Pengelola') + [
            'accounts' => $deletion->preview((string) $request->user()->id, $ids),
        ]);
    }

    public function destroy(Request $request, AccountDeletion $deletion)
    {
        $ids = $this->deletionIds($request);
        $request->validate(['confirmation' => ['required', 'in:HAPUS']], [
            'confirmation.required' => 'Ketik HAPUS untuk mengonfirmasi penghapusan akun.',
            'confirmation.in' => 'Konfirmasi harus berupa kata HAPUS.',
        ]);
        $result = $deletion->delete((string) $request->user()->id, $ids);
        if ($result['failed']) {
            return redirect('/admin/pengguna')->withErrors(['accounts' => $result['deleted'].' akun berhasil dihapus. '.$result['failed'].' akun belum selesai dihapus dan aksesnya sudah dinonaktifkan. Pilih "Lanjutkan hapus" untuk mencoba lagi.']);
        }
        return redirect('/admin/pengguna')->with('success', $result['deleted'].' akun pengelola berhasil dihapus permanen.');
    }

    private function deletionIds(Request $request): array
    {
        return $request->validate([
            'accounts' => ['required', 'array', 'min:1', 'max:20'],
            'accounts.*' => ['required', 'string', 'max:128', 'distinct'],
        ], ['accounts.required' => 'Pilih akun yang ingin dihapus.', 'accounts.max' => 'Pilih maksimal 20 akun dalam satu penghapusan.'])['accounts'];
    }

    private function rules(): array
    {
        return [
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'active' => ['required', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.', 'name.max' => 'Nama maksimal 150 karakter.',
            'email.required' => 'Email wajib diisi.', 'email.email' => 'Masukkan alamat email yang valid.',
            'role.required' => 'Pilih role akun.', 'role.in' => 'Role yang tersedia adalah '.implode(', ', array_values(User::ROLES)).'.',
            'active.required' => 'Pilih status akun.', 'active.boolean' => 'Status akun tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.', 'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.max' => 'Kata sandi maksimal 72 karakter.', 'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ];
    }
}
