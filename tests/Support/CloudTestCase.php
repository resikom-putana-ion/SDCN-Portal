<?php

namespace Tests\Support;

use App\Models\SchoolRecord;
use App\Models\User;
use App\Services\FirestoreDocumentStore;
use App\Support\CloudData;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

abstract class CloudTestCase extends TestCase
{
    protected MemoryFirestore $store;
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['database.default' => 'disabled', 'auth.passwords.users.store' => 'array']);
        $this->store = new MemoryFirestore;
        $this->app->instance(FirestoreDocumentStore::class, $this->store);
        $this->store->put('admins', hash('sha256', 'admin@sdcerianusantara.sch.id'), [
            'name' => 'Admin Sekolah', 'email' => 'admin@sdcerianusantara.sch.id', 'password' => Hash::make('Ceria2026!'), 'role' => 'admin', 'active' => true,
            'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(),
        ]);
        foreach (require base_path('tests/Fixtures/landing-content.php') as $key => $data) $this->store->put('content', $key, $data);
        foreach (json_decode(file_get_contents(database_path('seeders/demo.json')), true) as $index => $row) {
            if ($row['kind'] === 'content') continue;
            SchoolRecord::create(['id' => (string) ($index + 1)] + $row);
        }
        foreach ([['INV-2026-001', 'VA-20261004-18', 200000, 'Virtual account', 'Terverifikasi'], ['INV-2026-001', 'TRF-20261005-01', 100000, 'Transfer manual', 'Menunggu'], ['INV-2026-003', 'VA-20261004-19', 300000, 'Virtual account', 'Terverifikasi']] as $index => [$code, $ref, $amount, $method, $status]) {
            $this->store->put('school_payments', (string) ($index + 1), ['reference' => $ref, 'invoice_id' => SchoolRecord::where('code', $code)->first()->id, 'amount' => $amount, 'method' => $method, 'status' => $status, 'received_at' => '2026-10-05', 'user_id' => $this->admin()->id, 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]);
        }
        CloudData::clear();
    }
    protected function admin(): User { return User::find(hash('sha256', 'admin@sdcerianusantara.sch.id')); }
    protected function assertCloudCount(string $collection, int $expected): void { $this->assertCount($expected, CloudData::table($collection)->get()); }
    protected function assertCloudHas(string $collection, array $data): void
    {
        $query = CloudData::table($collection);
        foreach ($data as $key => $value) $query->where($key, $value);
        $this->assertTrue($query->exists());
    }
}
