<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PlatformMelding;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MessageTemplateSeeder::class);

        $this->skipUnlessFortifyHas(Features::resetPasswords());
    }

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, PlatformMelding::class, fn ($m) => $m->template->key === 'g-wachtwoord-vergeten');
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, PlatformMelding::class, function ($notification) {
            $response = $this->get(route('password.reset', self::token($notification)));

            $response->assertOk();

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, PlatformMelding::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => self::token($notification),
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            // Direct ingelogd en naar de eigen omgeving (App\Http\Responses\WachtwoordGekozen).
            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('start'));
            $this->assertAuthenticatedAs($user);

            return true;
        });
    }

    public function test_with_two_factor_enabled_the_user_still_logs_in_via_the_login_screen(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_secret' => encrypt('geheim'), 'two_factor_confirmed_at' => now()])->save();
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email, 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    /** Het token uit de link in de mail (g-wachtwoord-vergeten). */
    private static function token(PlatformMelding $melding): string
    {
        return basename(parse_url($melding->url, PHP_URL_PATH));
    }
}
