<?php

namespace Tests\Feature\Admin\Licence;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_current_spdx_licences(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/licences')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'MIT',
                'name' => 'MIT License',
                'is_osi_approved' => true,
                'reference_url' => 'https://spdx.org/licenses/MIT.html',
            ])
            ->assertJsonFragment([
                'id' => 'LicenseRef-Custom',
                'name' => 'Собственная лицензия',
                'is_osi_approved' => false,
                'reference_url' => null,
            ])
            ->assertJsonMissing([
                'id' => 'AGPL-1.0',
            ]);

        $this->assertSame(count($response->json('data')), $response->json('total'));
        $this->assertGreaterThan(500, $response->json('total'));
    }

    public function test_guest_cannot_list_licences(): void
    {
        $this->getJson('/api/licences')->assertUnauthorized();
    }
}
