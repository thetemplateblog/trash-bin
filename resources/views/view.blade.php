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
        <a href="{{ cp_route('trash-bin.index') }}" class="btn">
            {{ __('Back to Trash Bin') }}
        </a>
    </div>
@endsection
