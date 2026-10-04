<?php

namespace App\Http\Controllers;

use App\Models\BackgroundImage;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use App\Services\SystemBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Ported from backend/admin/background_settings.php + system_backgrounds_func.php */
class SystemController extends Controller
{
    public const UPLOAD_LIMIT = 5;

    public function index()
    {
        $disk = Storage::disk('public');
        $images = BackgroundImage::orderByDesc('created_at')->get()->map(function (BackgroundImage $image) use ($disk) {
            $image->setAttribute('is_available', str_starts_with($image->file_path, 'backgrounds/') && $disk->exists($image->file_path));
            $image->setAttribute('url', $image->is_available ? asset('storage/'.$image->file_path) : null);

            return $image;
        });
        $loginSetting = SystemSetting::get('login_background', 'default');
        $systemBranding = SystemSetting::branding();

        return view('system.index', compact('images', 'loginSetting', 'systemBranding'));
    }

    public function downloadBackup(SystemBackupService $backups): BinaryFileResponse
    {
        $path = $backups->create();
        $filename = 'system_backup_'.now()->format('Ymd_His').'.zip';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function restoreBackup(Request $request, SystemBackupService $backups)
    {
        $validated = $request->validate([
            'backup' => ['required', 'file', 'mimes:zip', 'extensions:zip', 'max:102400'],
            'confirmation' => ['required', 'string', Rule::in(['RESTORE'])],
        ]);

        try {
            $backups->restore($validated['backup']->getRealPath());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['backup' => $exception->getMessage()]);
        }

        AuditLogger::log('Restored system backup', 'System', 'Replaced system data and uploaded images from backup');

        return back()->with('success', 'System data and uploaded images were restored successfully.');
    }

    public function updateBranding(Request $request)
    {
        $validated = $request->validate([
            'barangay_name' => ['required', 'string', 'max:100'],
            'system_name' => ['required', 'string', 'max:100'],
            'brand_icon' => ['nullable', 'image', 'max:2048'],
            'remove_brand_icon' => ['nullable', 'boolean'],
        ]);

        $currentIcon = SystemSetting::get('brand_icon');
        $nextIcon = $currentIcon;

        if ($request->hasFile('brand_icon')) {
            $nextIcon = $request->file('brand_icon')->store('branding', 'public');

            if (! is_string($nextIcon)) {
                return back()->with('error', 'The system icon could not be uploaded.');
            }
        } elseif ($validated['remove_brand_icon'] ?? false) {
            $nextIcon = 'default';
        }

        SystemSetting::set('barangay_name', trim($validated['barangay_name']));
        SystemSetting::set('system_name', trim($validated['system_name']));
        SystemSetting::set('brand_icon', $nextIcon);

        if ($nextIcon !== $currentIcon && str_starts_with($currentIcon, 'branding/')) {
            Storage::disk('public')->delete($currentIcon);
        }

        AuditLogger::log('Updated system branding', 'System', 'Updated the barangay name, system name, or icon');

        return back()->with('success', 'System identity updated successfully.');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'backgroundFile' => ['required', 'image', 'max:5120'],
        ]);

        if (BackgroundImage::count() >= self::UPLOAD_LIMIT) {
            return back()->with('error', 'Maximum of '.self::UPLOAD_LIMIT.' background images allowed. Delete one first.');
        }

        $file = $request->file('backgroundFile');
        $path = $file->store('backgrounds', 'public');

        BackgroundImage::create([
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => Auth::user()->username,
            'created_at' => now(),
        ]);

        AuditLogger::log('Uploaded background image', 'System', $file->getClientOriginalName());

        return back()->with('success', 'Background image uploaded successfully');
    }

    public function setDefault(Request $request)
    {
        $validated = $request->validate([
            'target' => ['required', 'in:login_background'],
            'value' => ['required', 'string'],
        ]);

        if ($validated['value'] !== 'default') {
            $image = BackgroundImage::query()->find($validated['value']);

            if (! $image || ! str_starts_with($image->file_path, 'backgrounds/') || ! Storage::disk('public')->exists($image->file_path)) {
                return back()->with('error', 'That background file is unavailable. Upload the image again before selecting it.');
            }
        }

        SystemSetting::set($validated['target'], $validated['value']);

        return back()->with('success', 'Background updated');
    }

    public function destroy(BackgroundImage $backgroundImage)
    {
        if (SystemSetting::get('login_background') === (string) $backgroundImage->getKey()) {
            SystemSetting::set('login_background', 'default');
        }

        if (str_starts_with($backgroundImage->file_path, 'backgrounds/')) {
            Storage::disk('public')->delete($backgroundImage->file_path);
        }

        $backgroundImage->delete();

        return back()->with('success', 'Background image deleted');
    }
}
