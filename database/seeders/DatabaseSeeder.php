<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        throw new \RuntimeException('Seeder demo dinonaktifkan. Aplikasi memakai Firestore. Gunakan school:import-local untuk menyalin data lama tanpa menimpa data cloud.');
    }
}
