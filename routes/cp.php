<?php

use Illuminate\Support\Facades\Route;
use TheTemplateBlog\TrashBin\Http\Controllers\TrashController;

Route::prefix('trash-bin')->name('trash-bin.')->group(function () {
    
    // View the Trash Bin (index)
    Route::get('/', [TrashController::class, 'index'])
        ->name('index')
        ->middleware('can:view trash-bin');

    // View action (view but keep in trash)
    Route::get('/{type}/{id}', [TrashController::class, 'view'])
        ->name('view')
        ->middleware('can:view trash-bin-item')
        ->where('type', '[a-zA-Z0-9-_]+')
        ->where('id', '[a-zA-Z0-9-]+');

    // Restore a soft-deleted entry
    Route::post('/{type}/{id}/restore', [TrashController::class, 'restore'])
        ->name('restore')
        ->middleware('can:restore trash-bin-item')
        ->where('type', '[a-zA-Z0-9-_]+')
        ->where('id', '[a-zA-Z0-9-]+');

    // Permanently delete a soft-deleted entry (destroy)
    Route::delete('/{type}/{id}/permanent', [TrashController::class, 'destroy'])
        ->name('destroy')
        ->middleware('can:delete trash-bin-item')
        ->where('type', '[a-zA-Z0-9-_]+')
        ->where('id', '[a-zA-Z0-9-]+');
});
