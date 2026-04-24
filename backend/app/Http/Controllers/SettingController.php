<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const ALLOWED_KEYS = [
        'default_max_restaurants',
        'default_max_catalogs',
        'default_max_products',
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
    ];

    public function index(Request $request)
    {
        if (!$request->user()?->hasRole('superadmin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $settings = [];
        foreach (self::ALLOWED_KEYS as $key) {
            $value = Setting::get($key);
            $settings[$key] = ($key === 'mail_password' && $value !== null && $value !== '')
                ? '********'
                : $value;
        }

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        if (!$request->user()?->hasRole('superadmin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $data = $request->validate([
            'default_max_restaurants' => 'sometimes|nullable|integer|min:0|max:9999',
            'default_max_catalogs'    => 'sometimes|nullable|integer|min:0|max:9999',
            'default_max_products'    => 'sometimes|nullable|integer|min:0|max:9999',
            'mail_mailer'             => 'sometimes|nullable|string|max:50',
            'mail_host'               => 'sometimes|nullable|string|max:255',
            'mail_port'               => 'sometimes|nullable|integer|min:1|max:65535',
            'mail_username'           => 'sometimes|nullable|string|max:255',
            'mail_password'           => 'sometimes|nullable|string|max:255',
            'mail_encryption'         => 'sometimes|nullable|string|max:20',
            'mail_from_address'       => 'sometimes|nullable|email|max:255',
            'mail_from_name'          => 'sometimes|nullable|string|max:255',
        ]);

        foreach ($data as $key => $value) {
            // Skip if the frontend sent back the masked placeholder
            if ($key === 'mail_password' && $value === '********') {
                continue;
            }
            if (in_array($key, self::ALLOWED_KEYS, true)) {
                Setting::set($key, $value !== null ? (string) $value : null);
            }
        }

        $settings = [];
        foreach (self::ALLOWED_KEYS as $key) {
            $value = Setting::get($key);
            $settings[$key] = ($key === 'mail_password' && $value !== null && $value !== '')
                ? '********'
                : $value;
        }

        return response()->json($settings);
    }
}
