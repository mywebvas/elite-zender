<?php

/*
|--------------------------------------------------------------------------
| Platform
|--------------------------------------------------------------------------
|
| Operator-facing product settings. `App\Platform\Settings` overrides these
| at runtime from the `settings` table, so a super admin can change them
| without a deploy — but only if the key exists here to be overridden.
|
| It did not. The admin console offered "Product name", "Support email" and
| "Allow new signups", wrote all three to the database, and nothing anywhere
| read them: `config('platform.name')` resolved to null and registration
| stayed open no matter what the toggle said. A control that does nothing is
| worse than no control, because an operator believes it.
|
*/

return [

    /*
     | Shown in the interface, in the title bar and in every outgoing
     | transactional email.
     */
    'name' => env('PLATFORM_NAME', env('APP_NAME', 'EliteSender')),

    /*
     | Where customers are told to write when something goes wrong. Falls
     | back to the from-address so an email never advertises a blank mailto.
     */
    'support_email' => env('PLATFORM_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS', 'support@example.com')),

    /*
     | Close public registration without taking the site down — the switch you
     | want during an abuse wave, a migration, or an invite-only beta.
     */
    'signups_open' => (bool) env('PLATFORM_SIGNUPS_OPEN', true),

    /*
    |--------------------------------------------------------------------------
    | Lifecycle messaging
    |--------------------------------------------------------------------------
    |
    | Timings for the automated customer-lifecycle emails. Every one of these
    | is sent at most once per subject (trial, invoice, period, threshold):
    | see App\Lifecycle\LifecycleMessenger. Restraint is the product decision
    | here — a billing reminder that arrives twice reads as a billing error.
    |
    */

    'lifecycle' => [

        /*
         | Master switch. Off means no lifecycle email leaves the platform —
         | useful while load-testing a restored database, dangerous in
         | production, which is why the admin console labels it as such.
         */
        'enabled' => (bool) env('LIFECYCLE_ENABLED', true),

        // Days before a trial ends that the "your trial is ending" email goes out.
        'trial_ending_days' => (int) env('LIFECYCLE_TRIAL_ENDING_DAYS', 3),

        // Days before an automatic renewal charge that we warn the customer.
        // Card-network rules expect advance notice on recurring charges, and
        // an unexpected debit is the single most common chargeback trigger.
        'renewal_reminder_days' => (int) env('LIFECYCLE_RENEWAL_REMINDER_DAYS', 3),

        // Days after an invoice is raised, and still unpaid, that we nudge.
        // Two nudges only: an abandoned-checkout recovery and a due-soon
        // reminder. Everything after that is the dunning schedule's job.
        'invoice_nudge_days' => [1, 3],

        // Days before the grace window closes that we warn about suspension.
        'suspension_warning_days' => (int) env('LIFECYCLE_SUSPENSION_WARNING_DAYS', 2),

        // Percentages of the monthly sending allowance that trigger a heads-up.
        'usage_alert_thresholds' => [80, 100],

        // Days after a workspace drops to the free plan that we check in.
        'win_back_days' => (int) env('LIFECYCLE_WIN_BACK_DAYS', 7),
    ],

];
