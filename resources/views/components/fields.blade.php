@props(['fields','values'=>[], 'liveNominal'=>false, 'capitalName'=>false])
<div class="form-grid">@foreach($fields as $field)@php
$value=old($field['name'],$values[$field['name']] ?? $field['value']);
@endphp
<label class="field {{ $field['type']==='textarea'?'wide':'' }}"><span>{{ __($field['label']) }}</span>
@if(str_starts_with($field['name'],'image'))<select name="{{ $field['name'] }}">@foreach(collect(['classroom.jpeg','logo.png','teacher.png'])->merge(\App\Models\SchoolRecord::ofKind('media')->get()->map(fn($m)=>$m->value('image')))->push($value)->unique() as $image)<option value="{{ $image }}" @selected($image===$value)>{{ $image }}</option>@endforeach</select>
@elseif($field['type']==='textarea')<textarea name="{{ $field['name'] }}" rows="4">{{ $value }}</textarea>
@elseif($field['type']==='select')<select name="{{ $field['name'] }}">@foreach($field['options'] as $option)<option @selected($value==$option)>{{ $option }}</option>@endforeach</select>
@elseif($field['type']==='student')<select name="{{ $field['name'] }}" required>@foreach(\App\Models\SchoolRecord::ofKind('students')->get() as $student)<option value="{{ $student->id }}" @selected($value==$student->id)>{{ $student->value('name') }} · {{ $student->value('class') }}</option>@endforeach</select>
@else
@if(in_array($field['name'],['amount','fee']))<span class="currency-input"><span class="currency-prefix" aria-hidden="true">Rp</span>@endif
<input type="{{ $field['type'] }}" name="{{ $field['name'] }}" value="{{ $value }}" @if($field['type']==='number') min="0" step="1" @endif @if(in_array($field['name'],['amount','fee'])) aria-label="{{ __($field['label']) }} (Rupiah)" @endif @if($liveNominal && $field['name']==='amount') max="1000000000" data-live-nominal aria-describedby="nominal-help nominal-error" @endif @if(!in_array($field['name'],['notes','father','summary','previous_school'])) required @endif>
@if(in_array($field['name'],['amount','fee']))</span>@endif
@if($capitalName && $field['name']==='name')<small class="muted">Gunakan huruf kapital untuk seluruh nama, contoh: BUDI SANTOSO.</small><small class="error-text" data-capital-error aria-live="polite" hidden></small>@endif
@if($liveNominal && $field['name']==='amount')<small id="nominal-help" class="muted">Masukkan nominal bulat dari Rp0 sampai Rp1.000.000.000.</small><small id="nominal-error" class="error-text" aria-live="polite" hidden></small>@endif
@endif @error($field['name'])<small class="error-text">{{ $message }}</small>@enderror</label>
@endforeach</div>
