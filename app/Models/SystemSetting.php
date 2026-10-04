<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SystemSetting extends Model
{
    protected $primaryKey = 'setting_key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['setting_key', 'setting_value'];

    public static function get(string $key, string $default = 'default'): string
    {
        $value = static::query()->where('setting_key', $key)->value('setting_value');

        return $value !== null && $value !== '' ? $value : $default;
    }

    public static function set(string $key, string $value): void
    {
        static::updateOrCreate(['setting_key' => $key], ['setting_value' => $value]);
    }

    /**
     * @return array{barangay_name: string, system_name: string, icon_url: ?string}
     */
    public static function branding(): array
    {
        $settings = static::query()
            ->whereIn('setting_key', ['barangay_name', 'system_name', 'brand_icon'])
            ->pluck('setting_value', 'setting_key');

        $iconPath = $settings->get('brand_icon', 'default');
        $iconUrl = str_starts_with($iconPath, 'branding/') && Storage::disk('public')->exists($iconPath)
            ? asset('storage/' . $iconPath)
            : null;

        return [
            'barangay_name' => $settings->get('barangay_name') ?: 'Barangay',
            'system_name' => $settings->get('system_name') ?: 'Equipment',
            'icon_url' => $iconUrl,
        ];
    }
}
