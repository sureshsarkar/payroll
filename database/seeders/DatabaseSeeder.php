<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Currency\database\seeders\CurrencySeeder;
use Modules\Language\database\seeders\LanguageSeeder;
use Modules\GlobalSetting\database\seeders\SeoInfoSeeder;
use Modules\GlobalSetting\database\seeders\EmailTemplateSeeder;
use Modules\GlobalSetting\database\seeders\CustomPaginationSeeder;
use Modules\GlobalSetting\database\seeders\MarketingSettingSeeder;
use Modules\GlobalSetting\database\seeders\GlobalSettingInfoSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * LMS removal phase 2 (2026-08-27) — dropped every seeder belonging to a
     * deleted module: Installer, BasicPayment, Frontend's homepage-section and
     * featured-instructor seeders, PageBuilder, CertificateBuilder (+ its item
     * seeder), Menubuilder, InstructorRequest and Badges.
     */
    public function run(): void
    {
        $this->call([
            LanguageSeeder::class,
            CurrencySeeder::class,
            GlobalSettingInfoSeeder::class,
            MarketingSettingSeeder::class,
            CustomPaginationSeeder::class,
            EmailTemplateSeeder::class,
            SeoInfoSeeder::class,
            RolePermissionSeeder::class,
            AdminInfoSeeder::class,
        ]);
    }
}
