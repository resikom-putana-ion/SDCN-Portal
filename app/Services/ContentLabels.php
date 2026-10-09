<?php
namespace App\Services;

class ContentLabels
{
    public static function label(string $key): string
    {
        $labels = ['school_name'=>'Nama sekolah','email'=>'Email','whatsapp'=>'WhatsApp','address'=>'Alamat','hours'=>'Jam layanan','tagline'=>'Tagline','students'=>'Jumlah siswa','teachers'=>'Jumlah guru','founded'=>'Tahun berdiri','accreditation'=>'Akreditasi','period'=>'Periode pendaftaran','age'=>'Usia masuk','fee'=>'Biaya pendaftaran','logo'=>'Logo','facilities_image'=>'Foto fasilitas','classroom_image'=>'Foto ruang kelas','privacy'=>'Kebijakan privasi','terms'=>'Syarat & ketentuan','eyebrow'=>'Label bagian','title'=>'Judul','description'=>'Deskripsi','image'=>'Gambar utama','profile_title'=>'Judul profil','profile_subtitle'=>'Subjudul profil','profile_text'=>'Isi profil','values'=>'Nilai sekolah','advantages'=>'Keunggulan','programs'=>'Program','text'=>'Isi','principal_quote'=>'Sambutan kepala sekolah','principal_name'=>'Nama kepala sekolah','testimonials'=>'Testimoni','name'=>'Nama','faq'=>'Pertanyaan umum','question'=>'Pertanyaan','answer'=>'Jawaban','story_title'=>'Judul cerita','story_subtitle'=>'Subjudul cerita','story'=>'Cerita sekolah','quote'=>'Sambutan','approach'=>'Pendekatan','curriculum'=>'Kurikulum','activities'=>'Kegiatan','spaces'=>'Ruang','features'=>'Fasilitas','team'=>'Tim','role'=>'Jabatan','photo'=>'Foto','news'=>'Berita','category'=>'Kategori','date'=>'Tanggal','visit_hours'=>'Jam kunjungan'];
        return implode(' · ',array_map(fn($part)=>is_numeric($part)?(string)((int)$part+1):($labels[$part]??ucfirst(str_replace('_',' ',$part))),explode('.',$key)));
    }
}
