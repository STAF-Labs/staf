<?php

namespace Database\Seeders;

use App\Enums\CommonStatus;
use App\Models\Game\Game;
use Illuminate\Database\Seeder;
use RuntimeException;

class GameSeeder extends Seeder
{
    /**
     * @var list<array{name: string, slug: string, logo: string, banner: string}>
     */
    private const GAMES = [
        [
            'name' => 'S.T.A.L.K.E.R.: Shadow of Chernobyl',
            'slug' => 'stalker-shadow-of-chernobyl',
            'logo' => 'STALKER/Stalker_SoC_logo.jpg',
            'banner' => 'STALKER/stalker_common_banner.jpg',
        ],
        [
            'name' => 'S.T.A.L.K.E.R.: Clear Sky',
            'slug' => 'stalker-clear-sky',
            'logo' => 'STALKER/Stalker_CS_logo.jpg',
            'banner' => 'STALKER/stalker_common_banner.jpg',
        ],
        [
            'name' => 'S.T.A.L.K.E.R.: Call of Pripyat',
            'slug' => 'stalker-call-of-pripyat',
            'logo' => 'STALKER/Stalker_CoP_logo.jpg',
            'banner' => 'STALKER/stalker_common_banner.jpg',
        ],
        [
            'name' => 'Minecraft: Java Edition',
            'slug' => 'minecraft-java-edition',
            'logo' => 'Minecraft/Minecraft_logo.jpg',
            'banner' => 'Minecraft/Minecraft_banner.webp',
        ],
        [
            'name' => 'Minecraft: Bedrock Edition',
            'slug' => 'minecraft-bedrock-edition',
            'logo' => 'Minecraft/Minecraft_bedrock_logo.webp',
            'banner' => 'Minecraft/Minecraft_bedrock_banner.webp',
        ],
        [
            'name' => 'Hytale',
            'slug' => 'hytale',
            'logo' => 'Hytale/Hytale_logo.jpeg',
            'banner' => 'Hytale/Hytale_banner.webp',
        ],
        [
            'name' => 'World of Tanks',
            'slug' => 'world-of-tanks',
            'logo' => 'World Of Tanks/WoT_logo.webp',
            'banner' => 'World Of Tanks/WoT_banner.webp',
        ],
        [
            'name' => 'World of Warcraft: Midnight',
            'slug' => 'world-of-warcraft-midnight',
            'logo' => 'World Of Warcraft/WoW__midnight_logo.jpg',
            'banner' => 'World Of Warcraft/WoW_midnight_banner.webp',
        ],
    ];

    public function run(): void
    {
        foreach (self::GAMES as $data) {
            $game = Game::withTrashed()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'status' => CommonStatus::ACTIVE,
                ]
            );

            if ($game->trashed()) {
                $game->restore();
            }

            $this->syncMedia($game, 'logo', $data['logo']);
            $this->syncMedia($game, 'banner', $data['banner']);
        }
    }

    private function syncMedia(Game $game, string $collection, string $relativePath): void
    {
        $path = database_path('seeders/media/'.$relativePath);

        if (! is_file($path)) {
            throw new RuntimeException("Не найден файл для GameSeeder: {$relativePath}");
        }

        $checksum = hash_file('sha256', $path);

        if ($checksum === false) {
            throw new RuntimeException("Не удалось вычислить checksum: {$relativePath}");
        }

        $currentMedia = $game->getFirstMedia($collection);

        if ($currentMedia?->getCustomProperty('seed_checksum') === $checksum) {
            return;
        }

        $game
            ->addMedia($path)
            ->preservingOriginal()
            ->withCustomProperties(['seed_checksum' => $checksum])
            ->toMediaCollection($collection);
    }
}
