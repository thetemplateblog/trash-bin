<?php

namespace TheTemplateBlog\TrashBin;

use Statamic\Providers\AddonServiceProvider;
use TheTemplateBlog\TrashBin\Http\Middleware\TrashBinPermissions;
use Statamic\Events\EntryDeleting;
use TheTemplateBlog\TrashBin\Listeners\HandleEntryDeleting;
use TheTemplateBlog\TrashBin\Services\TrashManager;
use Statamic\Facades\{CP\Nav, File, Permission, User};
use Illuminate\Support\Facades\Log;

class ServiceProvider extends AddonServiceProvider
{

    protected $vite = [ 
        'input' => [
            'resources/js/trashbin.js',
            'resources/css/trashbin.css',
        ],
        'publicDirectory' => 'resources/dist',
    ]; 

    /**
     * Middleware groups for the addon
     */
    protected $middlewareGroups = [
        'statamic.cp.authenticated' => [
            TrashBinPermissions::class,
        ],
    ];

    /**
     * Event listeners for the addon
     */
    protected $listen = [
        EntryDeleting::class => [
            HandleEntryDeleting::class,
        ],
    ];

    /**
     * Route definitions for the addon
     */
    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    /**
     * View namespace for the addon
     */
    protected $viewNamespace = 'trash-bin';

    /**
     * Boot the addon after Statamic has fully booted
     * Handles view loading, publishable assets, directory structure, navigation, and permissions
     */
    public function bootAddon()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', $this->viewNamespace);

        $this->registerPublishables();
        $this->initializeTrashStructure();
        $this->bootNavigation();
        $this->bootPermissions();
    }

    /**
     * Register publishable assets for the addon
     * Allows users to publish config, views, and translations
     */
    protected function registerPublishables()
    {
        $this->publishes([
            __DIR__.'/../config/trash-bin.php' => config_path('trash-bin.php'),
        ], ['trash-bin', 'trash-bin-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/trash-bin'),
        ], ['trash-bin', 'trash-bin-views']);

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/trash-bin'),
        ], ['trash-bin', 'trash-bin-translations']);
    }

    /**
     * Initialize the trash directory structure
     * Creates necessary directories for trash storage
     */
    protected function initializeTrashStructure()
    {
        $trashRoot = config('trash-bin.paths.trash_folder');
        
        if (!File::exists($trashRoot)) {
            Log::info('Creating trash root directory');
            File::makeDirectory($trashRoot, 0755, true);
        }

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
     * Merges config and registers the TrashManager singleton
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/trash-bin.php', 'trash-bin'
        );

        $this->app->singleton(TrashManager::class, function ($app) {
            return new TrashManager();
        });
    }

    /**
     * Boot the navigation items
     * Adds the Trash Bin to Statamic's CP navigation if user has permission
     */
    protected function bootNavigation()
    {
        if (! config('trash-bin.show_in_nav', true)) {
            return;
        }

        Nav::extend(function ($nav) {
            $nav->content('Trash Bin')
                ->can('view trash-bin')
                ->route('trash-bin.index')
                ->icon('<svg viewBox="0 0 16 16"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M1 3h14M9.5 1h-3a1 1 0 0 0-1 1v1h5V2a1 1 0 0 0-1-1zm-3 10.5v-5m3 5v-5m3.077 7.583a1 1 0 0 1-.997.917H4.42a1 1 0 0 1-.996-.917L2.5 3h11l-.923 11.083z"/></svg>');
        });          
    }

    /**
     * Boot the permissions
     * Registers permissions for viewing and managing trashed items
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
     * 
     * @return array Array of service providers
     */
    public function provides(): array
    {
        return [
            TrashManager::class,
        ];
    }
}
