<?php

namespace TheTemplateBlog\TrashBin\Services;

use Statamic\Facades\{YAML, Entry, User, Path, Collection as CollectionFacade};
use Illuminate\Support\Facades\{File, Log};
use Carbon\Carbon;
use Illuminate\Support\{Collection, Str};

class TrashManager
{
    protected $config;
    protected $trashRoot;

    public function __construct()
    {
        $this->config = config('trash-bin');
        $this->trashRoot = $this->config['paths']['trash_folder'];
    }

    /**
     * Get all trashed items
     */
    public function getTrashedItems(int $limit = 50): Collection
    {
        return collect($this->getTrashFiles())
            ->take($limit)
            ->map(function ($file) {
                $pathParts = explode('/', $file);
                $type = $pathParts[count($pathParts) - 2]; // Get the parent directory name
                $id = pathinfo($file, PATHINFO_FILENAME);
                
                // Get metadata
                $metadata = $this->getMetadata($type, $id);
                
                // Get content
                $content = YAML::parse(File::get($file));
                
                return [
                    'id' => $id,
                    'type' => $type,
                    'title' => $content['title'] ?? 'Untitled',
                    'collection' => $metadata['collection'] ?? null,
                    'deleted_at' => $metadata['deleted_at'] ?? File::lastModified($file),
                    'formatted_date' => Carbon::createFromTimestamp($metadata['deleted_at'] ?? File::lastModified($file))->diffForHumans(),
                ];
            })
            ->sortByDesc('deleted_at')
            ->values();
    }

    /**
     * Get a single trashed item
     */
    public function getTrashedItem(string $type, string $id): ?array
    {
        $file = $this->getPath($type, $id);
        
        if (!File::exists($file)) {
            return null;
        }

        // Get metadata
        $metadata = $this->getMetadata($type, $id);
        
        // Get content
        $content = YAML::parse(File::get($file));
        
        return [
            'id' => $id,
            'type' => $type,
            'title' => $content['title'] ?? 'Untitled',
            'collection' => $metadata['collection'] ?? null,
            'deleted_at' => $metadata['deleted_at'] ?? File::lastModified($file),
            'formatted_date' => Carbon::createFromTimestamp($metadata['deleted_at'] ?? File::lastModified($file))->diffForHumans(),
            'content' => $content,
            'metadata' => $metadata,
        ];
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

        if (empty($metadata['collection'])) {
            throw new \Exception("No collection information found");
        }

        // Get original path or create new one if exists
        $restorePath = File::exists($metadata['original_path'])
            ? $this->getUniqueRestorePath($metadata['original_path'])
            : $metadata['original_path'];

        // Ensure directory exists
        $this->ensureDirectoryExists(dirname($restorePath));

        // Move file back
        File::move($trashPath, $restorePath);

        // Clean up metadata
        File::delete($this->getMetadataPath($type, $id));
    }

    /**
     * Move an item to trash
     */
    public function moveToTrash(string $type, string $originalPath): void
    {
        $filename = pathinfo($originalPath, PATHINFO_BASENAME);
        $trashPath = $this->trashRoot . '/' . $type . '/' . $filename;
        $this->ensureDirectoryExists(dirname($trashPath));

        // Move file to trash
        File::move($originalPath, $trashPath);

        // Create metadata
        $metadata = [
            'original_path' => $originalPath,
            'deleted_at' => Carbon::now()->timestamp,
            'collection' => $this->getCollectionFromPath($originalPath),
        ];

        File::put($this->getMetadataPath($type, $filename), YAML::dump($metadata));
    }

    /**
     * Get the path of a trashed file
     */
    protected function getPath(string $type, string $id): string
    {
        $metadata = $this->getMetadata($type, $id);
        
        // Use stored filename if available, fallback to id.md
        $filename = $metadata['original_filename'] ?? ($id . '.md');
        
        return $this->trashRoot . '/' . $type . '/' . $filename;
    }

    /**
     * Get all trash files
     */
    private function getTrashFiles(): array
    {
        if (!File::exists($this->trashRoot)) {
            File::makeDirectory($this->trashRoot, 0755, true);
            return [];
        }

        $files = [];
        
        // Get all enabled type directories
        foreach ($this->config['enabled_types'] as $type => $enabled) {
            if (!$enabled) continue;
            
            $typePath = $this->trashRoot . '/' . $type;
            
            if (!File::exists($typePath)) continue;
            
            // Get all .md files in this type directory
            $typeFiles = File::glob($typePath . '/*.md');
            
            // Only include files that have corresponding metadata
            $typeFiles = array_filter($typeFiles, function($file) use ($type) {
                $id = pathinfo($file, PATHINFO_FILENAME);
                $metaPath = $this->getMetadataPath($type, $id);
                return File::exists($metaPath);
            });
            
            $files = array_merge($files, $typeFiles);
        }
        
        return $files;
    }

    /**
     * Get metadata path for a trashed item
     */
    protected function getMetadataPath(string $type, string $filename): string
    {
        return $this->trashRoot . '/' . $type . '/' . $filename . '.meta.yaml';
    }

    /**
     * Get metadata for a trashed item
     */
    protected function getMetadata(string $type, string $id): array
    {
        $metadataPath = $this->getMetadataPath($type, $id);
        
        if (!File::exists($metadataPath)) {
            return [];
        }

        try {
            return YAML::parse(File::get($metadataPath)) ?? [];
        } catch (\Exception $e) {
            Log::error('Error reading metadata', [
                'type' => $type,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get filters configuration
     */
    public function getFilters(): array
    {
        return [
            'type' => [
                'display' => __('Type'),
                'options' => collect($this->config['enabled_types'])
                    ->filter()
                    ->keys()
                    ->mapWithKeys(fn($type) => [$type => __(ucfirst($type))])
                    ->all(),
            ],
            'collection' => [
                'display' => __('Collection'),
                'type' => 'select',
                'options' => $this->getAvailableCollections(),
            ],
            'date' => [
                'display' => __('Date'),
                'type' => 'select',
                'options' => [
                    'today' => __('Today'),
                    'week' => __('This Week'),
                    'month' => __('This Month'),
                    'older' => __('Older'),
                ],
            ],
        ];
    }

    /**
     * Get available collections
     */
    public function getAvailableCollections(): array
    {
        return $this->getTrashedItems()
            ->pluck('collection')
            ->filter()
            ->unique()
            ->mapWithKeys(function ($handle) {
                $collection = CollectionFacade::findByHandle($handle);
                return [$handle => $collection ? $collection->title() : ucfirst($handle)];
            })
            ->all();
    }

    /**
     * Get collection from path
     */
    protected function getCollectionFromPath(string $path): ?string
    {
        // Logic to determine collection from path
        return null; // Placeholder
    }

    /**
     * Ensure directory exists
     */
    protected function ensureDirectoryExists(string $path): void
    {
        $directory = dirname($path);
        
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    /**
     * Get unique restore path if original exists
     */
    protected function getUniqueRestorePath(string $originalPath): string
    {
        $extension = pathinfo($originalPath, PATHINFO_EXTENSION);
        $basename = pathinfo($originalPath, PATHINFO_FILENAME);
        $directory = dirname($originalPath);

        $count = 1;
        $newPath = $originalPath;

        while (File::exists($newPath)) {
            $newPath = $directory . '/' . $basename . '-' . $count . '.' . $extension;
            $count++;
        }

        return $newPath;
    }

    // ... (rest of the previous methods remain the same)
}
