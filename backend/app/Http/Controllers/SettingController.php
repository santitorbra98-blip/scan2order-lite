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
    ];

    public function index(Request $request)
    {
        if (!$request->user()?->hasRole('superadmin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $settings = [];
        foreach (self::ALLOWED_KEYS as $key) {
            $settings[$key] = Setting::get($key);
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
        ]);

        foreach ($data as $key => $value) {
            if (in_array($key, self::ALLOWED_KEYS, true)) {
                Setting::set($key, $value !== null ? (string) $value : null);
            }
        }

        $settings = [];
        foreach (self::ALLOWED_KEYS as $key) {
            $settings[$key] = Setting::get($key);
        }

        return response()->json($settings);
    }
}
