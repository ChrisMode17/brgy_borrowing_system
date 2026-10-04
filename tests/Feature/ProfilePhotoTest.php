<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table): void {
            $table->id('role_id');
            $table->string('role_name');
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id('user_id');
            $table->string('last_name', 50);
            $table->string('first_name', 50);
            $table->string('username', 50)->unique();
            $table->string('password');
            $table->string('email', 100)->unique();
            $table->string('contact_number', 25);
            $table->string('profile_photo_path')->nullable();
            $table->unsignedBigInteger('role_id');
            $table->unsignedTinyInteger('access_level');
            $table->timestamp('created_at')->nullable();
            $table->string('remember_token')->nullable();
        });

        Storage::fake('public');
    }

    public function test_user_can_upload_a_profile_photo_and_save_its_path(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->put(route('profile.update'), [
            ...$this->profileFields(),
            'profilePhoto' => UploadedFile::fake()->createWithContent('avatar.png', $this->pngImage()),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Profile updated successfully');

        $photoPath = $user->fresh()->profile_photo_path;
        $this->assertIsString($photoPath);
        $this->assertStringStartsWith('profile-photos/', $photoPath);
        $this->assertTrue(Storage::disk('public')->exists($photoPath));
        $this->assertStringContainsString($photoPath, $user->fresh()->profilePhotoUrl());
    }

    public function test_user_can_remove_their_saved_profile_photo(): void
    {
        $user = $this->createUser();
        $photoPath = 'profile-photos/current.png';
        Storage::disk('public')->put($photoPath, $this->pngImage());
        $user->update(['profile_photo_path' => $photoPath]);
        $this->actingAs($user);

        $response = $this->put(route('profile.update'), [
            ...$this->profileFields(),
            'removePhoto' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Profile updated successfully');
        $this->assertNull($user->fresh()->profile_photo_path);
        $this->assertFalse(Storage::disk('public')->exists($photoPath));
    }

    public function test_invalid_profile_photo_is_rejected_without_changing_the_saved_photo(): void
    {
        $user = $this->createUser();
        $photoPath = 'profile-photos/current.png';
        Storage::disk('public')->put($photoPath, $this->pngImage());
        $user->update(['profile_photo_path' => $photoPath]);
        $this->actingAs($user);

        $response = $this->put(route('profile.update'), [
            ...$this->profileFields(),
            'profilePhoto' => UploadedFile::fake()
                ->createWithContent('avatar.png', 'not an image')
                ->mimeType('text/plain'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('profilePhoto');
        $this->assertSame($photoPath, $user->fresh()->profile_photo_path);
        $this->assertTrue(Storage::disk('public')->exists($photoPath));
    }

    private function createUser(): User
    {
        $role = Role::create(['role_name' => 'User']);

        return User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'password' => 'password',
            'email' => 'testuser@example.com',
            'contact_number' => '1234567890',
            'role_id' => $role->role_id,
            'access_level' => 3,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function profileFields(): array
    {
        return [
            'username' => 'testuser',
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => 'testuser@example.com',
            'contactNumber' => '1234567890',
        ];
    }

    private function pngImage(): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        };

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('N2C5', 1, 1, 8, 6, 0, 0, 0))
            .$chunk('IDAT', gzcompress("\x00\xff\xff\xff\xff"))
            .$chunk('IEND', '');
    }
}
