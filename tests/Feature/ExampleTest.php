<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_halaman_login_staf_dapat_diakses(): void
    {
        $this->withoutVite();

        $response = $this->get('/login');

        $response->assertStatus(200);
    }
}
