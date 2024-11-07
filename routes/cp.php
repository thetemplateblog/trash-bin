<?php

use Illuminate\Support\Facades\Route;
use TheTemplateBlog\TrashBin\Http\Controllers\TrashController;

Route::prefix('trash-bin')->name('trash-bin.')->group(function () {
    
    // View the Trash Bin (index)
    Route::get('/', [TrashController::class, 'index'])
        ->name('index')
        ->middleware('can:view trash-bin');  // Ensure only those with permission can view

    Route::post('/bulk-action', [TrashController::class, 'bulkAction'])
        ->name('bulk-action')
        ->middleware('can:view trash-bin');

    // View action (view but keep in trash)
    Route::get('view/{type}/{filename}', [TrashController::class, 'view'])
        ->name('view')
        ->middleware('can:view trash-bin-item')  // Require permission to view trash-bin items
        ->where('type', '[a-zA-Z0-9-_]+')  // Validate type parameter (UUID or similar)
        ->where('filename', '[a-zA-Z0-9-_]+');  // Validate filename (string/number/UUID)

    // Soft delete action (delete but keep in trash)
    Route::delete('/{type}/{filename}', [TrashController::class, 'softDelete'])
        ->name('soft-delete')
        ->middleware('can:delete trash-bin-item')  // Require permission to delete trash-bin items
        ->where('type', '[a-zA-Z0-9-_]+')  // Validate type parameter (UUID or similar)
        ->where('filename', '[a-zA-Z0-9-_]+');  // Validate filename (string/number/UUID)

    // Restore a soft-deleted entry
    Route::post('/{type}/{filename}/restore', [TrashController::class, 'restore'])
        ->name('restore')
        ->middleware('can:restore trash-bin-item')  // Require restore permission
        ->where('type', '[a-zA-Z0-9-_]+')
        ->where('filename', '[a-zA-Z0-9-_]+');

    // Permanently delete a soft-deleted entry (destroy)
    Route::delete('/{type}/{filename}/permanent', [TrashController::class, 'destroy'])
        ->name('destroy')
        ->middleware('can:delete trash-bin-item')  // Require delete permission for permanent delete
        ->where('type', '[a-zA-Z0-9-_]+')
        ->where('filename', '[a-zA-Z0-9-_]+');
});
