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
     * Parse YAML front matter from a file
     */
    private function parseYamlFrontMatter(string $content): array
    {
        if (Str::startsWith($content, '---')) {
            $parts = explode('---', $content, 3);
            if (count($parts) >= 3) {
                return YAML::parse($parts[1]);
            }
        }
        return [];
    }

    /**
     * Get all trashed items
     */
    public function getTrashedItems(int $limit = 50): Collection
    {
        Log::info('Getting trashed items');
        return collect($this->getTrashFiles())
            ->take($limit)
            ->map(function ($file) {
                Log::info('Processing file', ['file' => $file]);
                
                $pathParts = explode('/', $file);
                $type = $pathParts[count($pathParts) - 2]; // Get the parent directory name
                $id = pathinfo($file, PATHINFO_FILENAME);
                
                // Get content and metadata
                $content = File::get($file);
                $frontMatter = $this->parseYamlFrontMatter($content);
                $metadata = $this->getMetadata($type, $id);
                
                Log::info('File data', [
                    'id' => $id,
                    'type' => $type,
                    'frontMatter' => $frontMatter,
                    'metadata' => $metadata
                ]);
                
                return [
                    'id' => $id,
                    'type' => $type,
                    'title' => $frontMatter['title'] ?? 'Untitled',
                    'collection' => $metadata['collection'] ?? null,
                    'deleted_at' => $metadata['deleted_at'] ?? File::lastModified($file),
                    'formatted_date' => Carbon::createFromTimestamp($metadata['deleted_at'] ?? File::lastModified($file))->diffForHumans(),
                ];
            })
            ->sortByDesc('deleted_at')
            ->values();
    }
    
    private function getTrashFiles(): array
    {
        Log::info('Starting getTrashFiles', ['trashRoot' => $this->trashRoot]);

        if (!File::exists($this->trashRoot)) {
            Log::info('Trash root does not exist, creating it');
            File::makeDirectory($this->trashRoot, 0755, true);
            return [];
        }

        $files = [];
        
        // Get all enabled type directories
        foreach ($this->config['enabled_types'] as $type => $enabled) {
            if (!$enabled) continue;
            
            $typePath = $this->trashRoot . '/' . $type;
            Log::info('Checking type directory', ['type' => $type, 'path' => $typePath]);
            
            if (!File::exists($typePath)) continue;
            
            // Get all .md files in this type directory
            $typeFiles = File::glob($typePath . '/*.md');
            Log::info('Found files', ['type' => $type, 'files' => $typeFiles]);
            
            // Filter out meta files
            $typeFiles = array_filter($typeFiles, function($file) {
                return !Str::endsWith($file, '.meta.yaml');
            });
            
            $files = array_merge($files, $typeFiles);
        }
        
        Log::info('Final files list', ['count' => count($files), 'files' => $files]);
        return $files;
    }

    // ... (rest of the code unchanged)
}
