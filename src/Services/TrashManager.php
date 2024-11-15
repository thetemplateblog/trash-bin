<?php

namespace TheTemplateBlog\TrashBin\Services;

use Statamic\Facades\{YAML, Entry, Collection as CollectionFacade};
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
     * Parse markdown file content into front matter and body
     * 
     * @param string $content The raw markdown file content
     * @return array Array containing 'frontMatter' and 'content' keys
     */
    protected function parseMarkdownFile(string $content): array
    {
        if (preg_match('/^---[\r\n|\n](.*?)[\r\n|\n]---[\r\n|\n](.*?)$/s', $content, $matches)) {
            return [
                'frontMatter' => YAML::parse($matches[1]),
                'content' => trim($matches[2])
            ];
        }
        
        return [
            'frontMatter' => [],
            'content' => trim($content)
        ];
    }

    /**
     * Get all items in the trash bin
     * 
     * @param int $limit Maximum number of items to return
     * @return Collection Collection of trashed items
     */
    public function getTrashedItems(int $limit = 50): Collection
    {
        return collect($this->getTrashFiles())
            ->take($limit)
            ->map(function ($file) {
                $pathParts = explode('/', $file);
                $type = $pathParts[count($pathParts) - 2];
                $filename = pathinfo($file, PATHINFO_BASENAME);
                
                $metadata = $this->getMetadata($type, $filename);
                $fileContent = File::get($file);
                $parsed = $this->parseMarkdownFile($fileContent);
                
                return [
                    'id' => $parsed['frontMatter']['id'] ?? null,
                    'filename' => $filename,
                    'type' => $type,
                    'title' => $parsed['frontMatter']['title'] ?? 'Untitled',
                    'collection' => $metadata['collection'] ?? null,
                    'deleted_at' => $metadata['deleted_at'] ?? File::lastModified($file),
                    'formatted_date' => Carbon::createFromTimestamp($metadata['deleted_at'] ?? File::lastModified($file))->diffForHumans(),
                ];
            })
            ->filter(function ($item) {
                return !empty($item['id']);
            })
            ->sortByDesc('deleted_at')
            ->values();
    }

    /**
     * Get a specific trashed item by type and ID
     * 
     * @param string $type The type of item (e.g., 'entries')
     * @param string $id The item's ID
     * @return array|null The trashed item data or null if not found
     */
    public function getTrashedItem(string $type, string $id): ?array
    {
        $typePath = $this->trashRoot . '/' . $type;
        if (!File::exists($typePath)) {
            return null;
        }

        foreach (File::glob($typePath . '/*.md') as $file) {
            $filename = pathinfo($file, PATHINFO_BASENAME);
            $fileContent = File::get($file);
            $parsed = $this->parseMarkdownFile($fileContent);
            
            if (isset($parsed['frontMatter']['id']) && $parsed['frontMatter']['id'] === $id) {
                $metadata = $this->getMetadata($type, $filename);
                
                return [
                    'id' => $id,
                    'filename' => $filename,
                    'type' => $type,
                    'title' => $parsed['frontMatter']['title'] ?? 'Untitled',
                    'collection' => $metadata['collection'] ?? null,
                    'deleted_at' => $metadata['deleted_at'] ?? File::lastModified($file),
                    'formatted_date' => Carbon::createFromTimestamp($metadata['deleted_at'] ?? File::lastModified($file))->diffForHumans(),
                    'metadata' => [
                        'entry' => [
                            'content' => $parsed['content'],
                            'data' => array_merge($parsed['frontMatter'], [
                                'collection' => $metadata['collection'] ?? null,
                                'path' => $metadata['path'] ?? null,
                            ])
                        ]
                    ]
                ];
            }
        }
        
        return null;
    }

    /**
     * Permanently delete a trashed item
     * 
     * @param string $type The type of item
     * @param string $id The item's ID
     * @throws \Exception if item not found
     */
    public function destroy(string $type, string $id): void
    {
        $item = $this->getTrashedItem($type, $id);
        if (!$item) {
            throw new \Exception("Trashed item not found");
        }

        $contentPath = $this->getPath($type, $item['filename']);
        $metadataPath = $this->getMetadataPath($type, $item['filename']);

        if (File::exists($contentPath)) {
            File::delete($contentPath);
        }

        if (File::exists($metadataPath)) {
            File::delete($metadataPath);
        }

        Log::info('Item permanently deleted', [
            'type' => $type,
            'id' => $id,
            'title' => $item['title'],
            'collection' => $item['collection']
        ]);
    }

    /**
     * Restore an item from trash
     * 
     * @param string $type The type of item
     * @param string $id The item's ID
     * @throws \Exception if item not found or restoration fails
     */
    public function restore(string $type, string $id): void
    {
        $item = $this->getTrashedItem($type, $id);
        if (!$item) {
            throw new \Exception("Trashed item not found");
        }

        $trashPath = $this->getPath($type, $item['filename']);
        $metadata = $this->getMetadata($type, $item['filename']);

        if (empty($metadata['collection'])) {
            throw new \Exception("No collection information found");
        }

        $collection = CollectionFacade::findByHandle($metadata['collection']);
        if (!$collection) {
            throw new \Exception("Collection not found: {$metadata['collection']}");
        }

        $originalPath = $metadata['path'] ?? null;
        if (empty($originalPath)) {
            throw new \Exception("Original path information not found");
        }

        $restorePath = File::exists($originalPath) 
            ? $this->getUniqueRestorePath($originalPath)
            : $originalPath;

        $this->ensureDirectoryExists(dirname($restorePath));

        $entry = Entry::make()
            ->id($item['id'])
            ->collection($metadata['collection'])
            ->slug(pathinfo($item['filename'], PATHINFO_FILENAME));

        if (!empty($metadata['data'])) {
            $entryData = $metadata['data'];
            unset($entryData['id']);
            $entry->data($entryData);
        }

        $entry->save();
        File::move($trashPath, $restorePath);
        File::delete($this->getMetadataPath($type, $item['filename']));
    }

    /**
     * Move an item to trash
     * 
     * @param string $type The type of item
     * @param array $metadata The item's metadata
     * @throws \Exception if original file not found
     */
    public function moveToTrash(string $type, array $metadata): void
    {
        $filePath = $metadata['path'];
    
        if (!File::exists($filePath)) {
            throw new \Exception("Original file not found: {$filePath}");
        }
    
        $filename = basename($filePath);
        $trashPath = $this->trashRoot . '/' . $type . '/' . $filename;
        $this->ensureDirectoryExists(dirname($trashPath));
    
        File::copy($filePath, $trashPath);
    
        $metadataToStore = [
            'collection' => $metadata['collection'],
            'path' => $filePath,
            'slug' => $metadata['slug'],
            'filename' => $filename,
            'deleted_at' => Carbon::now()->timestamp,
        ];
    
        File::put(
            $this->getMetadataPath($type, $filename),
            YAML::dump($metadataToStore)
        );
    }

    /**
     * Get the path of a trashed file
     * 
     * @param string $type The type of item
     * @param string $filename The filename
     * @return string The full path to the trashed file
     */
    protected function getPath(string $type, string $filename): string
    {
        return $this->trashRoot . '/' . $type . '/' . $filename;
    }

    /**
     * Get all files in the trash bin
     * 
     * @return array Array of file paths
     */
    private function getTrashFiles(): array
    {
        if (!File::exists($this->trashRoot)) {
            File::makeDirectory($this->trashRoot, 0755, true);
            return [];
        }

        $files = [];
        
        foreach ($this->config['enabled_types'] as $type => $enabled) {
            if (!$enabled) continue;
            
            $typePath = $this->trashRoot . '/' . $type;
            
            if (!File::exists($typePath)) continue;
            
            $typeFiles = File::glob($typePath . '/*.md');
            
            $typeFiles = array_filter($typeFiles, function($file) use ($type) {
                $filename = pathinfo($file, PATHINFO_BASENAME);
                return File::exists($this->getMetadataPath($type, $filename));
            });
            
            $files = array_merge($files, $typeFiles);
        }
        
        return $files;
    }

    /**
     * Get metadata path for a trashed item
     * 
     * @param string $type The type of item
     * @param string $filename The filename
     * @return string The full path to the metadata file
     */
    protected function getMetadataPath(string $type, string $filename): string
    {
        return $this->trashRoot . '/' . $type . '/' . $filename . '.meta.yaml';
    }

    /**
     * Get metadata for a trashed item
     * 
     * @param string $type The type of item
     * @param string $filename The filename
     * @return array The metadata
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
}
