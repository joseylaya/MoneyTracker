<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class RequireEmail { public function handle(Request $request, Closure $next): Response { return $request->user()?->email ? $next($request) : redirect()->route('profile.edit')->with('success','Add your email to use Personal Finance.'); } }
