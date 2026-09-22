<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CurrencyResource;
use App\Models\Currency;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The currency catalogue the settings screens pick from.
 *
 * Read-only: currencies are a fixed catalogue seeded with the installation
 * (VES, USD, USDT, COP), not something a store invents. Which of them a store
 * accepts is a store setting, and that is what the panel edits.
 */
class CurrencyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CurrencyResource::collection(
            Currency::query()->orderBy('code')->get()
        );
    }
}
