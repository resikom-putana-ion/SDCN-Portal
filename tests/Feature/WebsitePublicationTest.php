<?php

namespace Tests\Feature;

use App\Services\WebsiteContent;
use App\Support\CloudData;
use Illuminate\Http\UploadedFile;
use Tests\Support\CloudTestCase;

class WebsitePublicationTest extends CloudTestCase
{
    public function test_all_three_home_slides_upload_publish_and_render_independently(): void
    {
        $this->actingAs($this->admin());
        $content = app(WebsiteContent::class);
        $this->get('/admin/website/home/edit')->assertOk()
            ->assertSee('Foto slide 1')->assertSee('Foto slide 2')->assertSee('Foto slide 3');
        $original = $this->store->get('content', 'home');
        $paths = [];
        foreach (['campus_image', 'image', 'classroom_image'] as $key) {
            $editor = $content->editor('home');
            $this->post('/admin/website/home/edit', [
                'version'=>$editor['version'], 'values'=>['title'=>$editor['data']['title']],
                'uploads'=>[hash('sha256', $key)=>UploadedFile::fake()->createWithContent($key.'.jpg', file_get_contents(base_path('tests/Fixtures/admin-profile.jpg')))],
            ])->assertSessionHasNoErrors();
            $paths[$key] = $content->editor('home')['data'][$key];
            $this->assertStringStartsWith('/media/', $paths[$key]);
            $this->assertSame($original, $this->store->get('content', 'home'));
            foreach ($paths as $field=>$path) $this->assertSame($path, $content->editor('home')['data'][$field]);
        }
        $this->post('/admin/website/publish', ['review_version'=>$content->review()['version'], 'confirmed'=>1])->assertSessionHasNoErrors();
        $preview = $this->get('/admin/website/preview/frame?page=home&state=published')->assertOk();
        $this->assertSame(3, substr_count($preview->getContent(), 'class="hero-slide '));
        foreach ($paths as $key=>$path) {
            $this->assertSame($path, $this->store->get('content', 'home')[$key]);
            $preview->assertSee($path, false);
            $this->get($path)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        }
    }

    public function test_all_pages_save_review_and_publish_the_exact_reviewed_content(): void
    {
        $this->actingAs($this->admin());
        $content = app(WebsiteContent::class);
        $expected = [];
        foreach (WebsiteContent::SECTIONS as $section => $name) {
            $field = match ($section) { 'settings'=>'school_name', 'teachers'=>'directory_title', default=>'title' };
            $editor = $content->editor($section);
            $before = $this->store->get('content', $section);
            $expected[$section] = [$field, 'Konten baru '.$name];
            $this->get('/admin/website/'.$section.'/edit')->assertOk()->assertSee('Simpan & tinjau', false);
            $this->post('/admin/website/'.$section.'/edit', [
                'version'=>$editor['version'], 'values'=>[$field=>$expected[$section][1]], 'intent'=>'review',
            ])->assertRedirect('/admin/publikasi')->assertSessionHasNoErrors();
            $this->assertSame($before, $this->store->get('content', $section));
        }
        $review = $content->review();
        $this->assertCount(8, $review['pages']);
        $response = $this->get('/admin/publikasi')->assertOk()->assertSee('Sedang tayang')->assertSee('Setelah diterbitkan');
        foreach ($expected as [$field, $value]) $response->assertSee($value);
        $this->get('/admin/website/preview/frame?page=home&state=published')->assertDontSee($expected['home'][1]);
        $this->get('/admin/website/preview/frame?page=home')->assertSee($expected['home'][1]);
        $cache = new \App\Services\FirestoreCacheStore($this->store, config('school.landing_cache_prefix'));
        $cache->put('school-content-firestore', ['old'=>'cached content'], 600);
        $this->post('/admin/website/publish', ['review_version'=>$review['version'], 'confirmed'=>1])
            ->assertRedirect('/admin/terbit')->assertSessionHasNoErrors();
        foreach ($expected as $section => [$field, $value]) $this->assertSame($value, $this->store->get('content', $section)[$field]);
        $this->assertSame([], $this->store->all('dashboard_content_drafts'));
        $this->assertNull($cache->get('school-content-firestore'));
        $this->get('/admin/terbit')->assertOk()->assertSee('8 halaman')->assertSee('Perubahan berhasil diterbitkan');
        $this->get('/admin/website/preview/frame?page=home&state=published')->assertSee($expected['home'][1]);
        $this->get('/admin/publikasi')->assertSee('Belum ada perubahan untuk diterbitkan');
        $this->post('/admin/website/publish', ['review_version'=>$review['version'], 'confirmed'=>1])->assertSessionHasErrors('content');
    }

