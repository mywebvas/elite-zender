<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
