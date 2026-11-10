<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_registration_hashes_password_and_cannot_grant_admin_access(): void
    {
        $this->post('/register', ['name' => 'Alex', 'email' => 'ALEX@example.com', 'password' => 'long-test-password', 'password_confirmation' => 'long-test-password', 'is_admin' => true])->assertRedirect('/account');
        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('alex@example.com', $user->email);
        $this->assertTrue(Hash::check('long-test-password', $user->password));
        $this->assertFalse($user->is_admin);
    }

    public function test_registration_requires_confirmed_strong_password_and_unique_email(): void
    {
        User::factory()->create(['email' => 'alex@example.com']);
        $this->post('/register', ['name' => 'Alex', 'email' => 'alex@example.com', 'password' => 'short', 'password_confirmation' => 'different'])->assertSessionHasErrors(['email', 'password']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => Hash::make('long-test-password')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'long-test-password'])->assertRedirect('/account');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_guests_cannot_access_accounts_admin_or_book(): void
    {
        foreach (['/account', '/admin/appointments'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/book', [])->assertRedirect('/login');
    }

    public function test_customers_only_see_their_own_appointments_even_with_matching_legacy_email(): void
    {
        $owner = User::factory()->create();
        $own = Appointment::factory()->create(['user_id' => $owner->id]);
        $other = Appointment::factory()->create(['email' => $owner->email, 'starts_at' => $own->starts_at->addDays(1)]);
        $this->actingAs($owner)->get('/account')->assertOk()->assertSee($own->reference)->assertDontSee($other->reference);
        $this->get(route('appointments.show', $other))->assertNotFound();
        $this->get(route('appointments.show', $own))->assertOk();
        $this->get('/admin/appointments')->assertForbidden();
    }

    public function test_admin_can_view_all_bookings_and_filter(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $appointment = Appointment::factory()->create(['name' => 'Calendar Guest']);
        $this->actingAs($admin)->get('/admin/appointments')->assertOk()->assertSee($appointment->reference);
        $this->get('/admin/appointments?search=unmatched')->assertOk()->assertDontSee($appointment->reference);
        $this->get(route('appointments.show', $appointment))->assertOk();
    }

    public function test_password_reset_mail_and_single_use_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $data = ['token' => $token, 'email' => $user->email, 'password' => 'replacement-password', 'password_confirmation' => 'replacement-password'];
        $this->post('/reset-password', $data)->assertRedirect('/login');
        $this->assertTrue(Hash::check('replacement-password', $user->fresh()->password));
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
    }

    public function test_admin_promotion_requires_existing_account(): void
    {
        $this->artisan('users:make-admin', ['email' => 'missing@example.com'])->assertFailed();
        $user = User::factory()->create();
        $this->artisan('users:make-admin', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_admin);
    }
}
