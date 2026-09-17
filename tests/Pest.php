<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific
| PHPUnit test case class. By default, that class is "PHPUnit\Framework\TestCase".
| Of course, you may need to change it using the `pest()` function to bind a
| different class or extend the default test case with traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain
| conditions. The "expect()" function gives you access to a set of "expectations"
| methods that you can use to assert different things.
|
*/

// Custom expectation: response has security header
expect()->extend('toHaveSecurityHeader', function (string $header) {
    return $this->toHaveMethod('assertHeader');
});
