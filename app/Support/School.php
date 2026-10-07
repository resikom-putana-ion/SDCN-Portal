<?php
namespace App\Support;
use App\Models\SchoolRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class School {
    public static function config(): array { static $config; return $config ??= json_decode(file_get_contents(resource_path('data/school.json')), true); }
    public static function money($value): string { return 'Rp'.number_format((float)$value, 0, ',', '.'); }
    public static function date($value): string { try { return \Carbon\Carbon::parse($value)->locale('id')->translatedFormat('j M Y'); } catch (\Throwable) { return (string)$value; } }
    public static function log(string $name, string $section, string $status): void {
        SchoolRecord::create(['kind'=>'activities','code'=>'LOG-'.Str::uuid(),'data'=>['name'=>$name,'section'=>$section,'status'=>$status,'author'=>auth()->user()?->name ?? 'Formulir publik','date'=>now()->format('Y-m-d H:i')]]);
    }
    public static function invoice(SchoolRecord $record): array {
        $paid=(int)DB::table('school_payments')->where('invoice_id',$record->id)->where('status','Terverifikasi')->sum('amount');
        $balance=max(0, (int)$record->value('amount')-$paid);
        return [...$record->data,'paid'=>$paid,'balance'=>$balance,'status'=>$balance===0?'Lunas':($paid>0?'Sebagian':'Belum dibayar')];
    }
    public static function content(bool $draft=false): array { return SchoolRecord::ofKind('content')->get()->mapWithKeys(fn ($r)=>[$r->value('section')=>$r->value($draft?'draft':'published',[])])->all(); }
    public static function period(): array { return SchoolRecord::ofKind('period')->first()?->data ?? []; }
    public static function metric(string $kind, int $baseline, int $seed): int { return $baseline + SchoolRecord::ofKind($kind)->count() - $seed; }
    public static function badge(string $status): string { return in_array($status,['Aktif','Terbit','Terverifikasi','Lunas','Lengkap','Lolos administrasi','Selesai','Siap diaktifkan','Hadir']) ? 'success' : (in_array($status,['Pemeriksaan','Dalam pemeriksaan','Menunggu','Menunggu daftar ulang','Sebagian','Draf']) ? 'warning' : 'neutral'); }
}
