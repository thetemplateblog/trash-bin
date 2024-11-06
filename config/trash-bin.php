<?php

return [

    /*
     * The time in days after which trashed items are to be
     * automatically deleted (e.g., 30 days).
     */
    'auto_delete_after' => 30,

    /*
     * Specify which types of content can be soft deleted.
     * Enabled types include entries, assets, and forms.
     */
    'enabled_types' => [
        'entries' => true,
        'assets' => false,
        'forms' => false,
    ],

    /*
     * Toggle whether the Trash Bin is visible in the Control Panel.
     */
    'show_in_nav' => true,

    /*
     * Define paths for collections, assets, and trash folders.
     */
    'paths' => [
        'collections' => base_path('content/collections'),   // Directory where collections are stored.
        'public_assets' => public_path('vendor/trash-bin'),  // Path for public assets.
        'trash_folder' => base_path('content/collections/.trash'),  // The folder where trashed items are placed.
    ],
];
