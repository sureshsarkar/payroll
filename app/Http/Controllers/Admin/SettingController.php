<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class SettingController extends Controller
{
    public function settings()
    {
        checkAdminHasPermissionAndThrowException('settings.view');
        return view('admin.settings.settings');
    }
}
