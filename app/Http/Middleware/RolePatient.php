<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RolePatient
{
    protected $response;

    public function __construct(ApiResponse $response)
    {
        $this->response = $response;
    }
    
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->role_id !== 1) {
            return $this->response->error(__('messages.unauthorized'), 403);
        }

        return $next($request);
    }
}
