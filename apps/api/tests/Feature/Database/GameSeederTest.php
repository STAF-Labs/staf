<?php

namespace Tests\Feature\Database;

use App\Models\Game\Game;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class GameSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_games_with_logo_and_banner_idempotently(): void
    {
        Storage::fake('public');

        $this->seed(GameSeeder::class);
        $this->seed(GameSeeder::class);

        $this->assertDatabaseCount('games', 8);
        $this->assertDatabaseCount('media', 16);

        Game::query()->each(function (Game $game): void {
            $this->assertTrue($game->hasMedia('logo'));
            $this->assertTrue($game->hasMedia('banner'));
            $this->assertSame('public', $game->getFirstMedia('logo')?->disk);
            $this->assertSame('public', $game->getFirstMedia('banner')?->disk);
        });

        $this->assertSame(
            8,
            Media::query()->where('collection_name', 'logo')->count()
        );
        $this->assertSame(
            8,
            Media::query()->where('collection_name', 'banner')->count()
        );
        $this->assertFileExists(
            database_path('seeders/media/STALKER/stalker_common_banner.jpg')
        );
    }
}
