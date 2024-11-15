<?php

namespace TheTemplateBlog\TrashBin\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Statamic\Http\Controllers\CP\CpController;
use TheTemplateBlog\TrashBin\Services\TrashManager;

class TrashController extends CpController
{
    protected TrashManager $trashManager;

    /**
     * Constructor
     * 
     * @param Request $request The HTTP request
     * @param TrashManager $trashManager The trash manager service
     */
    public function __construct(Request $request, TrashManager $trashManager)
    {
        parent::__construct($request);
        $this->trashManager = $trashManager;
    }

    /**
     * Display the trash bin index page
     * Lists all trashed items with their details
     * 
     * @param Request $request The HTTP request
     * @return mixed View or JSON response
     */
    public function index(Request $request)
    {
        $this->authorize('view trash-bin');
        
        $items = $this->trashManager->getTrashedItems();
        $transformed = $this->transformItems($items);
    
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
            'trashedItems' => collect($transformed),
            'columns' => [
                ['label' => __('Title'), 'field' => 'title', 'visible' => true],
                ['label' => __('Collection'), 'field' => 'collection', 'visible' => true],
                ['label' => __('Deleted'), 'field' => 'deleted_at', 'visible' => true],
            ],
            'filters' => $this->trashManager->getFilters(),
        ]);
    }    

    /**
     * Display a specific trashed item
     * 
     * @param Request $request The HTTP request
     * @param string $type The type of item
     * @param string $id The item's ID
     * @return mixed View, JSON response, or redirect
     */
    public function view(Request $request, string $type, string $id)
    {
        $this->authorize('view trash-bin-item');

        if (!$item = $this->trashManager->getTrashedItem($type, $id)) {
            return redirect(cp_route('trash-bin.index'))
                ->with('error', __('Item not found.'));
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $item]);
        }

        return view('trash-bin::view', [
            'trashedItem' => $item,
            'title' => $item['title'] ?? __('View Trashed Item'),
            'breadcrumbs' => $this->getBreadcrumbs($item),
            'actions' => $this->getActions($item),
        ]);
    }

    /**
     * Restore a trashed item
     * 
     * @param Request $request The HTTP request
     * @param string $type The type of item
     * @param string $id The item's ID
     * @return RedirectResponse
     */
    public function restore(Request $request, string $type, string $id): RedirectResponse
    {
        $this->authorize('restore trash-bin-item');

        try {
            $this->trashManager->restore($type, $id);
            return redirect(cp_route('trash-bin.index'))
                ->with('success', __('Item restored successfully.'));
        } catch (\Exception $e) {
            return redirect(cp_route('trash-bin.index'))
                ->with('error', __('Failed to restore item.'));
        }
    }

    /**
     * Permanently delete a trashed item
     * 
     * @param Request $request The HTTP request
     * @param string $type The type of item
     * @param string $id The item's ID
     * @return mixed JSON response or redirect
     */
    public function destroy(Request $request, string $type, string $id)
    {
        $this->authorize('delete trash-bin-item');
        
        try {
            $this->trashManager->destroy($type, $id);
            
            if ($request->wantsJson()) {
                return response()->json(['message' => __('Item permanently deleted.')]);
            }
            
            return redirect(cp_route('trash-bin.index'))
                ->with('success', __('Item permanently deleted.'));
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => __('Failed to delete item.')], 500);
            }
            
            return redirect(cp_route('trash-bin.index'))
                ->with('error', __('Failed to delete item.'));
        }
    }

    /**
     * Transform items for display in the UI
     * Adds URLs and formats dates
     * 
     * @param mixed $items Collection of items to transform
     * @return array Transformed items
     */
    protected function transformItems($items): array
    {
        return $items->map(function ($item) {
            return [
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
        })->values()->all();
    }

    /**
     * Get breadcrumbs for item view
     * 
     * @param array $item The trashed item
     * @return array Breadcrumb data
     */
    protected function getBreadcrumbs(array $item): array
    {
        return [
            ['text' => __('Trash Bin'), 'url' => cp_route('trash-bin.index')],
            ['text' => $item['title'] ?? __('View Item')],
        ];
    }

    /**
     * Get available actions for an item
     * 
     * @param array $item The trashed item
     * @return array Available actions
     */
    protected function getActions(array $item): array
    {
        return [
            [
                'label' => __('View'),
                'icon' => 'eye',
                'url' => cp_route('trash-bin.view', ['type' => $item['type'], 'id' => $item['id']]),
                'permission' => 'view trash-bin-item',
            ],
            [
                'label' => __('Restore'),
                'icon' => 'refresh',
                'url' => cp_route('trash-bin.restore', ['type' => $item['type'], 'id' => $item['id']]),
                'method' => 'post',
                'permission' => 'restore trash-bin-item',
            ],
            [
                'label' => __('Delete'),
                'icon' => 'trash',
                'url' => cp_route('trash-bin.destroy', ['type' => $item['type'], 'id' => $item['id']]),
                'method' => 'delete',
                'permission' => 'delete trash-bin-item',
                'dangerous' => true,
                'confirm' => __('Are you sure you want to permanently delete this item?'),
            ],
        ];
    }
}
