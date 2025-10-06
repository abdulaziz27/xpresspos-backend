<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\AuthorizationException;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Override the default authorize helper so Sanctum tokens (api guard)
     * are resolved correctly when policies are evaluated.
     *
     * @throws AuthorizationException
     */
    protected function authorize($ability, $arguments = [])
    {
        $arguments = is_array($arguments) ? $arguments : [$arguments];

        $user = auth()->user() ?: request()->user();

        if (! $user) {
            throw new AuthorizationException();
        }

        return Gate::forUser($user)->authorize($ability, $arguments);
    }
}
