<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CloudData;
use App\Support\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        $id = hash('sha256', $data['email']);
        $password = Hash::make($data['password']);

        CloudData::transaction(function () use ($request, $data, $id, $password) {
            $this->authorizeOwner($request);
            $created = CloudData::store()->create('admins', $id, [
                'name' => $data['name'], 'email' => $data['email'], 'password' => $password,
                'role' => $data['role'], 'active' => (bool) $data['active'], 'remember_token' => '',
                'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(),
            ]);
            if (!$created) {
                throw ValidationException::withMessages(['email' => 'Email ini sudah digunakan oleh akun lain.']);
            }
            School::log('Menambahkan akun '.User::ROLES[$data['role']].': '.$data['name'], 'users', 'Tersimpan');
        });

        return redirect('/admin/pengguna')->with('success', 'Akun '.User::ROLES[$data['role']].' berhasil ditambahkan.');
    }

    public function update(Request $request, string $account)
    {
        $data = $request->validate($this->rules(), $this->messages());
        CloudData::transaction(function () use ($request, $account, $data) {
            $this->authorizeOwner($request);
            $row = CloudData::store()->get('admins', $account);
            abort_unless($row, 404);
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
                'name' => $data['name'], 'role' => $data['role'], 'active' => (bool) $data['active'],
                'updated_at' => now()->toIso8601String(),
            ]);
            CloudData::store()->put('admins', $account, $row);
            School::log('Mengubah akun '.$data['name'].' menjadi '.User::ROLES[$data['role']], 'users', $row['active'] ? 'Aktif' : 'Nonaktif');
        });

        if ($account === (string) $request->user()->id && !(bool) $data['active']) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->with('success', 'Akun Anda telah dinonaktifkan.');
        }

        return redirect('/admin/pengguna')->with('success', 'Perubahan akun berhasil disimpan.');
    }

    private function authorizeOwner(Request $request): void
    {
        // Recheck inside the transaction so a revoked owner cannot commit stale access.
        $owner = User::find($request->user()->id);
        abort_unless($owner?->isOwner() && $owner?->active === true, 403, 'Hanya Owner yang dapat mengelola akun pengguna.');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'active' => ['required', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.', 'name.max' => 'Nama maksimal 150 karakter.',
            'email.required' => 'Email wajib diisi.', 'email.email' => 'Masukkan alamat email yang valid.',
            'role.required' => 'Pilih role akun.', 'role.in' => 'Role yang tersedia adalah Owner dan Admin.',
            'active.required' => 'Pilih status akun.', 'active.boolean' => 'Status akun tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.', 'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.max' => 'Kata sandi maksimal 72 karakter.', 'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ];
    }
}
