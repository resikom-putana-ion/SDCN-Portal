<?php

namespace Tests\Feature;

use App\Models\SchoolRecord;
use App\Services\FirestoreFileStorage;
use Tests\Support\CloudTestCase;

class StudentPhotoTest extends CloudTestCase
{
    public function test_staff_name_and_crop_validation_preserve_existing_photo(): void
    {
        $this->actingAs($this->admin());
        $staff = SchoolRecord::ofKind('staff')->first();
        $staff->update(['data' => [...$staff->data, 'photo_path' => 'staff-photos/existing.jpg']]);
        $data = $staff->data;
        $data['name'] = 'Budi Santoso';
        $this->post('/admin/record/staff/'.$staff->id, $data)->assertSessionHasErrors('name');
        $data['name'] = 'BUDI SANTOSO';
        $data['photo_crop'] = 'data:image/jpeg;base64,'.base64_encode('invalid image');
        $this->post('/admin/record/staff/'.$staff->id, $data)->assertSessionHasErrors('photo_crop');
        unset($data['photo_crop']);
        $this->post('/admin/record/staff/'.$staff->id, $data)->assertSessionHasNoErrors();
        $this->assertSame('BUDI SANTOSO', $staff->fresh()->value('name'));
        $this->assertSame('staff-photos/existing.jpg', $staff->fresh()->value('photo_path'));
    }

    public function test_staff_photo_is_private_and_shown_in_form_profile_and_identity(): void
    {
        $staff = SchoolRecord::ofKind('staff')->first();
        $path = 'staff-photos/test.jpg';
        app(FirestoreFileStorage::class)->put($path, 'staff photo contents', 'image/jpeg');
        $staff->update(['data' => [...$staff->data, 'photo_path' => $path]]);
        $url = '/admin/foto-guru/'.$staff->id;
        $this->get($url)->assertRedirect('/login');
        $this->actingAs($this->admin())->get($url)->assertOk()->assertContent('staff photo contents');
        $this->get('/admin/foto-siswa/'.$staff->id)->assertNotFound();
        $this->get('/admin/guru/'.$staff->id)->assertOk()->assertSee($url);
        $this->get('/admin/identitas/'.$staff->id)->assertOk()->assertSee($url);
        $this->get('/admin/guru/'.$staff->id.'/edit')->assertOk()->assertSee($url);
        $this->get('/admin/guru/baru')->assertOk()->assertSee('Foto guru / staf')->assertSee('Gunakan potongan')->assertSee('data-capital-error', false);
    }

    public function test_student_name_and_crop_are_validated_without_overwriting_existing_photo(): void
    {
        $this->actingAs($this->admin());
        $student = SchoolRecord::ofKind('students')->first();
        $data = $student->data;
        $data['name'] = 'Budi Santoso';
        $this->post('/admin/record/students/'.$student->id, $data)->assertSessionHasErrors('name');
        $data['name'] = 'BUDI SANTOSO';
        $data['photo_crop'] = 'data:image/jpeg;base64,'.base64_encode('invalid image');
        $this->post('/admin/record/students/'.$student->id, $data)->assertSessionHasErrors('photo_crop');
        unset($data['photo_crop']);
        $this->post('/admin/record/students/'.$student->id, $data)->assertSessionHasNoErrors();
        $this->assertSame('BUDI SANTOSO', $student->fresh()->value('name'));
    }

    public function test_student_photo_is_private_and_used_by_profile_and_identity(): void
    {
        $student = SchoolRecord::ofKind('students')->first();
        $path = 'student-photos/test.jpg';
        app(FirestoreFileStorage::class)->put($path, 'photo contents', 'image/jpeg');
        $student->update(['data' => [...$student->data, 'photo_path' => $path]]);
        $url = '/admin/foto-siswa/'.$student->id;
        $this->get($url)->assertRedirect('/login');
        $this->actingAs($this->admin())->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertContent('photo contents');
        $this->get('/admin/siswa/'.$student->id)->assertOk()->assertSee($url);
        $this->get('/admin/identitas/'.$student->id)->assertOk()->assertSee($url);
        $this->get('/admin/siswa/baru')->assertOk()->assertSee('Gunakan potongan')->assertSee('data-capital-error', false);
    }
}
