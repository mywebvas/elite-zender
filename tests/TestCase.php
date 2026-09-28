<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base case for every application test.
 *
 * `RefreshDatabase` lives here rather than only in tests/Pest.php. Pest's
 * `->use(RefreshDatabase::class)->in('Feature')` applies exclusively to
 * closure-style tests, so any plain PHPUnit class dropped into tests/Feature
 * silently ran against an unmigrated database and "passed" for the wrong
 * reason. Putting the trait on the base class makes that impossible.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
}
