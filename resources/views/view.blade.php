@extends('statamic::layout')

@section('title', $title)

@section('content')
    <header class="mb-3">
        @include('statamic::partials.breadcrumb', ['url' => cp_route('trash-bin.index'), 'title' => __('Trash Bin')])
        <h1>{{ $title }}</h1>
    </header>

    <div class="card p-4 mb-4">
        <h2 class="mb-2 text-lg font-bold">{{ __('Item Details') }}</h2>
        <div class="metadata">
            <p><strong>{{ __('ID') }}:</strong> {{ $trashedItem['id'] ?? 'Unknown' }}</p>
            <p><strong>{{ __('Type') }}:</strong> {{ $trashedItem['type'] ?? 'Unknown' }}</p>
            <p><strong>{{ __('Collection') }}:</strong> {{ $trashedItem['collection'] ?? 'N/A' }}</p>
            <p><strong>{{ __('Deleted At') }}:</strong> {{ $trashedItem['deleted_at'] ? \Carbon\Carbon::createFromTimestamp($trashedItem['deleted_at'])->format('Y-m-d H:i:s') : 'Unknown' }}</p>
        </div>
    </div>

    @if(isset($trashedItem['metadata']['entry']['content']))
        <div class="card p-4 mb-4">
            <h2 class="mb-2 text-lg font-bold">{{ __('Content') }}</h2>
            <div class="content">
                {!! Statamic\Facades\Markdown::parse($trashedItem['metadata']['entry']['content']) !!}
            </div>
        </div>
    @endif

    <div class="flex justify-between mt-2">
        <a href="{{ cp_route('trash-bin.index') }}" class="text-gray-700 hover:text-gray-900 mr-2">
            {{ __('Back to Trash Bin') }}
        </a>
        
        <div class="flex space-x-4">
            <form method="POST" action="{{ cp_route('trash-bin.restore', ['type' => $trashedItem['type'], 'id' => $trashedItem['id']]) }}">
                @csrf
                <button type="submit" class="text-green-500 hover:text-green-700 focus:outline-none transition duration-150 ease-in-out">
                    {{ __('Restore') }}
                </button>
            </form>
            
            <form method="POST" action="{{ cp_route('trash-bin.destroy', ['type' => $trashedItem['type'], 'id' => $trashedItem['id']]) }}" 
                onsubmit="return confirm('{{ __('Are you sure you want to permanently delete this item?') }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-red-500 hover:text-red-700 focus:outline-none transition duration-150 ease-in-out">
                    {{ __('Delete Permanently') }}
                </button>
            </form>
        </div>
    </div>
@endsection
