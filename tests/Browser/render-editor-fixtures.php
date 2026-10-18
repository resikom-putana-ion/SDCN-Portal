<?php

// Render real controllers and landing templates with isolated, in-memory school data.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver'=>'array','cache.default'=>'array','database.default'=>'disabled','school.firebase_auth_enabled'=>false]);
Illuminate\Support\Facades\Http::preventStrayRequests();
$store = new Tests\Support\MemoryFirestore;
$app->instance(App\Services\FirestoreDocumentStore::class, $store);
auth()->setUser(new App\Models\User(['id'=>'editor-preview','name'=>'Admin Sekolah','email'=>'admin@example.test','role'=>'admin','active'=>true]));
view()->share('errors', new Illuminate\Support\ViewErrorBag);
foreach (require __DIR__.'/../Fixtures/landing-content.php' as $key=>$data) $store->put('content',$key,$data);
foreach (json_decode(file_get_contents(database_path('seeders/demo.json')),true) as $index=>$row) {
    if ($row['kind'] !== 'content') App\Models\SchoolRecord::create(['id'=>(string)($index+1)]+$row);
}
$output = storage_path('app/editor-browser-check');
if (!is_dir($output)) mkdir($output,0777,true);
$content = app(App\Services\WebsiteContent::class);
$controller = app(App\Http\Controllers\WebsiteController::class);
foreach (array_keys(App\Services\WebsiteContent::SECTIONS) as $section) {
    $app->instance('request', Illuminate\Http\Request::create('/admin/website/'.$section.'/edit'));
    file_put_contents($output.'/editor-'.$section.'.html',$controller->edit($section,$content)->render());
}
$request = Illuminate\Http\Request::create('/admin/website');
$app->instance('request',$request);
file_put_contents($output.'/manage.html',app(App\Http\Controllers\AdminController::class)->page($request,'website')->render());
foreach (array_keys(App\Services\LandingPreview::PAGES) as $page) {
    $app->instance('request',Illuminate\Http\Request::create('/admin/website/preview/frame?page='.$page.'&edit=1'));
    file_put_contents($output.'/frame-'.$page.'.html',App\Services\LandingPreview::render($content->all(true),$page,true)->render());
}
$site = $content->all(true);
$site['home']['title'] = 'JUDUL UJI PRATINJAU';
$app->instance('request',Illuminate\Http\Request::create('/admin/website/preview/frame?page=home&edit=1'));
file_put_contents($output.'/frame-updated.html',App\Services\LandingPreview::render($site,'home',true)->render());

$renderManagement = function (string $screen, string $file) use ($app, $output) {
    $request = Illuminate\Http\Request::create('/admin/'.$screen);
    $app->instance('request', $request);
    file_put_contents($output.'/'.$file, app(App\Http\Controllers\AdminController::class)->page($request, $screen)->render());
};
$renderManagement('publikasi','publication-empty.html');
foreach (App\Services\WebsiteContent::SECTIONS as $section=>$name) {
    $editor = $content->editor($section);
    $field = match ($section) {'settings'=>'school_name','teachers'=>'directory_title',default=>'title'};
    $data = [...$editor['data'], $field=>$section==='home'?'JUDUL UJI PRATINJAU':'Pembaruan '.$name];
    $content->save($section,$data,$editor['version']);
}
$renderManagement('publikasi','publication.html');
$renderManagement('website','manage-drafts.html');
$content->publish($content->review()['version']);
$renderManagement('terbit','published.html');
echo "Rendered isolated editor, management, and landing preview pages.\n";
