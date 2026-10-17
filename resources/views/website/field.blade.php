@php
    $imageField = (bool) preg_match('/(^image$|_image$|^logo$|\.photo$)/', $key);
    $shown = old('values')[$key] ?? (is_bool($value) ? ($value ? '1' : '0') : $value);
    $options = null;
    if ($section === 'teachers') {
        if (preg_match('/^people\.\d+\.group$/', $key)) $options = ['pimpinan'=>'Pimpinan', 'guru'=>'Guru', 'staf'=>'Staf'];
        if (str_ends_with($key, '.tone')) $options = ['blue'=>'Biru', 'green'=>'Hijau', 'yellow'=>'Kuning'];
        if (preg_match('/^structure\.(head|branches\.\d+\.(lead|teams\.\d+\.members\.\d+))$/', $key)) {
            $options = array_map(fn ($person) => $person['name'].' — '.$person['role'], $data['people']);
        }
    }
@endphp
<label class="field" @if($imageField) data-crop-field @endif>{{ \App\Services\ContentLabels::label($key) }}
@if($imageField)
    @if($value)<img data-crop-preview src="{{ \App\Services\WebsiteContent::imageUrl((string)$value) }}" alt="{{ \App\Services\ContentLabels::label($key) }}" style="max-width:260px;height:150px;object-fit:cover;border-radius:12px">@endif
    <input name="values[{{ $key }}]" value="{{ $shown }}" aria-label="Lokasi gambar {{ \App\Services\ContentLabels::label($key) }}">
    <input type="file" name="uploads[{{ $fieldIndex }}]" accept=".jpg,.jpeg,.png,.webp" data-crop-input>
    <small>Pilih gambar maksimal 5 MB lalu potong pada grid; hasil unggahan maksimal 1 MB.@if(str_starts_with($key,'people.')) Kosongkan lokasi gambar untuk memakai potret bawaan.@endif</small>
@elseif($options !== null)
    <select name="values[{{ $key }}]">@foreach($options as $id=>$label)<option value="{{ $id }}" @selected((string)$shown === (string)$id)>{{ $label }}</option>@endforeach</select>
@elseif(is_bool($value))
    <select name="values[{{ $key }}]"><option value="1" @selected((string)$shown==='1')>Ya</option><option value="0" @selected((string)$shown==='0')>Tidak</option></select>
@elseif(mb_strlen((string)$value)>100 || str_contains((string)$value,"\n"))
    <textarea name="values[{{ $key }}]" rows="4" maxlength="10000">{{ $shown }}</textarea>
@else
    <input name="values[{{ $key }}]" value="{{ $shown }}" maxlength="10000" @if(is_int($value) || is_float($value)) type="number" @endif>
@endif
</label>
