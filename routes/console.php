<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('school:firebase-check', function () {
    $store = app(\App\Services\FirestoreDocumentStore::class);
    $content = $store->all('content');
    $admins = $store->all('admins');
    $this->info('Firestore terhubung: '.config('school.firebase_project').' / '.config('school.firebase_database'));
    $this->line('Bagian website: '.implode(', ', array_column($content, 'id')));
    $this->line('Akun pengelola: '.count($admins));
    $this->line('Session: '.config('session.driver').'; cache: '.config('cache.default').'; SQL: '.config('database.default'));
})->purpose('Verifikasi Firestore dan konfigurasi tanpa mengubah data');
