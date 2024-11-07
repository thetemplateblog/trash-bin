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
                $filename = pathinfo($file, PATHINFO_BASENAME);
                
                // Get metadata
                $metadata = $this->getMetadata($type, $filename);
                
                // Get content
                $content = YAML::parse(File::get($file));
                
                return [
                    'filename' => $filename,
                    'type' => $type,
                    'title' => $content['title'] ?? 'Untitled',
                    'collection' => $metadata['collection'] ?? null,
                    'deleted_at' => $metadata['deleted_at'] ?? File::lastModified($file),
                    'formatted_date' => Carbon::createFromTimestamp($metadata['deleted_at'] ?? File::lastModified($file))->diffForHumans(),
                ];
            })
            ->filter()
            ->sortByDesc('deleted_at')
            ->values();
    }

    /**
     * Get a single trashed item
     */
    public function getTrashedItem(string $type, string $filename): ?array
    {
        $file = $this->getPath($type, $filename);
        
        if (!File::exists($file)) {
            return null;
        }

        // Get metadata
        $metadata = $this->getMetadata($type, $filename);
        
        // Get content
        $content = YAML::parse(File::get($file));
        
        return [
            'filename' => $filename,
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
    public function restore(string $type, string $filename): void
    {
        $trashPath = $this->getPath($type, $filename);
        $metadata = $this->getMetadata($type, $filename);

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
        File::delete($this->getMetadataPath($type, $filename));
    }

    /**
     * Move an item to trash
     */
    public function moveToTrash(string $type, array $metadata): void
    {
        // Extract file path from metadata
        $filePath = $metadata['path'];
    
        if (!File::exists($filePath)) {
            throw new \Exception("Original file not found: {$filePath}");
        }
    
        $filename = basename($filePath);
        $trashPath = $this->trashRoot . '/' . $type . '/' . $filename;
        $this->ensureDirectoryExists(dirname($trashPath));
    
        // Move the file to trash
        File::copy($filePath, $trashPath);
    
        // Add additional metadata
        $metadata = array_merge($metadata, [
            'deleted_at' => Carbon::now()->timestamp,
            'collection' => $metadata['collection'],
        ]);
    
        // Write the metadata to a YAML file
        $metadataPath = $this->getMetadataPath($type, $filename);
        File::put($metadataPath, YAML::dump($metadata));
    }

    /**
     * Get the path of a trashed file
     */
    protected function getPath(string $type, string $filename): string
    {
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
                $filename = pathinfo($file, PATHINFO_BASENAME);
                $metaPath = $this->getMetadataPath($type, $filename);
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
    protected function getMetadata(string $type, string $filename): array
    {
        $metadataPath = $this->getMetadataPath($type, $filename);
        
        if (!File::exists($metadataPath)) {
            return [];
        }

        try {
            return YAML::parse(File::get($metadataPath)) ?? [];
        } catch (\Exception $e) {
            Log::error('Error reading metadata', [
                'type' => $type,
                'filename' => $filename,
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
