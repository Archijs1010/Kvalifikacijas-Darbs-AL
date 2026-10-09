<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A test can never reach the real CSFloat API: every outbound request
        // must be faked explicitly or it fails loudly instead of burning quota.
        Http::preventStrayRequests();
    }
}
