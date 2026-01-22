<?php

namespace App\Http\Middleware;

use App\Constants\Keys;
use Closure;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\BaseController;

class AdminAuth extends BaseController
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next)
    {
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            $this->addFailResultKeyValue(Keys::ERROR, "Unauthorized user.");
            return $this->sendFailResult();
        }

        // Check if user is admin
        if (!$user->is_admin) {
            $this->addFailResultKeyValue(Keys::ERROR, "Access denied. Only admin can access this API.");
            return $this->sendFailResult();
        }

        return $next($request);
    }
}
