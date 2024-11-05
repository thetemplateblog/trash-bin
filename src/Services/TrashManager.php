<?php

namespace TheTemplateBlog\TrashBin\Services;

use Statamic\Facades\{File, YAML, Entry, User, Path, Collection as CollectionFacade};
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
    public function getTrashedItems(): Collection
    {
        return collect($this->config['enabled_types'])
            ->filter()
            ->flatMap(function ($enabled, $type) {
                $typePath = Path::tidy($this->trashRoot . "/{$type}");
                
                if (!File::exists($typePath)) {
                    return collect();
                }

                return collect(File::getFiles($typePath))
                    ->filter(fn($file) => Str::endsWith($file, '.md'))
                    ->map(function ($file) use ($type) {
                        $id = pathinfo($file, PATHINFO_FILENAME);
                        $metadata = $this->getMetadata($type, $id);
                        
                        try {
                            $content = YAML::parse(File::get($file));
                            
                            return [
                                'type' => $type,
                                'id' => $id,
                                'title' => $content['title'] 
                                    ?? $metadata['entry']['title'] 
                                    ?? $id,
                                'deleted_at' => $metadata['deleted_at'] 
                                    ?? File::lastModified($file),
                                'collection' => $metadata['collection'] ?? null,
                                'path' => $file,
                                'metadata' => $metadata,
                                'content' => $content,
                            ];
                        } catch (\Exception $e) {
                            \Log::error('Error processing trashed item', [
                                'type' => $type,
                                'id' => $id,
                                'error' => $e->getMessage()
                            ]);
                            return null;
                        }
                    })
                    ->filter();
            })
            ->sortByDesc('deleted_at');
    }

    /**
     * Move an item to trash
     */
    public function moveToTrash(string $type, string $id, array $metadata = []): void
    {
        if (!$this->isTypeEnabled($type)) {
            throw new \Exception("Type {$type} is not enabled for trash bin");
        }

        $trashPath = $this->getPath($type, $id);
        $originalPath = $metadata['path'] ?? $this->getOriginalEntryPath($metadata['collection'], $id);

        $this->ensureDirectoryExists($trashPath);
        
        // Save content
        $content = $metadata['entry'] ?? [];
        File::put($trashPath, YAML::dump($content));

        // Save metadata
        $this->saveMetadata($type, $id, array_merge($metadata, [
            'original_path' => $originalPath,
            'deleted_at' => Carbon::now()->timestamp,
            'collection' => $metadata['collection'] ?? null
        ]));
    }

    /**
     * Restore an item from trash
     */
    public function restore(string $type, string $id): void
    {
        $trashPath = $this->getPath($type, $id);
        $metadata = $this->getMetadata($type, $id);
        
        if (!$metadata['original_path']) {
            throw new \Exception("Original path not found in metadata");
        }

        if (!File::exists($trashPath)) {
            throw new \Exception("File not found in trash");
        }

        $this->ensureDirectoryExists($metadata['original_path']);
        File::move($trashPath, $metadata['original_path']);
        $this->deleteMetadata($type, $id);
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

    /**
     * Helper Methods
     */
    protected function getPath(string $type, string $id, bool $withMeta = false): string
    {
        $path = Path::tidy("{$this->trashRoot}/{$type}/{$id}.md");
        return $withMeta ? $path . '.meta' : $path;
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

    protected function saveMetadata(string $type, string $id, array $data): void
    {
        $path = $this->getPath($type, $id, true);
        File::put($path, json_encode($data, JSON_PRETTY_PRINT));
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

    protected function isTypeEnabled(string $type): bool
    {
        return $this->config['enabled_types'][$type] ?? false;
    }

    protected function ensureDirectoryExists(string $path): void
    {
        $directory = dirname($path);
        
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
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
