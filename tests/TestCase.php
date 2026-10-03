<?php

namespace Tests;

use App\Models\Institute;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests run behind the `active.institute.subscription` middleware.
        // Auto-subscribe every institute created during a test so older suites
        // that predate the middleware keep passing.
        Institute::observe(\Tests\Support\SubscribeAllTestInstitutes::class);
    }
}
