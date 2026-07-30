<?php

namespace Modules\CryptoPayment\app\Providers;

use Exception;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Modules\BasicPayment\app\Services\PaymentMethodService;
use Modules\CryptoPayment\app\Http\Controllers\TBPayment;
use Modules\CryptoPayment\app\Models\CryptoPG;
use Modules\CryptoPayment\app\Services\CryptoPaymentGatewayService;

class CryptoPaymentServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'CryptoPayment';

    protected string $moduleNameLower = 'cryptopayment';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        try {
            $configPath = module_path('CryptoPayment', 'config/coingate.php');
            $this->overrideConfigFromFile($configPath, 'coingate');
        } catch (Exception $e) {
            logger()->error('Setting config failed for coingate on CryptoPaymentServiceProvider: ' . $e->getMessage());
        }

        try {
            $cryptoData = Cache::rememberForever('cryptoConfig', function () {
                // Audit 2026-05-18 phase 5 — decrypt secret keys at cache build.
                $raw = CryptoPG::pluck('value', 'key')->toArray();
                return (object) \App\Support\SecretSettings::decryptForTable('crypto_p_g', $raw);
            });

            $setConfigData = [
                'crypto.sandbox'          => $cryptoData->crypto_sandbox,
                'crypto.crypto_token'    => $cryptoData->crypto_token,
            ];

            config($setConfigData);
        } catch (Exception $e) {
            logger()->error('Setting cache failed for coingate on CryptoPaymentServiceProvider: ' . $e->getMessage());
        }
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->extend(PaymentMethodService::class, function ($service) {
            return new CryptoPaymentGatewayService($service);
        });

        $this->app->bind("tbpayment", function () {
            return new TBPayment();
        });
        // $this->app->bind("tbrefund", function () {
        //     return new TBRefund();
        // });
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        // $this->commands([]);
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        // $this->app->booted(function () {
        //     $schedule = $this->app->make(Schedule::class);
        //     $schedule->command('inspire')->hourly();
        // });
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'lang'), $this->moduleNameLower);
            $this->loadJsonTranslationsFrom(module_path($this->moduleName, 'lang'));
        }
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $this->publishes([module_path($this->moduleName, 'config/config.php') => config_path($this->moduleNameLower . '.php')], 'config');
        $this->mergeConfigFrom(module_path($this->moduleName, 'config/config.php'), $this->moduleNameLower);
    }

    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);

        $componentNamespace = str_replace('/', '\\', config('modules.namespace') . '\\' . $this->moduleName . '\\' . config('modules.paths.generator.component-class.path'));
        Blade::componentNamespace($componentNamespace, $this->moduleNameLower);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }

        return $paths;
    }

    /**
     * @param string $filePath
     * @param string $configKey
     */
    protected function overrideConfigFromFile(string $filePath, string $configKey): void
    {
        try {
            // Check if the configuration file exists
            if (File::exists($filePath)) {
                // Load the configuration file
                $config = include $filePath;

                // Ensure the file returns an array
                if (is_array($config)) {
                    // Override the configuration
                    foreach ($config as $key => $value) {
                        config(["{$configKey}.{$key}" => $value]);
                    }
                }
            }
        } catch (Exception $e) {
            logger()->error('Config Overwriting failed: ' . $e->getMessage());
        }
    }
}
