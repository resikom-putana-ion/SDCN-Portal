@if(is_array($node))
    @if(array_is_list($node))
        <div class="cms-list">
            <div class="cms-list-heading"><strong>{{ \App\Services\ContentLabels::label(collect(explode('.',$path))->last()) }}</strong><span>{{ count($node) }} item</span></div>
            @forelse($node as $index=>$entry)
                @include('website.editor-item', ['entry'=>$entry,'itemPath'=>$path.'.'.$index,'listPath'=>$path,'itemIndex'=>$index,'itemCount'=>count($node)])
            @empty<p class="cms-help">Belum ada item. Tambahkan untuk mulai mengisi bagian ini.</p>@endforelse
            @if(\App\Services\WebsiteEditor::editableList($section,$path))<button class="cms-add" name="operation" value="add:{{ $path }}">+ Tambah item</button>@endif
        </div>
    @else
        <div class="cms-field-group">
            @foreach($node as $property=>$entry)
                @include('website.editor-node', ['node'=>$entry,'path'=>$path.'.'.$property])
            @endforeach
        </div>
    @endif
@elseif(!str_ends_with($path,'.icon') && !str_ends_with($path,'.portrait'))
    @include('website.field', ['key'=>$path,'value'=>$node,'fieldIndex'=>array_search($path,array_keys($fields))])
@endif
