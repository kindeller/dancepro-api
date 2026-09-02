<?php

namespace Tests\Feature\Security;

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
                ->assertHeaderMissing('Strict-Transport-Security');
        }
    }

    public function test_production_https_responses_enable_hsts(): void
    {
        $this->app['env'] = 'production';
        config()->set('app.url', 'https://dancepro.example');

        $this->get('/up')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
