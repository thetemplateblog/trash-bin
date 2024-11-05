<?php

namespace TheTemplateBlog\TrashBin\Listeners;

use Statamic\Events\EntryDeleted;
use TheTemplateBlog\TrashBin\Services\TrashManager;
use Illuminate\Support\Facades\Log;

class HandleEntryDeleted
{
    protected $trashManager;

    public function __construct(TrashManager $trashManager)
    {
        $this->trashManager = $trashManager;
    }

    public function handle(EntryDeleted $event)
    {
        try {
            Log::info('HandleEntryDeleted triggered', [
                'entry_id' => $event->entry->id(),
                'collection' => $event->entry->collection()->handle(),
                'path' => $event->entry->path()
            ]);

            // Get the file content before it's deleted
            $entryData = $event->entry->fileData();
            
            $type = 'entries';
            $id = $event->entry->id();
            
            $this->trashManager->moveToTrash($type, $id, [
                'collection' => $event->entry->collection()->handle(),
                'entry' => $entryData,
                'path' => $event->entry->path()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to move entry to trash: ' . $e->getMessage(), [
                'exception' => $e,
                'entry_id' => $event->entry->id() ?? 'unknown'
            ]);
        }
    }
}
