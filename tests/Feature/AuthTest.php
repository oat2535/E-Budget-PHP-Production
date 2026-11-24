<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_all_routes_have_existing_controller_methods(): void
    {
        $routes = Route::getRoutes();
        $missing = [];

        foreach ($routes as $route) {
            $action = $route->getActionName();
            if (str_contains($action, '@')) {
                [$class, $method] = explode('@', $action);
                if (! class_exists($class)) {
                    $missing[] = "Controller class {$class} does not exist for URI: {$route->uri()}";
                } elseif (! method_exists($class, $method)) {
                    $missing[] = "Method {$method} does not exist on {$class} for URI: {$route->uri()}";
                }
            }
        }

        $this->assertEmpty($missing, implode("\n", $missing));
    }

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Employee ID');
        $response->assertSee('password');
        $response->assertSee('Login');
    }

    public function test_authenticated_user_accessing_login_is_redirected(): void
    {
        $user = new User(['username' => 'test_officer']);
        $user->is_superuser = false;

        $response = $this->actingAs($user)->get('/login');
        $response->assertRedirect(route('budget_list'));
    }

    public function test_login_with_empty_credentials_returns_error(): void
    {
        $response = $this->post('/login', [
            'username' => '',
            'password' => '',
        ]);

        $response->assertSessionHas('error', 'กรุณากรอก Username และ Password');
    }

    public function test_login_page_with_session_expired_shows_warning(): void
    {
        $response = $this->get('/login?session_expired=1');
        $response->assertStatus(200);
        $response->assertSee('เซสชันหมดอายุ');
        $response->assertSee('ท่านไม่ได้ทำรายการหรือไม่มีการเคลื่อนไหวบนหน้าจอเกิน 1 ชั่วโมง');
    }

    public function test_authenticated_user_accessing_login_with_session_expired_is_logged_out(): void
    {
        $user = new User(['username' => 'test_officer']);
        $user->is_superuser = false;

        $response = $this->actingAs($user)->get('/login?session_expired=1');
        $response->assertStatus(200);
        $this->assertGuest();
        $response->assertSee('ท่านไม่ได้ทำรายการหรือไม่มีการเคลื่อนไหวบนหน้าจอเกิน 1 ชั่วโมง');
    }

    public function test_logout_with_session_expired_redirects_to_login_with_query(): void
    {
        $user = new User(['username' => 'test_officer']);
        $user->is_superuser = false;

        $response = $this->actingAs($user)->get('/logout?session_expired=1');
        $response->assertRedirect(route('login', ['session_expired' => 1]));
        $this->assertGuest();
    }

    public function test_imedx_first_time_login_creates_local_auth_user(): void
    {
        // Test that User model creation with default attributes satisfies all auth_user not-null constraints
        User::where('username', 'test_new_user_audit')->delete();

        $user = new User();
        $user->username = 'test_new_user_audit';
        $user->first_name = 'Test';
        $user->last_name = 'User';
        $user->save();

        $this->assertNotNull($user->id);
        $this->assertEquals('!', $user->password);
        $this->assertNotNull($user->date_joined);
        $this->assertEquals('', $user->email);

        $user->delete();

        // If running against UAT database where ratchadaporn_p has password 1234 and active profile
        $emp = \Illuminate\Support\Facades\DB::connection('pgsql_imedx')->selectOne("
            SELECT password, bank_sub_name, bank_account_note, active FROM employee WHERE LOWER(TRIM(employee_id)) = 'ratchadaporn_p'
        ");

        if ($emp && strtolower(trim((string)$emp->password)) === md5('1234') && !empty($emp->bank_sub_name) && !empty($emp->bank_account_note)) {
            User::where('username', 'ratchadaporn_p')->delete();

            $response = $this->post('/login', [
                'username' => 'ratchadaporn_p',
                'password' => '1234',
            ]);

            $response->assertRedirect(route('budget_list'));
            $this->assertAuthenticated();

            $saved = User::where('username', 'ratchadaporn_p')->first();
            $this->assertNotNull($saved);
            $this->assertEquals('ratchadaporn_p', $saved->username);
            $this->assertNotNull($saved->last_login);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $saved->last_login->format('Y-m-d H:i:s'));
        }
    }

    public function test_login_event_updates_last_login_timestamp_zero(): void
    {
        $admin = User::where('username', 'admin')->first();
        if ($admin) {
            $admin->last_login = null;
            $admin->saveQuietly();

            event(new \Illuminate\Auth\Events\Login('web', $admin, false));

            $admin->refresh();
            $this->assertNotNull($admin->last_login);
            $formatted = $admin->last_login->format('Y-m-d H:i:s');
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $formatted);
        }
    }
}
