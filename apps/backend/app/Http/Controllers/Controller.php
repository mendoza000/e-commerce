<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Admin controllers authorise per record through policies (see
    // app/Policies). Public storefront controllers do not use this.
    use AuthorizesRequests;
}
