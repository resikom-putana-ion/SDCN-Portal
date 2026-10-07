<?php
namespace App\Http\Controllers;
use App\Models\SchoolRecord;
use App\Support\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
class WebsiteController extends Controller {
    public function publicPage() { return view('public.website',['content'=>School::content(),'period'=>School::period(),'preview'=>false]); }
    public function preview() { return view('website.preview',['title'=>'Pratinjau Landing Page','group'=>'website','screen'=>'website','content'=>School::content(true),'period'=>School::period(),'preview'=>true]); }
    public function edit(string $section) {
        $definition=School::config()['sections'][$section]??null; abort_unless($definition,404);
        $record=SchoolRecord::where('code','CONTENT-'.$section)->firstOrFail();
        return view('website.edit',['title'=>$definition['title'],'group'=>'website','screen'=>'website','definition'=>$definition,'section'=>$section,'record'=>$record]);
    }
    public function save(Request $request,string $section) {
        $definition=School::config()['sections'][$section]??null; abort_unless($definition,404);
        $rules=[]; foreach($definition['fields'] as $field) $rules[$field['name']]='required|string|max:10000';
        $data=$request->validate($rules);
        foreach($data as $key=>$value) if(str_starts_with($key,'image')) abort_unless(!str_contains($value,'..') && preg_match('/^[a-zA-Z0-9_\/.\-]+$/',$value) && file_exists(public_path('assets/'.$value)),422,'Pilih gambar dari media website.');
        $record=SchoolRecord::where('code','CONTENT-'.$section)->firstOrFail();
        $record->update(['data'=>[...$record->data,'draft'=>$data,'status'=>'Draf','author'=>auth()->user()->name,'date'=>now()->toDateString(),'summary'=>$data['title']??$definition['heading']]]);
        School::log('Menyimpan draf '.$definition['name'],'Website sekolah','Draf');
        return back()->with('success','Draf disimpan. Tinjau melalui pratinjau sebelum menerbitkan.');
    }
    public function publish(Request $request) {
        $request->validate(['checks'=>'required|array|size:5','checks.*'=>'required|distinct|in:text,image,contact,period,offline','notes'=>'required|string|max:3000']);
        DB::transaction(function()use($request){
            foreach(SchoolRecord::ofKind('content')->lockForUpdate()->get() as $record) $record->update(['data'=>[...$record->data,'published'=>$record->value('draft'),'status'=>'Terbit','published_at'=>now()->toIso8601String()]]);
            foreach(SchoolRecord::ofKind('news')->where('data->status','Draf')->get() as $record) $record->update(['data'=>[...$record->data,'status'=>'Terbit']]);
            School::log('Menerbitkan website: '.$request->notes,'Website sekolah','Terbit');
        });
        return redirect('/admin/terbit')->with('success','Website berhasil diperbarui.');
    }
    public function upload(Request $request) {
        $request->validate(['image'=>'required|image|mimes:jpg,jpeg,png,webp|max:5120','alt'=>'required|string|max:300']);
        $file=$request->file('image'); $name=Str::uuid().'.'.$file->extension();
        $file->move(public_path('assets/uploads'),$name);
        SchoolRecord::create(['kind'=>'media','code'=>'MEDIA-'.Str::uuid(),'data'=>['name'=>$file->getClientOriginalName(),'image'=>'uploads/'.$name,'alt'=>$request->alt]]);
        return back()->with('success','Media berhasil diunggah.');
    }
    public function application() { return view('public.application',['period'=>School::period()]); }
    public function submitApplication(Request $request) {
        $data=$request->validate(['name'=>'required|string|max:150','birth_date'=>'required|date|before:today','guardian'=>'required|string|max:150','relationship'=>'required|in:Ibu,Ayah,Wali','phone'=>'required|string|max:30','email'=>'required|email|max:190','gender'=>'required|in:Perempuan,Laki-laki','consent'=>'accepted','birth_certificate'=>'required|file|mimes:pdf,jpg,jpeg,png|max:5120','family_card'=>'required|file|mimes:pdf,jpg,jpeg,png|max:5120','photo'=>'required|file|mimes:jpg,jpeg,png|max:5120']);
        $paths=[];
        try {
            $number=DB::transaction(function()use($request,$data,&$paths){
                $period=SchoolRecord::ofKind('period')->lockForUpdate()->firstOrFail(); $year=substr($period->value('year'),0,4);
                $last=SchoolRecord::ofKind('applicants')->where('code','like','PPDB-'.$year.'-%')->get()->max(fn($r)=>(int)substr($r->code,-3))??0;
                $number='PPDB-'.$year.'-'.str_pad($last+1,3,'0',STR_PAD_LEFT);
                $record=SchoolRecord::create(['kind'=>'applicants','code'=>$number,'data'=>[...collect($data)->except(['consent','birth_certificate','family_card','photo'])->all(),'number'=>$number,'year'=>$period->value('year'),'date'=>now()->toDateString(),'status'=>'Pemeriksaan','payment'=>'Belum dicatat','checks'=>[],'paid'=>false,'notes'=>'']]);
                foreach(['birth_certificate'=>'Akta kelahiran','family_card'=>'Kartu Keluarga','photo'=>'Pasfoto'] as $key=>$label) {
                    $file=$request->file($key); $path=$file->store('documents','local'); $paths[]=$path;
                    DB::table('school_documents')->insert(['record_id'=>$record->id,'name'=>$label.'.'.$file->extension(),'path'=>$path,'mime'=>$file->getMimeType(),'size'=>$file->getSize(),'created_at'=>now(),'updated_at'=>now()]);
                }
                School::log('Pendaftaran baru '.$number,'PPDB','Pemeriksaan'); return $number;
            });
        } catch(\Throwable $e) { foreach($paths as $path) Storage::disk('local')->delete($path); throw $e; }
        return redirect('/ppdb/berhasil')->with('registration',$number);
    }
}
