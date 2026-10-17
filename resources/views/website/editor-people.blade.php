@php($teacherTeams=\App\Services\WebsiteEditor::teacherTeams($data))
<label class="field">Cari nama atau peran<input type="search" data-cms-person-search placeholder="Contoh: wali kelas, UKS…"></label>
<label class="field">Tim<select data-cms-team-filter><option value="">Semua tim</option>@foreach($teacherTeams as $key=>$team)<option value="{{ $key }}">{{ $team['label'] }}</option>@endforeach</select></label>
<p class="cms-help">Ubah nama, foto, dan peran melalui kartu anggota. Pembagian tim mengikuti Struktur organisasi.</p>
@foreach($teacherTeams as $teamKey=>$team)
<section class="cms-team" data-cms-team="{{ $teamKey }}"><h4>{{ $team['label'] }} <span>{{ count($team['members']) }}</span></h4>
    @foreach($team['members'] as $personIndex)
        <div data-cms-person-index="{{ $personIndex }}" data-cms-person="{{ mb_strtolower($data['people'][$personIndex]['name'].' '.$data['people'][$personIndex]['role']) }}">
            @include('website.editor-item',['entry'=>$data['people'][$personIndex], 'itemPath'=>'people.'.$personIndex,'listPath'=>'people','itemIndex'=>$personIndex,'itemCount'=>count($data['people'])])
        </div>
    @endforeach
</section>
@endforeach
<p class="cms-help" data-cms-people-empty hidden>Tidak ada anggota yang sesuai. Coba nama atau tim lain.</p>
<button class="cms-add" name="operation" value="add:people">+ Tambah anggota</button>
