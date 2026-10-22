<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_send_contact_message(): void
    {
        $response = $this->postJson('/api/publik/kontak', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'message' => 'Halo, apakah kafe bisa disewa untuk acara komunitas?',
        ]);

        $response->assertCreated()
            ->assertJson(['message' => 'Pesan terkirim']);

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'message' => 'Halo, apakah kafe bisa disewa untuk acara komunitas?',
        ]);
    }

    public function test_honeypot_spam_is_rejected(): void
    {
        $response = $this->postJson('/api/publik/kontak', [
            'name' => 'Spammer',
            'email' => 'spam@example.com',
            'message' => 'Buy cheap links now',
            'website' => 'http://spamsite.test',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Spam detected']);

        $this->assertDatabaseMissing('contact_messages', [
            'email' => 'spam@example.com',
        ]);
    }

    public function test_validation_requires_all_fields(): void
    {
        $response = $this->postJson('/api/publik/kontak', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'message']);
    }
}
