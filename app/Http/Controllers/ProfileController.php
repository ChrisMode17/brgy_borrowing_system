<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Ported from backend/admin/user_profile_func.php */
class ProfileController extends Controller
{
    public function edit()
    {
        /** @var User $user */
        $user = Auth::user();

        return view('settings.profile', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:4', 'regex:/^[a-zA-Z0-9_]+$/', Rule::unique('users', 'username')->ignore($user->user_id, 'user_id')],
            'firstName' => ['required', 'string', 'max:50'],
            'lastName' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')],
            'contactNumber' => ['required', 'string', 'regex:/^[0-9]{7,20}$/'],
            'profilePhoto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'removePhoto' => ['sometimes', 'boolean'],
        ]);

        $attributes = [
            'username' => trim($validated['username']),
            'first_name' => trim($validated['firstName']),
            'last_name' => trim($validated['lastName']),
            'email' => trim($validated['email']),
            'contact_number' => $validated['contactNumber'],
        ];
        $oldPhotoPath = $user->profile_photo_path;
        $newPhotoPath = null;

        if ($request->hasFile('profilePhoto')) {
            $newPhotoPath = $request->file('profilePhoto')->store('profile-photos', 'public');
            if ($newPhotoPath === false) {
                throw new RuntimeException('Unable to save the uploaded profile photo.');
            }

            $attributes['profile_photo_path'] = $newPhotoPath;
        } elseif ($request->boolean('removePhoto')) {
            $attributes['profile_photo_path'] = null;
        }

        $user->update($attributes);

        if ($oldPhotoPath && ($newPhotoPath !== null || $request->boolean('removePhoto'))) {
            $publicStorage = Storage::disk('public');
            if ($publicStorage->exists($oldPhotoPath) && ! $publicStorage->delete($oldPhotoPath)) {
                throw new RuntimeException('Unable to remove the previous profile photo.');
            }
        }

        return back()->with('success', 'Profile updated successfully');
    }

    public function updatePassword(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:6'],
            'confirmPassword' => ['required', 'string', 'same:newPassword'],
        ]);

        if (! Hash::check($validated['currentPassword'], $user->password)) {
            return back()->with('error', 'Current password is incorrect');
        }

        $user->update(['password' => Hash::make($validated['newPassword'])]);

        return back()->with('success', 'Password changed successfully');
    }

    public function updateSecurityQuestions(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'question1' => ['required', 'string'],
            'question2' => ['required', 'string'],
            'question3' => ['required', 'string'],
            'answer1' => ['required', 'string'],
            'answer2' => ['required', 'string'],
            'answer3' => ['required', 'string'],
        ]);

        $user->securityAnswers()->delete();
        foreach ([1, 2, 3] as $n) {
            $user->securityAnswers()->create([
                'question' => trim($validated["question{$n}"]),
                'answer_hash' => Hash::make(strtolower(trim($validated["answer{$n}"]))),
            ]);
        }

        return back()->with('success', 'Security questions saved successfully');
    }
}
