<?php

namespace TheTemplateBlog\TrashBin\Services;

use Statamic\Facades\{File, YAML, Entry, User, Path, Collection as CollectionFacade};
use Carbon\Carbon;
use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\Log;

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
     * Get a trashed item with enhanced metadata
     */
    public function getTrashedItem(string $type, string $id): ?array
    {
        $path = $this->getPath($type, $id);
        
        if (!File::exists($path)) {
            return null;
        }

        $content = YAML::parse(File::get($path));
        $metadata = $this->getMetadata($type, $id);
        
        return array_merge($content, [
            'id' => $id,
            'type' => $type,
            'title' => $content['title'] ?? 'Untitled',
            'collection' => $metadata['collection'] ?? null,
            'deleted_at' => $metadata['deleted_at'] ?? File::lastModified($path),
            'author' => $this->resolveUsername($content['author'] ?? null),
            'updated_by' => $this->resolveUsername($content['updated_by'] ?? null),
            'metadata' => $metadata,
        ]);
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
                return [
                    'id' => pathinfo($file, PATHINFO_FILENAME),
                    'type' => $pathParts[count($pathParts) - 2], // Get the parent directory name
                    'deleted_at' => File::lastModified($file),
                    'formatted_date' => Carbon::createFromTimestamp(File::lastModified($file))->diffForHumans(),
                ];
            })
            ->sortByDesc('deleted_at')
            ->values();
    }
    
    private function getTrashFiles(): array
    {
        $files = [];
        $trashTypes = File::getFiles($this->trashRoot);
        
        foreach ($trashTypes as $typeDir) {
            if (File::isDirectory($typeDir)) {
                $typeFiles = File::getFiles($typeDir);
                $files = array_merge($files, array_filter($typeFiles, fn($file) => Str::endsWith($file, '.md')));
            }
        }
        
        return $files;
    }
    
    // Add this method for debugging
    private function debug()
    {
        \Log::info('Trash root: ' . $this->trashRoot);
        \Log::info('Trash contents: ', File::getFiles($this->trashRoot));
        
        $files = $this->getTrashFiles();
        \Log::info('Trash files found:', $files);
    }
    
    private function formatDate($timestamp): string
    {
        return Carbon::createFromTimestamp($timestamp)->diffForHumans();
    }
    
    /**
     * Move an item to trash
     */
    public function moveToTrash(string $type, string $id, array $metadata = []): void
    {
        $originalPath = $metadata['path'] ?? '';
        if (!File::exists($originalPath)) {
            throw new \Exception("Original file not found: {$originalPath}");
        }
    
        // Ensure the trash directory for this type exists
        $trashTypePath = Path::tidy($this->trashRoot . '/' . $type);
        if (!File::exists($trashTypePath)) {
            File::makeDirectory($trashTypePath, 0755, true);
        }
    
        // Use the original filename
        $originalFilename = basename($originalPath);
        $trashedFilename = $this->getUniqueFilename($trashTypePath, $originalFilename);
    
        // Full path for the trashed file
        $trashedPath = Path::tidy($trashTypePath . '/' . $trashedFilename);
    
        // Move the file
        File::move($originalPath, $trashedPath);
    
        // Save metadata
        $metadataPath = Path::tidy($trashTypePath . '/' . pathinfo($trashedFilename, PATHINFO_FILENAME) . '.meta.yaml');
        $metadataContent = array_merge($metadata, [
            'original_filename' => $originalFilename, // Store original filename in metadata
            'id' => $id,
            'type' => $type,
            'original_path' => $originalPath,
            'deleted_at' => Carbon::now()->timestamp,
        ]);
        File::put($metadataPath, YAML::dump($metadataContent));
    
        Log::info("File moved to trash", [
            'original_filename' => $originalFilename,
            'trashed_filename' => $trashedFilename,
            'from' => $originalPath,
            'to' => $trashedPath
        ]);
    }    

    private function getUniqueFilename(string $directory, string $filename): string
    {
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $newFilename = $filename;
        $counter = 1;
    
        while (File::exists(Path::tidy($directory . '/' . $newFilename))) {
            $newFilename = $basename . '-' . $counter . '.' . $extension;
            $counter++;
        }
    
        return $newFilename;
    }

    /**
     * Generate a unique path for the trashed file
     */
    protected function getUniqueTrashPath(string $type, string $filename): string
    {
        $basePath = Path::tidy($this->trashRoot . '/' . $type);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $baseFilename = pathinfo($filename, PATHINFO_FILENAME);
        
        $path = Path::tidy($basePath . '/' . $filename);

        // If file doesn't exist, use original filename
        if (!File::exists($path)) {
            return $path;
        }

        // If it exists, start adding incremental numbers with hyphens
        $count = 1;
        while (File::exists($path)) {
            $newFilename = $baseFilename . '-' . $count . '.' . $extension;
            $path = Path::tidy($basePath . '/' . $newFilename);
            $count++;
        }

        return $path;
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

    /**
     * Permanently delete an item
     */
    public function permanentlyDelete(string $type, string $id): void
    {
        $trashPath = $this->getPath($type, $id);
        
        if (File::exists($trashPath)) {
            File::delete($trashPath);
            $this->deleteMetadata($type, $id);
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

    protected function getMetadata(string $type, string $id): array
    {
        $path = $this->getPath($type, $id, true);
        
        if (!File::exists($path)) {
            return [];
        }

        try {
            return json_decode(File::get($path), true) ?? [];
        } catch (\Exception $e) {
            \Log::error('Error reading metadata', [
                'type' => $type,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Save metadata for a trashed item
     */
    protected function saveMetadata(string $type, string $id, array $data): void
    {
        $path = $this->getMetadataPath($type, $id);
        
        $metadata = array_merge([
            'id' => $id,
            'type' => $type,
            'deleted_at' => Carbon::now()->timestamp,
            'version' => '1.0'
        ], $data);

        File::put($path, YAML::dump($metadata));
    }

    /**
     * Get metadata path for a trashed item
     */
    protected function getMetadataPath(string $type, string $id): string
    {
        return Path::tidy($this->trashRoot . '/' . $type . '/' . $id . '.meta.yaml');
    }

    protected function deleteMetadata(string $type, string $id): void
    {
        $path = $this->getPath($type, $id, true);
        File::delete($path);
    }

    protected function getOriginalEntryPath(string $collection, string $id): string
    {
        return Path::tidy("content/collections/{$collection}/{$id}.md");
    }

    /**
     * Check if type is enabled
     */
    protected function isTypeEnabled(string $type): bool
    {
        return isset($this->config['enabled_types'][$type]) 
            && $this->config['enabled_types'][$type] === true;
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
     * Get the path of a trashed file
     */
    protected function getPath(string $type, string $id): string
    {
        $metadata = $this->getMetadata($type, $id);
        
        // Use stored filename if available, fallback to id.md
        $filename = $metadata['filename'] ?? ($id . '.md');
        
        return Path::tidy($this->trashRoot . '/' . $type . '/' . $filename);
    }

    protected function resolveUsername(?string $userId): string
    {
        if (!$userId) {
            return 'Unknown User';
        }

        return User::find($userId)?->name() ?? 'Unknown User';
    }

    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    protected function getDirectorySize(string $path): int
    {
        $size = 0;
        foreach (File::allFiles($path) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    protected function getTrashStats(): array
    {
        $totalSize = $this->getDirectorySize($this->trashRoot);
        $itemCount = $this->getTrashedItems()->count();

        return [
            'size' => $this->formatFileSize($totalSize),
            'count' => $itemCount,
            'types' => collect($this->config['enabled_types'])->filter()->count(),
            'approaching_limit' => $this->isApproachingSizeLimit(),
            'last_purge' => $this->getLastPurgeDate(),
        ];
    }

    protected function isApproachingSizeLimit(): bool
    {
        $maxSize = $this->config['max_size'] ?? 1024 * 1024 * 1024; // 1GB default
        $currentSize = $this->getDirectorySize($this->trashRoot);
        return $currentSize > ($maxSize * 0.9); // 90% of max size
    }

    protected function getLastPurgeDate(): ?string
    {
        $path = Path::tidy("{$this->trashRoot}/last_purge.txt");
        return File::exists($path) ? File::get($path) : null;
    }

    protected function updateLastPurgeDate(): void
    {
        $path = Path::tidy("{$this->trashRoot}/last_purge.txt");
        File::put($path, now()->toIso8601String());
    }
}
