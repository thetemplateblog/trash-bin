@php
    $iconClass = 'w-6 h-6';
    
    // Define the icon based on item type and subtype if available
    $icon = match($item['type']) {
        'entries' => [
            'name' => 'content-writing',
            'class' => $iconClass . ' text-blue-600'
        ],
        'assets' => [
            'name' => match($item['mime_type'] ?? '') {
                str_contains($item['mime_type'] ?? '', 'image/') => 'image',
                str_contains($item['mime_type'] ?? '', 'video/') => 'video',
                str_contains($item['mime_type'] ?? '', 'audio/') => 'audio',
                str_contains($item['mime_type'] ?? '', 'pdf') => 'pdf',
                str_contains($item['mime_type'] ?? '', 'spreadsheet') => 'spreadsheet',
                str_contains($item['mime_type'] ?? '', 'document') => 'document',
                default => 'assets'
            },
            'class' => $iconClass . ' text-green-600'
        ],
        'forms' => [
            'name' => 'form',
            'class' => $iconClass . ' text-purple-600'
        ],
        'users' => [
            'name' => 'users',
            'class' => $iconClass . ' text-orange-600'
        ],
        'taxonomies' => [
            'name' => 'tags',
            'class' => $iconClass . ' text-yellow-600'
        ],
        'globals' => [
            'name' => 'earth',
            'class' => $iconClass . ' text-teal-600'
        ],
        'navigation' => [
            'name' => 'hierarchy',
            'class' => $iconClass . ' text-indigo-600'
        ],
        default => [
            'name' => 'content',
            'class' => $iconClass . ' text-gray-600'
        ]
    };
@endphp

<div class="item-icon" title="{{ __(sprintf('trash-bin::messages.types.%s', $item['type'])) }}">
    @if(isset($item['thumbnail']) && $item['type'] === 'assets' && str_contains($item['mime_type'] ?? '', 'image/'))
        <img src="{{ $item['thumbnail'] }}" 
             alt="{{ $item['filename'] ?? '' }}" 
             class="w-6 h-6 object-cover rounded">
    @else
        <svg-icon name="{{ $icon['name'] }}" class="{{ $icon['class'] }}"></svg-icon>
    @endif
</div>

@once
    @push('styles')
    <style>
        .item-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
        }
        
        .item-icon img {
            max-width: 100%;
            height: auto;
        }
    </style>
    @endpush
@endonce
