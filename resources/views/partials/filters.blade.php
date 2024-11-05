<div class="flex items-center space-x-2">
    <select name="type" class="select-input" onchange="this.form.submit()">
        <option value="">{{ __('trash-bin::messages.all_types') }}</option>
        @foreach(config('trash-bin.enabled_types') as $type => $enabled)
            @if($enabled)
                <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>
                    {{ __(sprintf('trash-bin::messages.types.%s', $type)) }}
                </option>
            @endif
        @endforeach
    </select>

    <select name="sort" class="select-input" onchange="this.form.submit()">
        <option value="newest" {{ request('sort', 'newest') === 'newest' ? 'selected' : '' }}>
            {{ __('trash-bin::messages.sort_newest') }}
        </option>
        <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>
            {{ __('trash-bin::messages.sort_oldest') }}
        </option>
    </select>
</div>
