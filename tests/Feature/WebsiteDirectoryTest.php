<?php

namespace Tests\Feature;

use App\Services\TeacherDirectory;
use App\Services\WebsiteContent;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\Support\CloudTestCase;

class WebsiteDirectoryTest extends CloudTestCase
{
    public function test_directory_changes_keep_shared_references_without_publishing_early(): void
    {
        $content = app(WebsiteContent::class);
        $before = $this->store->get('content', 'teachers');
        $editor = $content->editor('teachers');
        $this->assertCount(30, $editor['data']['people']);
        $this->actingAs($this->admin())->get('/admin/website/teachers/edit')->assertOk()
            ->assertSee('Guru di beranda')->assertSee('Direktori Guru &amp; Staf', false)
            ->assertSee('values[structure.head]', false)->assertDontSee('name="values[people.0.portrait]"', false);
        $this->post('/admin/website/teachers/edit', ['version'=>$editor['version'], 'values'=>[
            'people.0.name'=>'Nama kepala dari dashboard', 'people.0.group'=>'pimpinan',
            'subjects.0.by'=>'Tim pengajar baru', 'structure.head'=>'1', 'demo_profiles'=>'0',
        ]])->assertSessionHasNoErrors();
        $this->assertSame($before, $this->store->get('content', 'teachers'));
        $draft = $content->editor('teachers')['data'];
        $this->assertSame(1, $draft['structure']['head']);
        $this->assertFalse($draft['demo_profiles']);
        $this->assertSame($editor['data']['featured_people'], $draft['featured_people']);
        $this->assertArrayNotHasKey('team', $draft);
        $this->get('/admin/website/preview/frame?page=teachers')->assertOk()->assertSee('Nama kepala dari dashboard')->assertSee('Tim pengajar baru');
        $content->publish();
        $saved = $this->store->get('content', 'teachers');
        TeacherDirectory::validate($saved);
        $this->assertSame('Nama kepala dari dashboard', $saved['people'][0]['name']);
        $this->assertSame($draft['featured_people'], $saved['featured_people']);
        $this->assertArrayNotHasKey('team', $saved);
        $this->assertSame(1, $saved['structure']['head']);
    }

    public function test_invalid_category_and_organization_reference_cannot_be_saved(): void
    {
        $this->actingAs($this->admin());
        $editor = app(WebsiteContent::class)->editor('teachers');
        foreach ([['people.0.group'=>'unknown'], ['structure.head'=>'999'], ['structure.branches.0.lead'=>'-1'], ['demo_profiles'=>'not-a-bool']] as $values) {
            $this->post('/admin/website/teachers/edit', ['version'=>$editor['version'], 'values'=>$values])->assertSessionHasErrors();
            $this->assertNull($this->store->get('dashboard_content_drafts', 'teachers'));
        }
    }

    public function test_new_photo_can_replace_empty_override_and_existing_team_assets_remain_valid(): void
    {
        $editor = app(WebsiteContent::class)->editor('teachers');
        $index = hash('sha256', 'people.0.photo');
        $this->actingAs($this->admin())->post('/admin/website/teachers/edit', [
            'version'=>$editor['version'], 'values'=>['people.0.photo'=>''],
            'uploads'=>[$index=>UploadedFile::fake()->createWithContent('teacher.jpg', file_get_contents(base_path('tests/Fixtures/admin-profile.jpg')))],
        ])->assertSessionHasNoErrors();
        $data = app(WebsiteContent::class)->editor('teachers')['data'];
        $this->assertStringStartsWith('/media/', $data['people'][0]['photo']);
        $this->assertSame('/assets/team/maria-simanjuntak.jpg', $data['people'][10]['photo']);
        $this->assertSame(rtrim(config('school.landing_url'),'/').'/assets/team/maria-simanjuntak.jpg', WebsiteContent::imageUrl($data['people'][10]['photo']));
        $this->assertSame('', WebsiteContent::image('/assets/team/../../private.jpg'));
    }

    public function test_lists_replace_whole_lists_and_concurrent_reorder_is_rejected(): void
    {
        $content = app(WebsiteContent::class);
        $before = ['team'=>[['name'=>'A','role'=>'One'], ['name'=>'B','role'=>'Two']], 'future'=>'kept'];
        $short = [...$before, 'team'=>[['name'=>'A','role'=>'Changed']]];
        $merged = $content->mergeDraft($before, ['base'=>$before,'data'=>$short], true);
        $this->assertCount(1, $merged['team']);
        $this->assertSame('kept', $merged['future']);
        $reordered = [...$before, 'team'=>array_reverse($before['team'])];
        $this->expectException(ValidationException::class);
        $content->mergeDraft($reordered, ['base'=>$before, 'data'=>$short], true);
    }
}
