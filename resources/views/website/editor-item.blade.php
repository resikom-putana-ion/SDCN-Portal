<details class="cms-item" data-cms-item>
    <summary>@if($listPath==='people')@include('website.portrait', ['person'=>$entry])@else<span class="cms-item-number">{{ $itemIndex+1 }}</span>@endif<div><strong>{{ is_array($entry) ? ($entry['name'] ?? $entry['title'] ?? $entry['question'] ?? 'Item '.($itemIndex+1)) : (is_int($entry) ? ($data['people'][$entry]['name'] ?? 'Pilih anggota') : $entry) }}</strong>@if(is_array($entry) && isset($entry['role']))<small>{{ $entry['role'] }}</small>@endif</div><span class="cms-item-edit">Edit</span></summary>
    <div class="cms-item-body">
        @include('website.editor-node', ['node'=>$entry,'path'=>$itemPath])
        @if(\App\Services\WebsiteEditor::editableList($section,$listPath))
        <div class="cms-item-actions"><button name="operation" value="up:{{ $listPath }}:{{ $itemIndex }}" @disabled($itemIndex===0) aria-label="Pindahkan item {{ $itemIndex+1 }} ke atas">↑ Naik</button><button name="operation" value="down:{{ $listPath }}:{{ $itemIndex }}" @disabled($itemIndex===$itemCount-1) aria-label="Pindahkan item {{ $itemIndex+1 }} ke bawah">↓ Turun</button><button class="cms-remove" name="operation" value="remove:{{ $listPath }}:{{ $itemIndex }}">Hapus</button></div>
        @endif
    </div>
</details>
