<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class VerifyInstagramSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Meta webhooks verification challenge
        if ($request->isMethod('get') && $request->has('hub_mode') && $request->has('hub_verify_token')) {
             if ($request->input('hub_verify_token') === config('services.instagram.verify_token')) {
                 return response($request->input('hub_challenge'), 200);
             }
             return response('Invalid Verify Token', 403);
        }

        // Signature verification for POST requests
        if ($request->isMethod('post')) {
            $signature = $request->header('X-Hub-Signature-256');
            
            // Allow missing signature for now if needed, or strictly enforce
            // if (!$signature) { ... }

            $appSecret = config('services.instagram.app_secret');
            if ($signature && $appSecret) {
                $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);
                
                if (!hash_equals($expected, $signature)) {
                    Log::error('Instagram Webhook signature mismatch', ['expected' => $expected, 'actual' => $signature]);
                    return response('Invalid Signature', 403);
                }
            }
        }

        return $next($request);
    }
}
