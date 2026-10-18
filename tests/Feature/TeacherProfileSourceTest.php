<?php

namespace Tests\Feature;

use App\Services\FirestoreCacheStore;
use App\Services\TeacherDirectory;
use App\Services\WebsiteContent;
use App\Services\WebsiteEditor;
use App\Support\CloudData;
use Tests\Support\CloudTestCase;

class TeacherProfileSourceTest extends CloudTestCase
{
    private function legacy(): array
    {
        $data = TeacherDirectory::withDefaults($this->store->get('content', 'teachers'));
        $data['people'][0]['name'] = 'Kepala sesuai struktur';
        $data['people'][0]['photo'] = '/media/structure-head.jpg';
        $data['directory'] = $data['people'];
        $data['directory'][0]['name'] = 'Kepala dari daftar lama';
        $data['directory'][0]['role'] = 'King Sigma';
        $data['team'] = [$data['directory'][0], $data['directory'][3]];
        unset($data['featured_people']);
        return $data;
    }

    public function test_editor_and_every_public_placement_follow_the_organization_profiles(): void
    {
        $this->store->put('content', 'teachers', $this->legacy());
        CloudData::clear();
        $this->actingAs($this->admin())->get('/admin/website/teachers/edit?block=other')
            ->assertOk()->assertSee('data-initial-block="people"', false)
            ->assertSee('Kepala sesuai struktur')->assertDontSee('King Sigma')
            ->assertDontSee('name="values[directory.', false)->assertDontSee('name="values[team.', false);
        $this->get('/admin/website/home/edit')->assertDontSee('name="values[principal_name]"', false);
        $content = app(WebsiteContent::class);
        $this->assertSame([0, 3], $content->editor('teachers')['data']['featured_people']);
        foreach (['teachers', 'home', 'about'] as $page) {
            $this->get('/admin/website/preview/frame?page='.$page)->assertOk()
                ->assertSee('Kepala sesuai struktur')->assertDontSee('Kepala dari daftar lama')->assertDontSee('King Sigma');
        }
        $this->post('/admin/website/teachers/edit', [
            'version'=>$content->editor('teachers')['version'],
            'values'=>['people.0.name'=>'Nama baru satu sumber', 'people.0.photo'=>'/media/new-head.jpg'],
        ])->assertSessionHasNoErrors();
        foreach (['teachers', 'home', 'about'] as $page) {
            $this->get('/admin/website/preview/frame?page='.$page)->assertOk()->assertSee('Nama baru satu sumber');
            $this->get('/admin/website/preview/frame?page='.$page.'&state=published')->assertDontSee('Nama baru satu sumber');
        }
        $this->get('/admin/website/preview/frame?page=home')->assertSee('/media/new-head.jpg');
        $content->publish();
        $saved = $this->store->get('content', 'teachers');
        $this->assertArrayNotHasKey('directory', $saved);
        $this->assertArrayNotHasKey('team', $saved);
        $this->get('/admin/website/preview/frame?page=home&state=published')->assertSee('Nama baru satu sumber');
    }

    public function test_head_selection_reordering_and_removal_keep_the_same_featured_people(): void
    {
        $data = TeacherDirectory::withDefaults($this->legacy());
        $data['featured_people'] = [0, 3];
        $reordered = WebsiteEditor::operation($data, 'teachers', 'down:people:0');
        $this->assertSame('Kepala sesuai struktur', TeacherDirectory::principal($reordered)['name']);
        $this->assertSame(array_column(TeacherDirectory::homepagePeople($data), 'name'),
            array_column(TeacherDirectory::homepagePeople($reordered), 'name'));
        $removed = WebsiteEditor::operation($reordered, 'teachers', 'remove:people:3');
        $this->assertSame([1], $removed['featured_people']);
        TeacherDirectory::validate($removed);
        $removed['structure']['head'] = 0;
        $this->assertSame($removed['people'][0]['name'], TeacherDirectory::homepagePeople($removed)[0]['name']);
        $this->assertCount(2, TeacherDirectory::homepagePeople($removed));
    }

    public function test_invalid_and_duplicate_homepage_references_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $data = TeacherDirectory::withDefaults($this->legacy());
        $this->store->put('content', 'teachers', $data);
        $content = app(WebsiteContent::class);
        foreach (['999', '0'] as $invalid) {
            $this->post('/admin/website/teachers/edit', [
                'version'=>$content->editor('teachers')['version'], 'values'=>['featured_people.1'=>$invalid],
            ])->assertSessionHasErrors();
        }
        $this->assertNull($this->store->get('dashboard_content_drafts', 'teachers'));
    }

    public function test_migration_backs_up_legacy_data_preserves_drafts_and_is_idempotent(): void
    {
        $legacy = $this->legacy();
        $legacy['future_setting'] = 'keep';
        $this->store->put('content', 'teachers', $legacy);
        $draft = $legacy;
        $draft['people'][3]['name'] = 'Perubahan belum terbit';
        $this->store->put('dashboard_content_drafts', 'teachers', ['base'=>$legacy, 'data'=>$draft, 'author'=>'Editor']);
        $cache = new FirestoreCacheStore($this->store, config('school.landing_cache_prefix'));
        $cache->put('school-content-firestore', ['stale'=>true], 300);
        $before = $this->store->documents;
        $this->artisan('school:normalize-teachers')->assertSuccessful();
        $this->assertSame($before, $this->store->documents);
        $existing = glob(storage_path('app/private/content-backups/teacher-profiles-*.json'));
        try {
            $this->artisan('school:normalize-teachers', ['--apply'=>true])->assertSuccessful();
            $backups = array_values(array_diff(glob(storage_path('app/private/content-backups/teacher-profiles-*.json')), $existing));
            $this->assertCount(1, $backups);
            $backup = json_decode(file_get_contents($backups[0]), true);
            $this->assertSame($legacy['directory'], $backup['before']['content/teachers']['directory']);
            $saved = $this->store->get('content', 'teachers');
            $this->assertSame($legacy['people'], $saved['people']);
            $this->assertSame([0, 3], $saved['featured_people']);
            $this->assertSame('keep', $saved['future_setting']);
            $this->assertArrayNotHasKey('directory', $saved);
            $this->assertArrayNotHasKey('team', $saved);
            $this->assertArrayNotHasKey('principal_name', $this->store->get('content', 'home'));
            $this->assertSame('Perubahan belum terbit', $this->store->get('dashboard_content_drafts', 'teachers')['data']['people'][3]['name']);
            $this->assertNull($cache->get('school-content-firestore'));
            $after = $this->store->documents;
            $this->artisan('school:normalize-teachers', ['--apply'=>true])->assertSuccessful();
            $this->assertSame($after, $this->store->documents);
        } finally {
            foreach (array_diff(glob(storage_path('app/private/content-backups/teacher-profiles-*.json')), $existing) as $path) unlink($path);
        }
    }
}
