<?php

namespace Modules\BkashPG\app\Providers;

use Exception;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Modules\BasicPayment\app\Services\PaymentMethodService;
use Modules\BkashPG\app\Http\Controllers\Payment\TBPayment;
use Modules\BkashPG\app\Http\Controllers\Payment\TBRefund;
use Modules\BkashPG\app\Models\BkashPGModel;
use Modules\BkashPG\app\Services\BkashPaymentGatewayService;

class BkashPGServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'BkashPG';

    protected string $moduleNameLower = 'bkashpg';

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
            $configPath = module_path('BkashPG', 'config/bkash.php');
            $this->overrideConfigFromFile($configPath, 'bkash');
        } catch (Exception $e) {
            logger()->error('Setting config failed for bkash on BkashPGServiceProvider: ' . $e->getMessage());
        }

        try {
            $bkashData = Cache::rememberForever('bkashConfig', function () {
                // Audit 2026-05-18 phase 5 — decrypt secret keys at cache build.
                // Plaintext values pre-encryption pass through unchanged.
                $raw = BkashPGModel::pluck('value', 'key')->toArray();
                return (object) \App\Support\SecretSettings::decryptForTable('bkash_p_g_models', $raw);
            });

            $setConfigData = [
                'bkash.sandbox'          => $bkashData->bkash_sandbox,
                'bkash.bkash_app_key'    => $bkashData->bkash_key,
                'bkash.bkash_app_secret' => $bkashData->bkash_secret,
                'bkash.bkash_username'   => $bkashData->bkash_username,
                'bkash.bkash_password'   => $bkashData->bkash_password,
            ];

            config($setConfigData);
        } catch (Exception $e) {
            logger()->error('Setting cache failed for bkash on BkashPGServiceProvider: ' . $e->getMessage());
        }
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->extend(PaymentMethodService::class, function ($service) {
            return new BkashPaymentGatewayService($service);
        });

        $this->app->bind("tbpayment", function () {
            return new TBPayment();
        });
        $this->app->bind("tbrefund", function () {
            return new TBRefund();
        });

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
        $this->mergeConfigFrom(module_path($this->moduleName, 'config/bkash.php'), 'bkash');
    }

    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath   = resource_path('views/modules/' . $this->moduleNameLower);
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

    /**
     * @return mixed
     */
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
