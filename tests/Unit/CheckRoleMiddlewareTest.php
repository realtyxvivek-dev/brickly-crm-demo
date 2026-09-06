<?php

namespace Tests\Unit;

use App\Http\Middleware\CheckRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Tests\TestCase;

class CheckRoleMiddlewareTest extends TestCase
{
    public function test_ad_manager_can_access_facebook_lead_ads_integration_routes(): void
    {
        $request = $this->requestForRoute('integrations.facebook-lead-ads.index');
        $request->setUserResolver(fn () => $this->userWithRole(Role::AD_MANAGER));

        $response = (new CheckRole())->handle($request, fn () => new Response('ok'), 'admin');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_ad_manager_cannot_access_other_admin_integration_routes(): void
    {
        $request = $this->requestForRoute('integrations.website.index');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $this->userWithRole(Role::AD_MANAGER));

        $response = (new CheckRole())->handle($request, fn () => new Response('ok'), 'admin');

        $this->assertSame(403, $response->getStatusCode());
    }

    private function requestForRoute(string $routeName): Request
    {
        $request = Request::create('/integrations/facebook-lead-ads', 'GET');
        $route = (new Route('GET', '/integrations/facebook-lead-ads', []))->name($routeName);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = new User();
        $user->setRelation('role', new Role(['slug' => $roleSlug]));

        return $user;
    }
}
