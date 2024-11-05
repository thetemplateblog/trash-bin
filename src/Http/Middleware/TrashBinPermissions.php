<?php

namespace TheTemplateBlog\TrashBin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Statamic\Facades\User;
use Illuminate\Support\Facades\Log;

class TrashBinPermissions
{
    public function handle(Request $request, Closure $next)
    {
        // Ensure the user is authenticated
        if (!$user = User::current()) {
            Log::warning('Unauthorized access attempt. No user is authenticated.');
            abort(403, 'User not authenticated.');
        }

        // Dynamically check permissions based on the requested route action type
        $permissionRequired = '';

        // Check for a route name and match appropriate permissions
        if ($request->routeIs('trash-bin.index')) {
            $permissionRequired = 'view trash-bin';  // Permission to view the trash bin
        } elseif ($request->routeIs('trash-bin.view')) {
            $permissionRequired = 'view trash-bin-item';  // Permission to view individual items
        } elseif ($request->routeIs('trash-bin.restore')) {
            $permissionRequired = 'restore trash-bin-item';  // Permission to restore items
        } elseif ($request->routeIs('trash-bin.destroy') || $request->routeIs('trash-bin.soft-delete')) {
            $permissionRequired = 'delete trash-bin-item';  // Permission to delete items
        }

        // Proceed to the next middleware or the core request
        return $next($request);
    }
}
