<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_applied_to_web_and_api_responses(): void
    {
        foreach (['/', '/up'] as $url) {
            $this->get($url)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()')
                ->assertHeader('Content-Security-Policy-Report-Only')
                ->assertHeaderMissing('Content-Security-Policy')
                ->assertHeaderMissing('Strict-Transport-Security');
        }
    }

    public function test_content_security_policy_can_be_enforced_after_report_only_rollout(): void
    {
        config()->set('security.content_security_policy.report_only', false);

        $response = $this->get('/up')
            ->assertHeader('Content-Security-Policy')
            ->assertHeaderMissing('Content-Security-Policy-Report-Only');

        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString('https://maps.googleapis.com', $policy);
        $this->assertMatchesRegularExpression("/script-src [^;]*'nonce-[A-Za-z0-9+\\/=]+'/", $policy);
    }

    public function test_content_security_policy_nonce_is_available_to_rendered_views(): void
    {
        Route::get('/_test/csp-nonce', fn () => response((string) request()->attributes->get('csp_nonce')));

        $response = $this->get('/_test/csp-nonce')->assertOk();
        $policy = (string) $response->headers->get('Content-Security-Policy-Report-Only');

        preg_match("/'nonce-([^']+)'/", $policy, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $response->assertContent($matches[1]);
    }

    public function test_production_https_responses_enable_hsts(): void
    {
        $this->app['env'] = 'production';
        config()->set('app.url', 'https://dancepro.example');

        $this->get('/up')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
