@props(['fields','values'=>[]])
<div class="form-grid">@foreach($fields as $field)@php
$value=old($field['name'],$values[$field['name']] ?? $field['value']);
@endphp
<label class="field {{ $field['type']==='textarea'?'wide':'' }}"><span>{{ __($field['label']) }}</span>
@if(str_starts_with($field['name'],'image'))<select name="{{ $field['name'] }}">@foreach(collect(['classroom.jpeg','logo.png','teacher.png'])->merge(\App\Models\SchoolRecord::ofKind('media')->get()->map(fn($m)=>$m->value('image')))->push($value)->unique() as $image)<option value="{{ $image }}" @selected($image===$value)>{{ $image }}</option>@endforeach</select>
@elseif($field['type']==='textarea')<textarea name="{{ $field['name'] }}" rows="4">{{ $value }}</textarea>
@elseif($field['type']==='select')<select name="{{ $field['name'] }}">@foreach($field['options'] as $option)<option @selected($value==$option)>{{ $option }}</option>@endforeach</select>
@elseif($field['type']==='student')<select name="{{ $field['name'] }}" required>@foreach(\App\Models\SchoolRecord::ofKind('students')->get() as $student)<option value="{{ $student->id }}" @selected($value==$student->id)>{{ $student->value('name') }} · {{ $student->value('class') }}</option>@endforeach</select>
@else<input type="{{ $field['type'] }}" name="{{ $field['name'] }}" value="{{ $value }}" @if($field['type']==='number') min="0" step="1" @endif @if(!in_array($field['name'],['notes','father','summary','previous_school'])) required @endif>
@endif @error($field['name'])<small class="error-text">{{ $message }}</small>@enderror</label>
@endforeach</div>
