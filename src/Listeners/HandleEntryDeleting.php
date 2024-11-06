<?php

namespace TheTemplateBlog\TrashBin\Listeners;

use TheTemplateBlog\TrashBin\Services\TrashManager;
use Illuminate\Support\Facades\Log;
use Statamic\Events\EntryDeleting;

class HandleEntryDeleting
{
    protected $trashManager;

    public function __construct(TrashManager $trashManager)
    {
        $this->trashManager = $trashManager;
    }

    public function handle(EntryDeleting $event)
    {
        Log::info('HandleEntryDeleting triggered', [
            'entry_id' => $event->entry->id(),
            'collection' => $event->entry->collection()->handle(),
            'path' => $event->entry->path()
        ]);

        try {
            $this->trashManager->moveToTrash('entries', $event->entry->id(), [
                'collection' => $event->entry->collection()->handle(),
                'entry' => $event->entry->fileData(),
                'path' => $event->entry->path(),
                'filename' => basename($event->entry->path())
            ]);

            Log::info('Entry moved to trash successfully', [
                'entry_id' => $event->entry->id(),
                'collection' => $event->entry->collection()->handle()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to move entry to trash: ' . $e->getMessage(), [
                'exception' => $e,
                'entry_id' => $event->entry->id(),
                'collection' => $event->entry->collection()->handle(),
                'path' => $event->entry->path()
            ]);
            
            throw $e; // Re-throw to ensure proper error handling
        }
    }
}
