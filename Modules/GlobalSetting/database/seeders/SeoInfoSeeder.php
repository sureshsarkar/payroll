<?php

namespace Modules\GlobalSetting\database\seeders;

use Illuminate\Database\Seeder;
use Modules\GlobalSetting\app\Models\SeoSetting;

class SeoInfoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $item1 = new SeoSetting();
        $item1->page_name = 'home_page';
        $item1->seo_title = 'Home || MBS Guru';
        $item1->seo_description = 'Home || MBS Guru';
        $item1->save();

        $item2 = new SeoSetting();
        $item2->page_name = 'about_page';
        $item2->seo_title = 'About || MBS Guru';
        $item2->seo_description = 'About || MBS Guru';
        $item2->save();

        $item2 = new SeoSetting();
        $item2->page_name = 'course_page';
        $item2->seo_title = 'Course || MBS Guru';
        $item2->seo_description = 'Course || MBS Guru';
        $item2->save();

        $item2 = new SeoSetting();
        $item2->page_name = 'blog_page';
        $item2->seo_title = 'Blog || MBS Guru';
        $item2->seo_description = 'Blog || MBS Guru';
        $item2->save();

        $item2 = new SeoSetting();
        $item2->page_name = 'contact_page';
        $item2->seo_title = 'Contact || MBS Guru';
        $item2->seo_description = 'Contact || MBS Guru';
        $item2->save();
    }
}
