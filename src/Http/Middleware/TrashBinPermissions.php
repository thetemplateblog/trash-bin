<?php

namespace TheTemplateBlog\TrashBin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Statamic\Facades\User;

class TrashBinPermissions
{
    /**
     * Map of route names to required permissions
     */
    protected $permissionMap = [
        'trash-bin.index' => 'view trash-bin',
        'trash-bin.view' => 'view trash-bin-item',
        'trash-bin.restore' => 'restore trash-bin-item',
        'trash-bin.destroy' => 'delete trash-bin-item',
        'trash-bin.soft-delete' => 'delete trash-bin-item',
    ];

    /**
     * Handle the incoming request
     * Checks if the user has the required permissions for the requested action
     * 
     * @param Request $request The HTTP request
     * @param Closure $next The next middleware
     * @return mixed
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$user = User::current()) {
            abort(403, 'User not authenticated.');
        }

        $routeName = $request->route()->getName();
        $permissionRequired = $this->permissionMap[$routeName] ?? null;

        if ($permissionRequired && !$user->can($permissionRequired)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
