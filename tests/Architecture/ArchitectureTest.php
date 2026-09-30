<?php

/**
 * Architecture guardrails.
 *
 * Every rule here encodes a defect this codebase actually shipped. They are
 * cheap to run and they fail the build the moment the pattern comes back.
 */
arch('no debugging helpers reach production code')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit', 'echo'])
    ->not->toBeUsed();

arch('env() is only read inside config files')
    // env() returns null once `php artisan config:cache` has run. Reading it
    // anywhere else is how Redis throttling silently stayed off in production.
    ->expect('env')
    ->not->toBeUsedIn('app');

arch('controllers are thin and never touch the request superglobals')
    ->expect('App\Http\Controllers')
    ->not->toUse(['$_GET', '$_POST', '$_REQUEST', '$_SERVER']);

arch('models live in App\Models and extend Eloquent')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->ignoring(['App\Models\Role', 'App\Models\User']);

arch('jobs are queued, never run inline by accident')
    ->expect('App\Jobs')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

arch('policies only live in App\Policies')
    ->expect('App\Policies')
    ->toHaveSuffix('Policy');

arch('form requests validate and authorise')
    ->expect('App\Http\Requests')
    ->toHaveMethod('rules')
    ->ignoring('App\Http\Requests\CampaignRules');

arch('services are final so behaviour cannot be silently overridden')
    ->expect('App\Services')
    ->toBeFinal()
    ->ignoring('App\Services\SpinSyntaxService');

arch('support helpers are final and stateless')
    ->expect('App\Support')
    ->toBeFinal();

arch('nothing outside the tenancy layer binds the tenant container key directly')
    ->expect('current_tenant')
    ->not->toBeUsedIn('App\Http\Controllers');

arch('the frozen legacy reference is never executed')
    ->expect('App')
    ->not->toUse('Specimen1');

arch('strict PHP baseline')
    ->expect('App')
    ->not->toUse(['eval', 'extract', 'compact_unsafe', 'serialize', 'unserialize']);

/*
|--------------------------------------------------------------------------
| Source-level guards
|--------------------------------------------------------------------------
| Rules the arch plugin cannot express, checked against the source text.
*/

/**
 * `TenantContext::set()` is a footgun outside the tenancy layer itself.
 *
 * The pair `set($tenant) … set(null)` does not restore the previous tenant,
 * it clears it. Under Octane the container survives the request, and inside a
 * sync-driver job the caller's tenant is simply unbound half-way through its
 * own work. `TenantContext::run()` restores in a `finally` and is the only
 * approved entry point. (AGENTS.md — "rules that exist because they were
 * broken before".)
 */
it('never binds the tenant context manually outside the tenancy layer', function (): void {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app', RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = str_replace(dirname(__DIR__, 2).'/', '', $file->getPathname());

        if (str_starts_with($path, 'app/Tenancy/')) {
            continue; // the implementation itself
        }

        foreach (file($file->getPathname()) as $number => $line) {
            // Ignore prose: several classes document the old mistake.
            $code = trim($line);

            if ($code === '' || str_starts_with($code, '*') || str_starts_with($code, '//')) {
                continue;
            }

            if (str_contains($code, 'TenantContext::set(')) {
                $offenders[] = $path.':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBe([], 'Use TenantContext::run() instead: '.implode(', ', $offenders));
});

/**
 * No interface element may congratulate the user from the client.
 *
 * This codebase shipped the same lie three times: the onboarding wizard
 * toasted "SMTP Connected Successfully" and "Contacts imported" after a
 * `setTimeout`, the settings page toasted "IMAP Settings saved successfully"
 * from a form with no action, and the relay list toasted "Connection test
 * successful!" without opening a socket. In each case nothing was persisted,
 * nothing was tested, and the customer was told it had worked.
 *
 * A success message is a statement about server state, so it may only come
 * from the server — `session('success')` after a real round trip. Optimistic
 * client-side confirmation is banned outright because the failure mode is
 * silent and the user has no reason to doubt it.
 */
it('never confirms success from the client', function (): void {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/resources/views', RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $relative = str_replace(dirname(__DIR__, 2).'/', '', $file->getPathname());

        foreach (file($file->getPathname()) as $number => $line) {
            $code = trim($line);

            // Skip prose: several templates document the old mistake.
            if ($code === '' || str_starts_with($code, '{{--') || str_starts_with($code, '*')) {
                continue;
            }

            if (preg_match('/@click[^"\']*\$toast\([^)]*[\'"]success[\'"]/', $code) === 1) {
                $offenders[] = $relative.':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBe(
        [],
        'Success must be confirmed by the server via session("success"), not asserted in the browser: '.implode(', ', $offenders),
    );
});
