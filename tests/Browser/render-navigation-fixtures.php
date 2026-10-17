<?php

// Navigation checks use only fixture records and never connect to the school's cloud.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver'=>'array', 'cache.default'=>'array', 'database.default'=>'disabled', 'school.firebase_auth_enabled'=>false]);
Illuminate\Support\Facades\Http::preventStrayRequests();
$store = new Tests\Support\MemoryFirestore;
$app->instance(App\Services\FirestoreDocumentStore::class, $store);
auth()->setUser(new App\Models\User(['id'=>'navigation-preview', 'name'=>'Admin Sekolah', 'email'=>'admin@example.test', 'role'=>'admin', 'active'=>true]));
view()->share('errors', new Illuminate\Support\ViewErrorBag);
foreach (require __DIR__.'/../Fixtures/landing-content.php' as $key=>$data) $store->put('content', $key, $data);
foreach (json_decode(file_get_contents(database_path('seeders/demo.json')), true) as $index=>$row) {
    if ($row['kind'] !== 'content') App\Models\SchoolRecord::create(['id'=>(string)($index+1)]+$row);
}
$student = App\Models\SchoolRecord::ofKind('students')->first();
$profile = '/admin/siswa/'.$student->id;
$query = ['q'=>explode(' ', $student->value('name'))[0], 'filter'=>'Aktif', 'year'=>$student->value('year')];
$list = '/admin/siswa?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
$output = storage_path('app/navigation-browser-check');
if (!is_dir($output)) mkdir($output, 0777, true);
$controller = app(App\Http\Controllers\AdminController::class);
foreach (['list'=>[$list, null, null], 'profile'=>[$profile, $student->id, null], 'documents'=>[$profile.'/dokumen', $student->id, 'dokumen'], 'edit'=>[$profile.'/edit', $student->id, 'edit']] as $file=>[$url,$id,$tab]) {
    $request = Illuminate\Http\Request::create('http://navigation.test'.$url);
    $request->setLaravelSession(app('session')->driver());
    $app->instance('request', $request);
    file_put_contents($output.'/'.$file.'.html', $controller->page($request, 'siswa', $id, $tab)->render());
}
file_put_contents($output.'/manifest.json', json_encode(compact('profile', 'list'), JSON_THROW_ON_ERROR));
echo "Rendered isolated navigation fixtures.\n";
