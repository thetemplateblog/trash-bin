@extends('statamic::layout')
@section('title', __('Trash Bin'))

@section('content')
    <div class="flex items-center mb-3">
        <h1 class="flex-1">{{ __('Trash Bin') }}</h1>
    </div>

    <div class="card p-0">
        <div class="data-list">

            <div class="data-list-body">
                @if($trashedItems->isEmpty())
                    <div class="p-3 text-center text-gray-500">
                        <p>{{ __('No items found in the Trash Bin') }}</p>
                    </div>
                @else
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('Collection') }}</th>
                                <th>{{ __('Deleted') }}</th>
                                <th class="actions-column"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($trashedItems as $item)
                                <tr>
                                    <td>
                                    <a href="{{ $item['url'] }}" class="flex items-center group hover:text-blue-600 transition duration-150 ease-in-out">
                                        <div class="w-1.5 h-1.5 rounded-full mr-2 bg-{{ $item['type'] === 'entries' ? 'green-600' : 'blue-600' }}"></div>
                                        <span class="group-hover:underline">{{ $item['title'] }}</span>
                                    </a>

                                    </td>
                                    <td>{{ $item['collection'] }}</td>
                                    <td>{{ $item['formatted_date'] }}</td>
                                    <td class="text-right">
                                    <div class="flex justify-end">
                                        <a href="{{ $item['url'] }}" class="text-blue hover:text-blue-800 mr-2">
                                            {{ __('View') }}
                                        </a>

                                        <form method="POST" action="{{ $item['restoreUrl'] }}" class="mr-2">
                                            @csrf
                                            <button type="submit" class="text-green-500 hover:text-green-700">
                                                {{ __('Restore') }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ cp_route('trash-bin.destroy', ['type' => $item['type'], 'id' => $item['id']]) }}" 
                                            onsubmit="return confirm('{{ __('Are you sure you want to permanently delete this item?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-500 hover:text-red-700 focus:outline-none transition duration-150 ease-in-out">
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
@endsection