    public function test_unchanged_forms_do_not_create_drafts_and_async_save_returns_the_next_version(): void
    {
        $this->actingAs($this->admin());
        $content = app(WebsiteContent::class);
        foreach (['home', 'teachers', 'settings'] as $section) {
            $editor = $content->editor($section);
            $values = array_filter(\Illuminate\Support\Arr::dot($editor['data']), fn ($value) => !is_array($value));
            $values = array_map(fn ($value) => is_bool($value) ? ($value ? '1' : '0') : (string) $value, $values);
            $this->postJson('/admin/website/'.$section.'/edit', ['version'=>$editor['version'], 'values'=>$values])
                ->assertOk()->assertJsonPath('has_draft', false);
        }
        $version = $content->editor('home')['version'];
        $saved = $this->postJson('/admin/website/home/edit', ['version'=>$version, 'values'=>['title'=>'Draf pertama']])
            ->assertOk()->assertJsonPath('has_draft', true)->json();
        $this->assertNotSame($version, $saved['version']);
        $this->postJson('/admin/website/home/edit', ['version'=>$saved['version'], 'values'=>['title'=>'Draf berikutnya']])->assertOk();
        $this->assertSame('Draf berikutnya', $content->editor('home')['data']['title']);
    }

    public function test_changes_after_review_block_publication_and_preserve_every_draft(): void
    {
        $this->actingAs($this->admin());
        $content = app(WebsiteContent::class);
        foreach (['home','about'] as $section) {
            $editor = $content->editor($section);
            $content->save($section, [...$editor['data'], 'title'=>'Draf '.$section], $editor['version']);
        }
        $review = $content->review();
        $editor = $content->editor('home');
        $content->save('home', [...$editor['data'], 'title'=>'Diubah editor lain'], $editor['version']);
        $before = $this->store->documents;
        $this->post('/admin/website/publish', ['review_version'=>$review['version'], 'confirmed'=>1])->assertSessionHasErrors('content');
        $this->assertSame($before, $this->store->documents);
        $review = $content->review();
        $live = $this->store->get('content','about');
        $this->store->put('content','about',[...$live,'title'=>'Perubahan dari website']);
        CloudData::clear();
        $this->get('/admin/publikasi')->assertOk()->assertSee('Perlu diperiksa')->assertSee('Perubahan dari website');
        $this->post('/admin/website/publish', ['review_version'=>$review['version'], 'confirmed'=>1])->assertSessionHasErrors('content');
        $this->assertCount(2, $this->store->all('dashboard_content_drafts'));
    }

    public function test_cancel_only_removes_the_reviewed_draft_without_changing_published_content(): void
    {
        $this->actingAs($this->admin());
        $content = app(WebsiteContent::class);
        $editor = $content->editor('home');
        $content->save('home', [...$editor['data'], 'title'=>'Dibatalkan'], $editor['version']);
        $this->post('/admin/website/home/discard', ['version'=>$editor['version']])->assertSessionHasErrors('content');
        $this->post('/admin/website/home/discard', ['version'=>$content->editor('home')['version']])->assertRedirect('/admin/publikasi');
        $this->assertNull($this->store->get('dashboard_content_drafts','home'));
        $this->assertSame($editor['published'], $content->editor('home')['published']);
    }

    public function test_image_upload_keeps_its_field_when_firestore_field_order_changes(): void
    {
        $this->actingAs($this->admin());
        $content = app(WebsiteContent::class);
        $editor = $content->editor('home');
        $this->store->put('content','home', array_reverse($this->store->get('content','home'),true));
        $photo = UploadedFile::fake()->createWithContent('campus.jpg', file_get_contents(base_path('tests/Fixtures/admin-profile.jpg')));
        $this->post('/admin/website/home/edit', [
            'version'=>$editor['version'], 'values'=>['title'=>'Gambar kampus baru'], 'uploads'=>[hash('sha256','campus_image')=>$photo],
        ])->assertSessionHasNoErrors();
        $updated = $content->editor('home')['data'];
        $this->assertStringStartsWith('/media/', $updated['campus_image']);
        $this->assertSame($editor['data']['image'], $updated['image']);
        $this->assertCount(1, $this->store->all('uploaded_files'));
        $this->get('/admin/publikasi')->assertSee($updated['campus_image'], false);
    }
}
