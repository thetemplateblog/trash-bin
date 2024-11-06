<?php

namespace TheTemplateBlog\TrashBin\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
            'trashedItems' => collect($transformed), // Ensure it's a collection
            'columns' => [
                ['label' => __('Title'), 'field' => 'title', 'visible' => true],
                ['label' => __('Collection'), 'field' => 'collection', 'visible' => true],
                ['label' => __('Deleted'), 'field' => 'deleted_at', 'visible' => true],
            ],
        ]);
    }    

    /**
     * View a specific trashed item
     */
    public function view(Request $request, string $type, string $id)
    {
        $this->authorize('view trash-bin-item');

        if (!$item = $this->trashManager->getTrashedItem($type, $id)) {
            return $this->redirectWithError('trash-bin.index', __('Item not found.'));
        }

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
     * Restore an item from trash
     */
    public function restore(string $type, string $id): void
    {
        $trashPath = $this->getPath($type, $id);
        $metadata = $this->getMetadata($type, $id);

        if (!File::exists($trashPath)) {
            throw new \Exception("Trashed file not found");
        }

        // The collection is directly in the metadata
        $collection = $metadata['collection'] ?? null;

        if (empty($collection)) {
            throw new \Exception("No collection information found");
        }

        // Use the original_path from metadata
        $originalPath = $metadata['original_path'] ?? null;
        
        if (!$originalPath) {
            throw new \Exception("No original path found");
        }

        $restorePath = File::exists($originalPath)
            ? $this->getUniqueRestorePath($originalPath)
            : $originalPath;

        // Ensure directory exists
        $this->ensureDirectoryExists(dirname($restorePath));

        // Move file back
        File::move($trashPath, $restorePath);

        // Clean up metadata
        File::delete($this->getMetadataPath($type, $id));

        Log::info('Item restored', [
            'from' => $trashPath,
            'to' => $restorePath,
            'collection' => $collection
        ]);
    }

    /**
     * Permanently delete an item
     */
    public function permanentlyDelete(string $type, string $id): void
    {
        \Log::debug('Permanently deleting item', [
            'type' => $type,
            'id' => $id,
        ]);
    
        $trashPath = $this->getPath($type, $id);
        
        if (File::exists($trashPath)) {
            
            try {
                File::delete($trashPath);
                $this->deleteMetadata($type, $id);
            } catch (\Exception $e) {
                \Log::error('Error deleting file', [
                    'path' => $trashPath,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            \Log::debug('File not found', ['path' => $trashPath]);
        }
    }

    /**
     * Permanently delete item
     */
    public function destroy(Request $request, string $type, string $id)
    {
        $this->authorize('delete trash-bin-item');
    
        \Log::debug('Deleting trashed item', [
            'type' => $type,
            'id' => $id,
        ]);
    
        return $this->handleAction(
            fn() => $this->trashManager->permanentlyDelete($type, $id),
            __('Item permanently deleted.'),
            __('Failed to delete item.'),
            $request->wantsJson()
        );
    }
    

    /**
     * Handle bulk actions
     */
    public function bulkAction(Request $request)
    {
        $this->validateRequest($request, [
            'action' => 'required|in:restore,delete',
            'items' => 'required|array',
            'items.*.type' => 'required|string|regex:/^[a-zA-Z0-9-_]+$/',
            'items.*.id' => 'required|string|regex:/^[a-zA-Z0-9-_]+$/',
        ]);
    
        // Verify permissions based on action
        if ($request->input('action') === 'restore') {
            $this->authorize('restore trash-bin-item');
        } else {
            $this->authorize('delete trash-bin-item');
        }
    
        $results = $this->processBulkAction(
            $request->input('action'),
            $request->input('items', [])
        );
    
        if ($request->wantsJson()) {
            return response()->json($results);
        }
    
        return back()->with('success', $this->getBulkActionMessage(
            $request->input('action'),
            $results
        ));
    }
    

    /**
     * Transform items for display
     */
    protected function transformItems($items)
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
        })->values()->all(); // Ensure we have a plain array
    }    

    /**
     * Get available actions for an item
     */
    protected function getActions($item): array
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

    /**
     * Get breadcrumbs for item view
     */
    protected function getBreadcrumbs(array $item): array
    {
        return [
            ['text' => __('Trash Bin'), 'url' => cp_route('trash-bin.index')],
            ['text' => $item['title'] ?? __('View Item')],
        ];
    }

    /**
     * Process bulk actions
     */
    protected function processBulkAction(string $action, array $items): array
    {
        $results = ['succeeded' => [], 'failed' => []];

        foreach ($items as $item) {
            try {
                match ($action) {
                    'restore' => $this->trashManager->restore($item['type'], $item['id']),
                    'delete' => $this->trashManager->permanentlyDelete($item['type'], $item['id']),
                };
                $results['succeeded'][] = $item;
            } catch (\Exception $e) {
                $results['failed'][] = array_merge($item, ['error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    /**
     * Return JSON response for index
     */
    protected function jsonIndex($items): JsonResponse
    {
        return response()->json([
            'meta' => [
                'columns' => [
                    ['label' => __('Title'), 'field' => 'title'],
                    ['label' => __('Collection'), 'field' => 'collection'],
                    ['label' => __('Deleted'), 'field' => 'deleted_at'],
                ],
            ],
            'data' => $items,
        ]);
    }

    /**
     * Return view response for index
     */
    protected function viewIndex($items)
    {
        return view('trash-bin::index', [
            'title' => __('Trash Bin'),
            'trashedItems' => $items,
            'columns' => [
                ['label' => __('Title'), 'field' => 'title', 'visible' => true],
                ['label' => __('Collection'), 'field' => 'collection', 'visible' => true],
                ['label' => __('Deleted'), 'field' => 'deleted_at', 'visible' => true],
            ],
            'filters' => $this->trashManager->getFilters(),
        ]);
    }

    /**
     * Handle common action pattern
     */
    protected function handleAction(callable $action, string $successMessage, string $errorMessage, bool $wantsJson = false)
    {
        try {
            $result = $action();

            if ($wantsJson) {
                return response()->json([
                    'message' => $successMessage,
                    'data' => $result,
                ]);
            }

            return $this->redirectWithSuccess(null, $successMessage);
        } catch (\Exception $e) {
            if ($wantsJson) {
                return response()->json([
                    'message' => $errorMessage,
                    'error' => $e->getMessage(),
                ], 422);
            }

            return $this->redirectWithError(null, $errorMessage . ' ' . $e->getMessage());
        }
    }

    /**
     * Get message for bulk action results
     */
    protected function getBulkActionMessage(string $action, array $results): string
    {
        $successCount = count($results['succeeded']);
        $failureCount = count($results['failed']);

        if ($failureCount === 0) {
            return __('Successfully :action :count items.', [
                'action' => $action === 'restore' ? 'restored' : 'deleted',
                'count' => $successCount,
            ]);
        }

        return __(':success items :action, :failed items failed.', [
            'success' => $successCount,
            'action' => $action === 'restore' ? 'restored' : 'deleted',
            'failed' => $failureCount,
        ]);
    }

    /**
     * Validate request with given rules
     * 
     * @throws ValidationException
     */
    protected function validateRequest(Request $request, array $rules): void
    {
        $validator = validator($request->all(), $rules);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                throw ValidationException::withMessages($validator->errors()->toArray());
            }

            $validator->validate();
        }
    }

    /**
     * Redirect with success message
     */
    protected function redirectWithSuccess(?string $route = null, string $message): RedirectResponse
    {
        return $this->returnResponse($route, ['success' => $message]);
    }

    /**
     * Redirect with error message
     */
    protected function redirectWithError(?string $route = null, string $message): RedirectResponse
    {
        return $this->returnResponse($route, ['error' => $message]);
    }

    /**
     * Return response with flash message
     */
    protected function returnResponse(?string $route, array $with): RedirectResponse
    {
        return $route
            ? redirect()->route($route)->with($with)
            : back()->with($with);
    }
}
