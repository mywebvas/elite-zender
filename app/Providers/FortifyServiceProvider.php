<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Notifications\LifecycleContent;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        $this->registerViews();
        $this->brandAuthEmails();

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            // Keyed on credential *and* IP: keying on IP alone locks out a
            // whole office behind one NAT, keying on the username alone lets
            // an attacker lock any account out at will.
            return [
                Limit::perMinute(5)->by($throttleKey)->response($this->throttled(...)),
                // Second, wider bucket: one IP spraying many usernames.
                Limit::perMinute(20)->by('login-ip:'.$request->ip())->response($this->throttled(...)),
            ];
        });

        // Password reset requests are an email-bombing and account-enumeration
        // vector; Fortify ships them unthrottled.
        RateLimiter::for('reset-password', fn (Request $request) => [
            Limit::perMinute(3)->by(Str::lower((string) $request->input('email')).'|'.$request->ip())->response($this->throttled(...)),
            Limit::perHour(20)->by('reset-ip:'.$request->ip())->response($this->throttled(...)),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)
            ->by('register-ip:'.$request->ip())
            ->response($this->throttled(...)));

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        $this->throttleFortifyRoutes();

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }

    /**
     * Bind a view to every enabled Fortify feature.
     *
     * Only the login and register views were bound. Password reset, the
     * two-factor challenge and password confirmation are all enabled in
     * config/fortify.php, so each of those routes threw
     * "Target [...ViewResponse] is not instantiable" — a 500. The two-factor
     * challenge is the worst of them: enabling 2FA locked the user out of
     * their own workspace with no way back in.
     */
    /**
     * Put the two highest-trust emails in the product's own shell.
     *
     * "Verify your email address" and "Reset password" were stock Laravel
     * markdown: a different typeface, a different button, no product name,
     * no support address. Those two messages arrive at the exact moments a
     * customer is deciding whether to trust the thing with their data, and
     * looking like a different application at that moment is the one place
     * inconsistency actually costs money — it is also precisely what a
     * phishing page looks like.
     */
    private function brandAuthEmails(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $content = new LifecycleContent(
                subject: 'Confirm your email address',
                heading: 'Confirm your email address',
                greetingName: $this->firstName($notifiable),
                lines: [
                    'One click and your workspace is fully unlocked. We ask because this platform sends email on your behalf — confirming you own this address is what protects your deliverability, and everybody else\'s.',
                    'Everything else already works: connect a relay, import contacts, build a campaign. Only sending waits on this.',
                ],
                eyebrow: 'Almost there',
                preheader: 'One click unlocks sending on your workspace.',
                actionLabel: 'Confirm my email',
                actionUrl: $url,
                outro: ['If you did not create an account, ignore this email and nothing further will happen.'],
            );

            return (new MailMessage)
                ->subject($content->subject)
                ->view('emails.lifecycle', ['content' => $content])
                ->text('emails.lifecycle-plain', ['content' => $content]);
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            $content = new LifecycleContent(
                subject: 'Reset your password',
                heading: 'Reset your password',
                greetingName: $this->firstName($notifiable),
                lines: [
                    sprintf('Somebody asked to reset the password for this account. The link below is good for %d minutes and can be used once.', $minutes),
                ],
                eyebrow: 'Security',
                preheader: sprintf('This link works once and expires in %d minutes.', $minutes),
                actionLabel: 'Choose a new password',
                actionUrl: url($url),
                outro: [
                    'If this was not you, no action is needed — your password has not changed and this link will expire on its own. If you get these repeatedly, tell us at '
                    .config('platform.support_email').'.',
                ],
                tone: 'warning',
            );

            return (new MailMessage)
                ->subject($content->subject)
                ->view('emails.lifecycle', ['content' => $content])
                ->text('emails.lifecycle-plain', ['content' => $content]);
        });
    }

    private function firstName(object $notifiable): string
    {
        $name = trim((string) ($notifiable->name ?? ''));
        $first = trim(explode(' ', $name)[0] ?? '');

        return $first !== '' ? $first : 'there';
    }

    private function registerViews(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        // Registered explicitly for the same reason as the rest: Fortify
        // binds a response contract per view, and an unbound one is a 500
        // the first time a customer follows a verification link.
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
    }

    /**
     * Attach rate limiters to the Fortify routes it does not expose in
     * config/fortify.php's `limiters` array.
     *
     * Password reset and registration ship completely unthrottled, which makes
     * them an email-bombing and account-enumeration vector.
     */
    private function throttleFortifyRoutes(): void
    {
        $this->app->booted(function (): void {
            $routes = $this->app->make('router')->getRoutes();

            // Names assigned with ->name() after a route is added are not in
            // the collection's lookup table until it is refreshed, so
            // getByName() silently returns null at boot time and the middleware
            // is never attached.
            $routes->refreshNameLookups();

            // Keyed by route *name*: 'register' is the GET view route, the
            // POST handler is 'register.store'. Throttling the wrong one is
            // the kind of mistake that reads as "done" and protects nothing.
            $map = [
                'password.email' => 'throttle:reset-password',
                'password.update' => 'throttle:reset-password',
                'register.store' => 'throttle:register',
            ];

            foreach ($map as $name => $middleware) {
                $routes->getByName($name)?->middleware($middleware);
            }
        });
    }

    /**
     * Content-negotiated throttle response.
     *
     * The previous handler always returned JSON, so a user who mistyped their
     * password five times on the HTML login form was shown a raw JSON blob.
     */
    private function throttled(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $message = 'Too many attempts. Please try again in a moment.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => ['code' => 'RATE_LIMITED', 'message' => $message]], 429);
        }

        return back()
            ->withInput($request->except('password'))
            ->withErrors(['email' => $message]);
    }
}
