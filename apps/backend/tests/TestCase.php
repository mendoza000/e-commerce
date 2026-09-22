<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Makes every request in the test look like it came from the admin SPA.
     *
     * Sanctum only attaches the session (and therefore CSRF) middleware to
     * requests whose Origin/Referer matches `sanctum.stateful`. Test requests
     * carry no Origin, so without this the admin endpoints fail on a missing
     * session store — which is the correct production behaviour for a request
     * that did not come from the panel.
     */
    protected function actingFromAdminPanel(string $origin = 'http://tienda.test'): static
    {
        config(['sanctum.stateful' => [parse_url($origin, PHP_URL_HOST)]]);

        return $this->withHeader('Origin', $origin);
    }
}
