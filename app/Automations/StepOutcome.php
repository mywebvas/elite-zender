<?php

namespace App\Automations;

use Carbon\CarbonInterface;

/**
 * What the engine should do after a step has run.
 *
 * `waitUntil` parks the enrolment; `nextStepId` jumps somewhere other than the
 * next step in order (used by conditions); neither set means "carry on".
 */
final readonly class StepOutcome
{
    private function __construct(
        public ?CarbonInterface $waitUntil = null,
        public ?string $nextStepId = null,
        public bool $stop = false,
    ) {}

    public static function continue(): self
    {
        return new self;
    }

    public static function waitUntil(CarbonInterface $when): self
    {
        return new self(waitUntil: $when);
    }

    public static function jumpTo(string $stepId): self
    {
        return new self(nextStepId: $stepId);
    }

    public static function stop(): self
    {
        return new self(stop: true);
    }
}
