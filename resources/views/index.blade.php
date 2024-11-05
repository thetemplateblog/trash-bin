@extends('statamic::layout')
@section('title', __('Trash Bin'))

@section('content')
    <header class="mb-3">
        <h1>{{ __('Trash Bin') }}</h1>
    </header>

    @if($trashedItems->isEmpty())
        <div class="text-center text-gray-500 p-4">
            {{ __('No items found in the Trash Bin') }}
        </div>
    @else
        <div class="card p-0">
            <div class="data-list-header p-2 border-b">
                <div class="flex items-center">
                    <data-list-search class="flex-1"></data-list-search>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table w-full">
                    <thead>
                        <tr>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Collection') }}</th>
                            <th>{{ __('Deleted') }}</th>
                            <th class="actions-column text-center">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trashedItems as $item)
                            <tr>
                                <td>
                                    <div class="flex items-center">
                                        <div class="little-dot mr-1 bg-{{ $item['type'] === 'entries' ? 'green' : 'blue' }}"></div>
                                        <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                                    </div>
                                </td>
                                <td>{{ $item['collection'] }}</td>
                                <td>{{ $item['formatted_date'] }}</td>
                                <td class="text-right">
                                    <div class="btn-group flex space-x-2 justify-end">
                                        <!-- View Button -->
                                        <a href="{{ $item['url'] }}" class="btn btn-s btn-primary">{{ __('View') }}</a>

                                        <!-- Restore Button -->
                                        <form method="POST" action="{{ $item['restoreUrl'] }}" class="inline-block">
                                            @csrf
                                            <button type="submit" class="btn btn-s btn-success" onclick="return confirm('{{ __('Are you sure you want to restore this item?') }}')">
                                                {{ __('Restore') }}
                                            </button>
                                        </form>

                                        <!-- Delete Button -->
                                        <form method="POST" action="{{ $item['deleteUrl'] }}" class="inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-s btn-danger" onclick="return confirm('{{ __('Are you sure you want to permanently delete this item?') }}')">
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
