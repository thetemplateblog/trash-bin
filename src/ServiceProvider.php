<?php

namespace TheTemplateBlog\TrashBin;

use Statamic\Providers\AddonServiceProvider;
use TheTemplateBlog\TrashBin\Http\Middleware\TrashBinPermissions;
use Statamic\Events\EntryDeleting;
use TheTemplateBlog\TrashBin\Listeners\HandleEntryDeleting;
use TheTemplateBlog\TrashBin\Services\TrashManager;
use Statamic\Facades\{CP\Nav, File, Path};
use Statamic\Facades\Permission;
use Illuminate\Support\Facades\Log;

class ServiceProvider extends AddonServiceProvider
{
    protected $middlewareGroups = [
        'statamic.cp.authenticated' => [
            TrashBinPermissions::class,
        ],
    ];

    protected $listen = [
        EntryDeleting::class => [
            HandleEntryDeleting::class,
        ],
    ];

    protected $scripts = [
        __DIR__.'/../dist/js/cp.js',
    ];

    protected $stylesheets = [
        __DIR__.'/../dist/css/cp.css',
    ];

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $viewNamespace = 'trash-bin';

    /**
     * Boot the addon after Statamic has fully booted
     */
    public function bootAddon()
    {
        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', $this->viewNamespace);

        // Register publishables with tags for better organization
        $this->publishes([
            __DIR__.'/../config/trash-bin.php' => config_path('trash-bin.php'),
        ], ['trash-bin', 'trash-bin-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/trash-bin'),
        ], ['trash-bin', 'trash-bin-views']);

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/trash-bin'),
        ], ['trash-bin', 'trash-bin-translations']);

        // Initialize trash directory structure
        $this->initializeTrashStructure();

        // Register navigation
        $this->bootNavigation();
        
        // Register permissions
        $this->bootPermissions();
    }

    /**
     * Initialize the trash directory structure
     */
    protected function initializeTrashStructure()
    {
        $trashRoot = config('trash-bin.paths.trash_folder');
        
        // Create root trash directory if it doesn't exist
        if (!File::exists($trashRoot)) {
            Log::info('Creating trash root directory');
            File::makeDirectory($trashRoot, 0755, true);
        }

        // Create directories for each enabled type
        foreach (config('trash-bin.enabled_types', []) as $type => $enabled) {
            if ($enabled) {
                $typePath = $trashRoot . '/' . $type;
                if (!File::exists($typePath)) {
                    Log::info('Creating type directory', ['type' => $type, 'path' => $typePath]);
                    File::makeDirectory($typePath, 0755, true);
                }
            }
        }
    }

    /**
     * Register the addon's services
     */
    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/trash-bin.php', 'trash-bin'
        );

        // Register singleton using app binding
        $this->app->singleton(TrashManager::class, function ($app) {
            return new TrashManager();
        });
    }

    /**
     * Boot the navigation items
     */
    protected function bootNavigation()
    {
        if (! config('trash-bin.show_in_nav', true)) {
            return;
        }

        Nav::extend(function ($nav) {
            $nav->tools('Trash Bin')
                ->route('trash-bin.index')
                ->icon('<svg viewBox="0 0 16 16"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M1 3h14M9.5 1h-3a1 1 0 0 0-1 1v1h5V2a1 1 0 0 0-1-1zm-3 10.5v-5m3 5v-5m3.077 7.583a1 1 0 0 1-.997.917H4.42a1 1 0 0 1-.996-.917L2.5 3h11l-.923 11.083z"/></svg>');
        });          
    }

    /**
     * Boot the permissions
     */
    protected function bootPermissions()
    {
        Permission::group('trash-bin', 'Trash Bin', function () {
            Permission::register('view trash-bin')
                ->label('View Trash Bin');

            Permission::register('view trash-bin-item')
                ->label('View Trashed Item');

            Permission::register('restore trash-bin-item')
                ->label('Restore Items from Trash');

            Permission::register('delete trash-bin-item')
                ->label('Delete Items from Trash');
        });
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [
            TrashManager::class,
        ];
    }
}
