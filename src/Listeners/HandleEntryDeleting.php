<?php

namespace TheTemplateBlog\TrashBin\Listeners;

use TheTemplateBlog\TrashBin\Services\TrashManager;
use Statamic\Events\EntryDeleting;

class HandleEntryDeleting
{
    protected $trashManager;

    /**
     * Constructor
     * 
     * @param TrashManager $trashManager The trash manager service
     */
    public function __construct(TrashManager $trashManager)
    {
        $this->trashManager = $trashManager;
    }

    /**
     * Handle the entry deleting event
     * Moves the entry to trash before it's deleted
     * 
     * @param EntryDeleting $event The entry deleting event
     * @throws \Exception if moving to trash fails
     */
    public function handle(EntryDeleting $event)
    {
        $entry = $event->entry;
        
        $this->trashManager->moveToTrash('entries', [
            'id' => $entry->id(),
            'collection' => $entry->collection()->handle(),
            'path' => $entry->path(),
            'slug' => $entry->slug(),
            'filename' => basename($entry->path()),
            'data' => $entry->data()->all()
        ]);
    }
}
