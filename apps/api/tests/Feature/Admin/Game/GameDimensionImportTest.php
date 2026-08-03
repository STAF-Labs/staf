<?php

namespace Tests\Feature\Admin\Game;

use App\Models\Game\ContentType\ContentType;
use App\Models\Game\ContentType\GameContentType;
use App\Models\Game\Game;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class GameDimensionImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_validate_and_import_dimensions_with_hierarchy(): void
    {
        [$user, $game, $gameContentType] = $this->context();
        $baseUrl = "/api/games/{$game->id}/content-types/{$gameContentType->id}/dimensions/import";

        $this
            ->actingAs($user)
            ->postJson("{$baseUrl}/validate", [
                'file' => $this->validSpreadsheet(),
            ])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonCount(2, 'filters')
            ->assertJsonCount(4, 'values');

        $this
            ->actingAs($user)
            ->postJson($baseUrl, [
                'file' => $this->validSpreadsheet(),
            ])
            ->assertOk()
            ->assertJsonPath('created_filters', 2)
            ->assertJsonPath('reused_filters', 0)
            ->assertJsonPath('created_values', 4)
            ->assertJsonPath('skipped_values', 0);

        $this->assertDatabaseHas('dimensions', [
            'game_content_type_id' => $gameContentType->id,
            'name' => 'Платформа',
            'selection_mode' => 'multiple',
            'applies_to' => 'release',
            'is_required' => false,
        ]);
        $this->assertDatabaseHas('dimension_values', [
            'name' => 'Windows',
            'sort_order' => 10,
        ]);

        $transport = $gameContentType->dimensions()
            ->where('name', 'Категория')
            ->firstOrFail()
            ->values()
            ->where('name', 'Транспорт')
            ->firstOrFail();

        $this->assertDatabaseHas('dimension_values', [
            'name' => 'Автомобили',
            'parent_id' => $transport->id,
        ]);
    }

    public function test_reimport_only_adds_missing_records(): void
    {
        [$user, $game, $gameContentType] = $this->context();
        $url = "/api/games/{$game->id}/content-types/{$gameContentType->id}/dimensions/import";

        $this->actingAs($user)->postJson($url, ['file' => $this->validSpreadsheet()])->assertOk();

        $this
            ->actingAs($user)
            ->postJson($url, ['file' => $this->validSpreadsheet()])
            ->assertOk()
            ->assertJsonPath('created_filters', 0)
            ->assertJsonPath('reused_filters', 2)
            ->assertJsonPath('created_values', 0)
            ->assertJsonPath('skipped_values', 4);

        $this->assertDatabaseCount('dimensions', 2);
        $this->assertDatabaseCount('dimension_values', 4);
    }

    public function test_import_rejects_missing_values_sheet(): void
    {
        [$user, $game, $gameContentType] = $this->context();
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('filters');
        $file = $this->uploadedSpreadsheet($spreadsheet, 'missing-values.xlsx');

        $this
            ->actingAs($user)
            ->postJson(
                "/api/games/{$game->id}/content-types/{$gameContentType->id}/dimensions/import/validate",
                ['file' => $file]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_import_rejects_another_game_context(): void
    {
        [$user, $game, $gameContentType] = $this->context();
        $anotherGame = Game::query()->create([
            'name' => 'Another Game',
            'status' => 'active',
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/games/{$anotherGame->id}/content-types/{$gameContentType->id}/dimensions/import/validate",
                ['file' => $this->validSpreadsheet()]
            )
            ->assertNotFound();
    }

    private function validSpreadsheet(): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $filters = $spreadsheet->getActiveSheet();
        $filters->setTitle('filters');
        $filters->fromArray([
            ['filter_key', 'name', 'selection_mode', 'applies_to', 'is_filterable', 'is_required', 'is_active'],
            ['platform', 'Платформа', 'multiple', 'release', 'true', 'false', 'true'],
            ['category', 'Категория', 'multiple', 'project', 'true', 'false', 'true'],
        ]);
        $values = $spreadsheet->createSheet();
        $values->setTitle('values');
        $values->fromArray([
            ['filter_key', 'value_key', 'name', 'parent_key', 'sort_order', 'is_active'],
            ['platform', 'windows', 'Windows', '', '10', 'true'],
            ['platform', 'linux', 'Linux', '', '20', 'true'],
            ['category', 'transport', 'Транспорт', '', '10', 'true'],
            ['category', 'cars', 'Автомобили', 'transport', '20', 'true'],
        ]);

        return $this->uploadedSpreadsheet($spreadsheet, 'filters.xlsx');
    }

    private function uploadedSpreadsheet(Spreadsheet $spreadsheet, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'dimension-import-');

        if ($path === false) {
            $this->fail('Не удалось создать временный файл для XLSX.');
        }

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    /** @return array{User, Game, GameContentType} */
    private function context(): array
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $game = Game::query()->create([
            'name' => 'Filter Game',
            'status' => 'active',
        ]);
        $contentType = ContentType::query()->create([
            'name' => 'Модификации',
            'is_public' => true,
        ]);
        $gameContentType = $game->gameContentTypes()->create([
            'content_type_id' => $contentType->id,
        ]);

        return [$user, $game, $gameContentType];
    }
}
