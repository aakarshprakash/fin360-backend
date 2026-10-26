<?php

namespace App\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * API-only backend — never redirect, always return JSON 401.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        // Returning null tells Laravel to throw an AuthenticationException
        // which produces a proper JSON 401 { "message": "Unauthenticated." }
        return null;
    }

    /**
     * Override the unauthenticated handler so it NEVER calls route('login').
     * Laravel 8's base class calls route('login') here which crashes API-only apps.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $guards
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function unauthenticated($request, array $guards)
    {
        throw new AuthenticationException('Unauthenticated.', $guards, null);
    }
}
