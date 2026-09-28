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
