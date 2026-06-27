<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePasswordPolicy();
    }

    /**
     * Application-wide password rules, applied everywhere via Password::defaults().
     *
     * Follows the OWASP Authentication Cheat Sheet / NIST SP 800-63B guidance:
     * favour length over forced character classes (so no symbol/number/case
     * requirements), and reject passwords found in known breach corpora via
     * the Have I Been Pwned k-anonymity API. The breach check is skipped only
     * under the test suite so tests stay offline and deterministic.
     */
    private function configurePasswordPolicy(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(12);

            return $this->app->runningUnitTests()
                ? $rule
                : $rule->uncompromised();
        });
    }
}
