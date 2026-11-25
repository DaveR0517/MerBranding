<?php

namespace App\Providers;

use App\Services\Gmail\GmailTransport;
use App\Services\Gmail\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
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
        $this->configureDefaults();

        // Mail via de Gmail API (de server mag niet via de mailpoorten versturen): MAIL_MAILER=gmail.
        Mail::extend('gmail', fn () => new GmailTransport($this->app->make(Workspace::class)));

        // Spamwering formulieren website (VOORSTEL: aantal per uur instelbaar in config/mebranding.php).
        RateLimiter::for('leads', fn (Request $request) => Limit::perHour(config('mebranding.lead_rate_limit_per_hour'))->by($request->ip()));

        // Super Admin mag alles.
        Gate::before(fn ($user) => $user->hasRole('Super Admin') ? true : null);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
