<?php

// Render the real views against memory fixtures; never connect browser checks to live data.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array', 'cache.default' => 'array', 'database.default' => 'disabled']);
Illuminate\Support\Facades\Http::preventStrayRequests();
$store = new Tests\Support\MemoryFirestore;
$app->instance(App\Services\FirestoreDocumentStore::class, $store);
auth()->setUser(new App\Models\User(['id' => 'crop-preview', 'name' => 'Admin Sekolah', 'email' => 'admin@example.test', 'role' => 'admin', 'active' => true]));
view()->share('errors', new Illuminate\Support\ViewErrorBag);
foreach (require __DIR__.'/../Fixtures/landing-content.php' as $key => $data) $store->put('content', $key, $data);
foreach (json_decode(file_get_contents(database_path('seeders/demo.json')), true) as $index => $row) {
    if ($row['kind'] !== 'content') App\Models\SchoolRecord::create(['id' => (string) ($index + 1)] + $row);
}
$output = storage_path('app/crop-browser-check');
if (!is_dir($output)) mkdir($output, 0777, true);
$admin = app(App\Http\Controllers\AdminController::class);
$website = app(App\Http\Controllers\WebsiteController::class);
$request = Illuminate\Http\Request::create('/admin/pengaturan');
$app->instance('request', $request);
$views = [
    'admin' => $admin->page($request, 'pengaturan'),
    'student' => $admin->page($request, 'siswa', 'baru'),
    'staff' => $admin->page($request, 'guru', 'baru'),
    'media' => $admin->page($request, 'media'),
    'website' => $website->edit('teachers', app(App\Services\WebsiteContent::class)),
    'ppdb' => $website->application(),
];
foreach ($views as $name => $view) file_put_contents($output.'/'.$name.'.html', $view->render());
echo "Rendered six isolated crop pages.\n";
