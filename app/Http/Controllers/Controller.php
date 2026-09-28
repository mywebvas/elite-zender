<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Every controller in this application is tenant-scoped and role-gated, so
     * `$this->authorize()` must always be available. Policies live in
     * app/Policies and are auto-discovered by Laravel's naming convention.
     */
    use AuthorizesRequests;
}
