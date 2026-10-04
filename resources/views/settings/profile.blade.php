<x-layouts.app :title="'Profile Settings'">
    <div class="max-w-2xl space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Profile Settings</h2>
            <p class="text-slate-500 text-sm">Manage your account details, password, and security questions.</p>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold text-slate-800 mb-4">Account Information</h3>
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf @method('PUT')
                <div class="flex items-center gap-4">
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-sky-100 text-xl font-bold text-sky-700">
                        @if ($user->profilePhotoUrl())
                            <img src="{{ $user->profilePhotoUrl() }}" alt="Profile photo" class="h-full w-full object-cover">
                        @else
                            {{ strtoupper(substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1)) ?: 'U' }}
                        @endif
                    </div>
                    <div class="space-y-2">
                        <label for="profile-photo" class="block text-sm font-medium text-slate-700">Profile photo</label>
                        <input id="profile-photo" type="file" name="profilePhoto" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700">
                        <p class="text-xs text-slate-500">JPG, PNG, or WebP. Maximum file size: 2 MB.</p>
                        @error('profilePhoto')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @if ($user->profile_photo_path)
                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" name="removePhoto" value="1" class="rounded border-slate-300">
                                Remove current photo
                            </label>
                        @endif
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                        <input type="text" name="username" value="{{ old('username', $user->username) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div></div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">First Name</label>
                        <input type="text" name="firstName" value="{{ old('firstName', $user->first_name) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Last Name</label>
                        <input type="text" name="lastName" value="{{ old('lastName', $user->last_name) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Contact Number</label>
                        <input type="text" name="contactNumber" value="{{ old('contactNumber', $user->contact_number) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                </div>
                <button class="rounded-lg bg-brand-600 text-white px-4 py-2 text-sm font-medium">Save Changes</button>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold text-slate-800 mb-4">Change Password</h3>
            <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf @method('PUT')
                <input type="password" name="currentPassword" placeholder="Current password" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <input type="password" name="newPassword" placeholder="New password (min 6 chars)" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <input type="password" name="confirmPassword" placeholder="Confirm new password" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <button class="rounded-lg bg-brand-600 text-white px-4 py-2 text-sm font-medium">Update Password</button>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold text-slate-800 mb-2">Security Questions</h3>
            <p class="text-sm text-slate-500 mb-4">Used to reset your password if you forget it. Saving replaces all three questions.</p>
            <form method="POST" action="{{ route('security-questions.update') }}" class="space-y-3">
                @csrf @method('PUT')
                @for ($i = 1; $i <= 3; $i++)
                    <div class="grid sm:grid-cols-2 gap-2">
                        <input type="text" name="question{{ $i }}" placeholder="Security question {{ $i }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <input type="text" name="answer{{ $i }}" placeholder="Answer" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                @endfor
                <button class="rounded-lg bg-brand-600 text-white px-4 py-2 text-sm font-medium">Save Security Questions</button>
            </form>
        </div>
    </div>
</x-layouts.app>
