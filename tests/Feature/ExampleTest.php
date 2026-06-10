<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_is_available(): void
    {
        $this->get('/login')->assertOk()->assertSee('LOGIN / MASUK SISTEM');
    }
}
