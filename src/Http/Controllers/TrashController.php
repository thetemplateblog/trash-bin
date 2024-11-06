<?php

namespace TheTemplateBlog\TrashBin\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Statamic\Http\Controllers\CP\CpController;
use TheTemplateBlog\TrashBin\Services\TrashManager;
use TheTemplateBlog\TrashBin\Http\Resources\TrashResource;

class TrashController extends CpController
{
    protected TrashManager $trashManager;

    public function __construct(Request $request, TrashManager $trashManager)
    {
        parent::__construct($request);
        $this->trashManager = $trashManager;
    }

    /**
     * Show the Trash Bin page
     */
    public function index(Request $request)
    {
        $this->authorize('view trash-bin');
        
        Log::info('Fetching trashed items');
        $items = $this->trashManager->getTrashedItems();
        Log::info('Got trashed items', ['count' => $items->count(), 'items' => $items->toArray()]);
        
        $transformed = $this->transformItems($items);
        Log::info('Transformed items', ['transformed' => $transformed]);
    
        if ($request->wantsJson()) {
            return response()->json([
                'meta' => [
                    'columns' => [
                        ['label' => __('Title'), 'field' => 'title'],
                        ['label' => __('Collection'), 'field' => 'collection'],
                        ['label' => __('Deleted'), 'field' => 'deleted_at'],
                    ],
                ],
                'data' => $transformed,
            ]);
        }
    
        return view('trash-bin::index', [
            'title' => __('Trash Bin'),
            'trashedItems' => collect($transformed), // Ensure it's a collection
            'columns' => [
                ['label' => __('Title'), 'field' => 'title', 'visible' => true],
                ['label' => __('Collection'), 'field' => 'collection', 'visible' => true],
                ['label' => __('Deleted'), 'field' => 'deleted_at', 'visible' => true],
            ],
            'filters' => $this->trashManager->getFilters(),
        ]);
    }    

    /**
     * View a specific trashed item
     */
    public function view(Request $request, string $type, string $id)
    {
        $this->authorize('view trash-bin-item');

        Log::info('Viewing trashed item', ['type' => $type, 'id' => $id]);
        if (!$item = $this->trashManager->getTrashedItem($type, $id)) {
            Log::error('Trashed item not found', ['type' => $type, 'id' => $id]);
            return $this->redirectWithError('trash-bin.index', __('Item not found.'));
        }

        Log::info('Found trashed item', ['item' => $item]);

        if ($request->wantsJson()) {
            return response()->json(['data' => new TrashResource($item)]);
        }

        return view('trash-bin::view', [
            'trashedItem' => $item,
            'title' => $item['title'] ?? __('View Trashed Item'),
            'breadcrumbs' => $this->getBreadcrumbs($item),
            'actions' => $this->getActions($item),
        ]);
    }

    /**
     * Transform items for display
     */
    protected function transformItems($items)
    {
        Log::info('Transforming items', ['count' => $items->count()]);
        
        $transformed = $items->map(function ($item) {
            Log::info('Transforming item', ['item' => $item]);
            
            $transformed = [
                'id' => $item['id'],
                'title' => $item['title'] ?? __('Untitled'),
                'type' => $item['type'],
                'collection' => $item['collection'] ?? null,
                'deleted_at' => $item['deleted_at'],
                'formatted_date' => Carbon::createFromTimestamp($item['deleted_at'])->diffForHumans(),
                'url' => cp_route('trash-bin.view', ['type' => $item['type'], 'id' => $item['id']]),
                'restoreUrl' => cp_route('trash-bin.restore', ['type' => $item['type'], 'id' => $item['id']]),
                'deleteUrl' => cp_route('trash-bin.destroy', ['type' => $item['type'], 'id' => $item['id']]),
            ];
            
            Log::info('Transformed item', ['transformed' => $transformed]);
            return $transformed;
        })->values()->all();
        
        Log::info('All items transformed', ['transformed' => $transformed]);
        return $transformed;
    }

    // ... (rest of the code unchanged)
}
