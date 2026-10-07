<?php

namespace Database\Seeders;

use App\Models\{User, SchoolRecord};
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (!app()->environment(['local','testing'])) throw new \RuntimeException('Seeder contoh hanya untuk local/testing.');
        $admin=User::firstOrCreate(['email'=>'admin@sdcerianusantara.sch.id'],['name'=>'Admin Sekolah','password'=>Hash::make('Ceria2026!')]);
        foreach(json_decode(file_get_contents(__DIR__.'/demo.json'),true) as $row) SchoolRecord::firstOrCreate(['code'=>$row['code']],$row);
        foreach([['INV-2026-001','VA-20261004-18',200000,'Virtual account','Terverifikasi'],['INV-2026-001','TRF-20261005-01',100000,'Transfer manual','Menunggu'],['INV-2026-003','VA-20261004-19',300000,'Virtual account','Terverifikasi']] as [$code,$ref,$amount,$method,$status]) {
            if (!DB::table('school_payments')->where('reference',$ref)->exists()) DB::table('school_payments')->insert(['reference'=>$ref,'invoice_id'=>SchoolRecord::where('code',$code)->first()->id,'amount'=>$amount,'method'=>$method,'status'=>$status,'received_at'=>'2026-10-05','user_id'=>$admin->id,'created_at'=>now(),'updated_at'=>now()]);
        }
    }
}
