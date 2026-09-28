<?php

namespace App\Logging;

use App\Tenancy\TenantContext;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Stamps every log line with the workspace, user and request it belongs to.
 *
 * Without this, a multi-tenant log is unusable during an incident: you can see
 * that *someone* hit an SMTP failure, but not whose sending domain is on fire.
 */
final class TenantContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = array_filter([
            'tenant_id' => TenantContext::id(),
            'user_id' => auth()->hasUser() ? auth()->id() : null,
            'request_id' => request()?->headers->get('X-Request-Id'),
            'env' => config('app.env'),
        ], static fn ($value) => $value !== null);

        return $record->with(extra: [...$record->extra, ...$extra]);
    }
}
