<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'), // تعيين كلمة مرور تطابق قواعد الحروف والأرقام
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/auth/profile')
            ->put('/auth/update-password', [
                'current_password' => 'password123',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/auth/profile');

        $this->assertTrue(Hash::check('newpassword123', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/auth/profile')
            ->put('/auth/update-password', [
                'current_password' => 'wrong-password',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ]);

        $response
            ->assertSessionHas('error')
            ->assertRedirect('/auth/profile');
    }
}
