
# Trash Bin Addon for Statamic

## Overview

The  **Trash Bin**  addon for Statamic adds functionality to manage "trashed" content. This addon provides a control panel interface where users can view, restore, or permanently delete soft-deleted items. It extends Statamic with a user-friendly "trash bin" feature for entries and other content types.

### Features:

-   View soft-deleted entries and assets.
-   Restore deleted items to their original state.
-   Permanently delete trashed items.
-   Integrated into Statamic’s Control Panel with user permissions.

## Installation

To install the  **Trash Bin Addon**, follow these steps:

1.  **Install the Addon**  via Composer:
    
    From your project's base directory, run the following command:
    
    bash
    
    ```
    composer require thetemplateblog/trash-bin
    ```
    
2.  **Publish the Configuration**  (optional):
    
    If you wish to customize the behavior, publish the config file using:
    
    bash
    
    ```
    php artisan vendor:publish --tag=trash-bin-config
    ```
    
    This will create a configuration file in  `config/trash-bin.php`  where you can adjust settings such as where trashed items are stored.
    
3.  **Clear Cache**:
    
    After installation, make sure to clear Statamic’s cache to ensure everything is loaded properly:
    
    bash
    
    ```
    php artisan cache:clear
    php please cache:clear
    ```
    

----------

## Configuration

You can configure the location of trashed items, permissions, and navigation visibility by editing the published config file:

php

```
// config/trash-bin.php

return [
    'trash_directory' => storage_path('app/trash'),   // Default directory for trashed items
    'show_in_nav' => true,                            // Whether to show the Trash Bin in CP navigation
];
```

----------

## Permissions

You'll need to configure  **permissions**  to control access to the Trash Bin:

-   **View Trash Bin**: Permission to view the Trash Bin and the list of trashed items (`view trash-bin`).
-   **View Trashed Items**: Permission to view and inspect individual trashed items (`view trash-bin-item`).
-   **Restore from Trash**: Permission to restore trashed items to their original state (`restore trash-bin-item`).
-   **Delete from Trash**: Permission to permanently delete items from the Trash Bin (`delete trash-bin-item`).

You can assign these permissions to specific  **User Roles**  from the Statamic Control Panel under  **Users → Roles**.

----------

## Usage

### Viewing Trashed Items

Once the addon is installed, you can access the  **Trash Bin**  from the Statamic Control Panel (CP).

1.  **Open Statamic’s Control Panel**.
2.  Navigate to  **"Trash Bin"**  (if shown in the navigation).
3.  The Trash Bin displays a list of all soft-deleted items (entries, assets, etc.).

### Restoring Items

To restore a specific trashed item:

1.  **Click "View"**  next to the trashed entry you want to inspect.
2.  In the detailed view, click  **"Restore"**  to restore the item to its original state.

### Deleting Items Permanently

1.  In the list of trashed items, click the  **Delete Permanently**  button.
2.  This action will delete the item permanently from the trash.

### Batch Actions

You can also perform  **batch actions**  (like deleting multiple items) using the batch checkbox feature at the top of the list.

----------

## Routes

By default, the Trash Bin routes are prefixed under  `/cp`  within the Control Panel (`/cp/trash-bin`).

### Available Routes:

-   **View Trash Bin**
    
    -   Route:  `/cp/trash-bin`
    -   Views the list of trashed items.
-   **View a Specific Trashed Item**
    
    -   Route:  `/cp/trash-bin/view/{type}/{id}`
    -   Views details of a specific trashed item.
-   **Restore a Trashed Item**
    
    -   Route:  `/cp/trash-bin/{type}/{id}/restore`
    -   Restores a soft-deleted item.
-   **Permanently Delete a Trashed Item**
    
    -   Route:  `/cp/trash-bin/{type}/{id}/permanent`
    -   Permanently deletes a soft-deleted item.

----------

## Support & Contributing

If you encounter any issues, feel free to report them on the  [Issues](https://github.com/the_template_blog/trash-bin/issues)  page of this addon’s repository.

To contribute:

1.  Fork the repository.
2.  Create a new branch (`git checkout -b feature/your-feature`).
3.  Make your changes.
4.  Commit your changes (`git commit -am 'Add some feature'`).
5.  Push to the branch (`git push origin feature/your-feature`).
6.  Open a Pull Request.

----------

## License

TBD