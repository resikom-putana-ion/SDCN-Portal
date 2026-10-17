<?php

namespace Tests\Feature;

use App\Models\SchoolRecord;
use Tests\Support\CloudTestCase;

class DashboardHeaderTest extends CloudTestCase
{
    public function test_header_uses_the_configured_school_year_and_reflects_changes(): void
    {
        $this->actingAs($this->admin());
        $settings = SchoolRecord::ofKind('settings')->first();
        $settings->update(['data'=>[...$settings->data, 'year'=>'2032/2033']]);
        $this->get('/admin')->assertOk()->assertSee('data-header-year>2032/2033', false)
            ->assertSee('Profil &amp; pengaturan akun', false)->assertDontSee('class="account"', false);
        $settings->update(['data'=>[...$settings->data, 'year'=>'2033/2034']]);
        $this->get('/admin/website')->assertOk()->assertSee('data-header-year>2033/2034', false);
    }

    public function test_unconfigured_school_year_is_not_replaced_with_an_invented_period(): void
    {
        $settings = SchoolRecord::ofKind('settings')->first();
        $settings->update(['data'=>[...$settings->data, 'year'=>'']]);
        $this->actingAs($this->admin())->get('/admin/pengguna')->assertOk()->assertSee('data-header-year>Belum diatur', false);
    }
}
