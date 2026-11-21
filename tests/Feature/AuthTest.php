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
}
