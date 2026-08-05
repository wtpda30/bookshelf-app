<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 会員登録画面を表示できること
     */
    public function test_guest_can_display_register_page(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertViewIs('auth.register');
    }

    /**
     * 正常な情報でユーザー登録できること
     */
    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(RouteServiceProvider::HOME);

        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
        ]);
    }

    /**
     * 名前が未入力の場合は登録できないこと
     */
    public function test_name_is_required_when_registering(): void
    {
        $response = $this
            ->from('/register')
            ->post('/register', [
                'name' => '',
                'email' => 'test@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('name');

        $this->assertGuest();

        $this->assertDatabaseMissing('users', [
            'email' => 'test@example.com',
        ]);
    }

    /**
     * メールアドレスが未入力の場合は登録できないこと
     */
    public function test_email_is_required_when_registering(): void
    {
        $response = $this
            ->from('/register')
            ->post('/register', [
                'name' => 'テストユーザー',
                'email' => '',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * 不正なメールアドレス形式では登録できないこと
     */
    public function test_email_must_be_valid_when_registering(): void
    {
        $response = $this
            ->from('/register')
            ->post('/register', [
                'name' => 'テストユーザー',
                'email' => 'invalid-email',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * 登録済みメールアドレスでは登録できないこと
     */
    public function test_duplicate_email_cannot_be_registered(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this
            ->from('/register')
            ->post('/register', [
                'name' => '別のユーザー',
                'email' => 'test@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->assertDatabaseCount('users', 1);
    }

    /**
     * パスワードが未入力の場合は登録できないこと
     */
    public function test_password_is_required_when_registering(): void
    {
        $response = $this
            ->from('/register')
            ->post('/register', [
                'name' => 'テストユーザー',
                'email' => 'test@example.com',
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    /**
     * パスワード確認が一致しない場合は登録できないこと
     */
    public function test_password_confirmation_must_match(): void
    {
        $response = $this
            ->from('/register')
            ->post('/register', [
                'name' => 'テストユーザー',
                'email' => 'test@example.com',
                'password' => 'password',
                'password_confirmation' => 'different-password',
            ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('password');

        $this->assertGuest();

        $this->assertDatabaseMissing('users', [
            'email' => 'test@example.com',
        ]);
    }

    /**
     * ログイン画面を表示できること
     */
    public function test_guest_can_display_login_page(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertViewIs('auth.login');
    }

    /**
     * 正しいメールアドレスとパスワードでログインできること
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(RouteServiceProvider::HOME);

        $this->assertAuthenticatedAs($user);
    }

    /**
     * 存在しないメールアドレスではログインできないこと
     */
    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        $response = $this
            ->from('/login')
            ->post('/login', [
                'email' => 'not-found@example.com',
                'password' => 'password',
            ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * 誤ったパスワードではログインできないこと
     */
    public function test_user_cannot_login_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this
            ->from('/login')
            ->post('/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * ログイン中のユーザーがログアウトできること
     */
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/logout');

        $this->assertGuest();

        $response->assertRedirect('/login');
    }
}
