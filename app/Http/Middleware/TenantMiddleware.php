<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $this->getSubdomain($request);
        
        if ($subdomain) {
            $tenant = Tenant::where('subdomain', $subdomain)
                ->where('is_active', true)
                ->first();
            
            if (!$tenant) {
                abort(404, 'Tenant not found');
            }
            
            // Store tenant in request attributes for use in controllers
            $request->attributes->set('tenant', $tenant);
            
            // Set global scope for tenant-specific queries
            app()->instance('tenant', $tenant);
        }
        
        return $next($request);
    }
    
    /**
     * Extract subdomain from the request
     */
    protected function getSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        
        // Get the base domain from config
        $baseDomain = config('app.domain', 'eventpast.test');
        
        // Remove base domain and extract subdomain
        $subdomain = str_replace('.' . $baseDomain, '', $host);
        
        // If subdomain is the same as host, no subdomain exists
        if ($subdomain === $host || $subdomain === $baseDomain) {
            return null;
        }
        
        return $subdomain;
    }
}
