<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TeacherDirectory;
use App\Services\WebsiteContent;
use Tests\Support\CloudTestCase;

class WebsiteEditorTest extends CloudTestCase
{
    public function test_real_landing_pages_render_inside_a_private_preview(): void
    {
        $this->get('/admin/website/preview/frame')->assertRedirect('/login');
        $this->actingAs($this->admin());
        foreach (['home','about','program','facilities','teachers','gallery','contact','privacy','terms'] as $page) {
            $this->get('/admin/website/preview/frame?page='.$page.'&edit=1')->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertSee('/landing-preview/css/site.css', false)
                ->assertSee('/js/website-preview.js', false);
        }
        $this->get('/admin/website/preview/frame?page=missing')->assertNotFound();
        $this->get('/admin/website/home/edit')->assertOk()->assertSee('data-cms-panel="hero"', false)->assertSee('data-cms-frame', false);
        $this->get('/admin/website/teachers/edit')->assertOk()->assertSee('Wali Kelas')->assertSee('Administrasi &amp; Data', false);
        $this->actingAs(new User(['id'=>'academic','name'=>'Academic','role'=>'akademik','active'=>true]));
        $this->get('/admin/website/preview/frame')->assertForbidden();
        $this->post('/admin/website/home/preview')->assertForbidden();
    }

    public function test_unsaved_preview_uses_form_values_without_saving_or_publishing(): void
    {
        $content = app(WebsiteContent::class);
        $before = $this->store->documents;
        $this->actingAs($this->admin())->post('/admin/website/home/preview', [
            'version'=>$content->editor('home')['version'], 'values'=>['title'=>'Pratinjau tanpa menyimpan <script>alert(1)</script>', 'campus_image'=>'/media/kampus-preview.jpg'],
        ])->assertOk()->assertSee('Pratinjau tanpa menyimpan &lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('src="/media/kampus-preview.jpg"',false);
        $photoIndex = hash('sha256', 'people.0.photo');
        $this->post('/admin/website/teachers/preview', [
            'version'=>$content->editor('teachers')['version'], 'values'=>['people.0.photo'=>''],
            'uploads'=>[$photoIndex=>\Illuminate\Http\UploadedFile::fake()->createWithContent('preview.jpg',file_get_contents(base_path('tests/Fixtures/admin-profile.jpg')))],
        ])->assertOk()->assertSee('data:image/jpeg;base64,',false);
        $this->assertSame($before, $this->store->documents);
    }

    public function test_adding_and_removing_people_preserves_the_organization_references(): void
    {
        $this->actingAs($this->admin());
        $content = app(WebsiteContent::class);
        $published = $this->store->get('content','teachers');
        $original = $content->editor('teachers')['data'];
        $this->post('/admin/website/teachers/edit', ['version'=>$content->editor('teachers')['version'], 'operation'=>'add:people'])->assertSessionHasNoErrors();
        $this->assertCount(31, $content->editor('teachers')['data']['people']);
        $this->post('/admin/website/teachers/edit', ['version'=>$content->editor('teachers')['version'], 'operation'=>'remove:people:3'])->assertSessionHasNoErrors();
        $data = $content->editor('teachers')['data'];
        $this->assertCount(30,$data['people']);
        $firstMember = $data['structure']['branches'][0]['teams'][0]['members'][0];
        $this->assertSame($original['people'][4]['name'], $data['people'][$firstMember]['name']);
        TeacherDirectory::validate($data);
        $this->assertSame($published,$this->store->get('content','teachers'));
        $this->post('/admin/website/teachers/edit', ['version'=>$content->editor('teachers')['version'], 'operation'=>'down:people:0'])->assertSessionHasNoErrors();
        $data = $content->editor('teachers')['data'];
        $this->assertSame($original['people'][0]['name'],$data['people'][$data['structure']['head']]['name']);
        $this->assertSame($original['people'][1]['name'],$data['people'][$data['structure']['branches'][0]['lead']]['name']);
        $this->get('/admin/website/preview/frame?page=teachers')->assertOk();
    }

    public function test_leaders_invalid_lists_and_stale_editors_are_protected(): void
    {
        $this->actingAs($this->admin());
        $content = app(WebsiteContent::class);
        $version = $content->editor('teachers')['version'];
        foreach (['remove:people:0','remove:people:1','remove:people:999','add:private.keys','up:people:0'] as $operation) {
            $this->post('/admin/website/teachers/edit', compact('version','operation'))->assertSessionHasErrors('content');
            $this->assertNull($this->store->get('dashboard_content_drafts','teachers'));
        }
        $this->post('/admin/website/teachers/edit', ['version'=>$version,'operation'=>'add:subjects'])->assertSessionHasNoErrors();
        $this->post('/admin/website/teachers/edit', ['version'=>$version,'operation'=>'add:people'])->assertSessionHasErrors('content');
        $this->post('/admin/website/teachers/preview', ['version'=>$version,'values'=>['directory_title'=>'Stale preview']])->assertSessionHasErrors('content');
    }

    public function test_empty_lists_can_be_filled_again_and_deleted_cards_stay_deleted(): void
    {
        $this->actingAs($this->admin());
        $content = app(WebsiteContent::class);
        $editor = $content->editor('gallery');
        foreach ([2,1,0] as $index) {
            $this->post('/admin/website/gallery/edit', ['version'=>$content->editor('gallery')['version'],'operation'=>'remove:news:'.$index])->assertSessionHasNoErrors();
        }
        $this->assertSame([], $content->editor('gallery')['data']['news']);
        $content->publish();
        $this->assertSame([], $this->store->get('content','gallery')['news']);
        $this->get('/admin/website/preview/frame?page=gallery')->assertOk();
        $this->post('/admin/website/gallery/edit', ['version'=>$content->editor('gallery')['version'],'operation'=>'add:news'])->assertSessionHasNoErrors();
        $this->assertCount(1,$content->editor('gallery')['data']['news']);
    }
}
