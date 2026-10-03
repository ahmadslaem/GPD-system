<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        // الصفحة الرئيسية تحوّل إلى لوحة التحكم (frontend/dashboard/index.html)
        $response->assertRedirect('/frontend/dashboard/index.html');
    }
}
