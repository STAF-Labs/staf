<?php

namespace Tests\Feature\Admin\ContentType;

use App\Models\Game\ContentType\ContentType;
use App\Models\Game\ContentType\GameContentType;
use App\Models\Game\Game;
use App\Models\Game\Project\Project;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ContentTypeImportValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_csv_import_file_passes_validation(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'content-types.csv',
            "name,is_public\nМод,да\nКарта,true\n"
        );

        $this
            ->actingAs($user)
            ->postJson('/api/content-types/import/validate', [
                'file' => $file,
            ])
            ->assertOk()
            ->assertJsonPath('valid', true);
    }

    public function test_invalid_csv_import_header_fails_validation(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'content-types.csv',
            "title,public\nМод,да\n"
        );

        $this
            ->actingAs($user)
            ->postJson('/api/content-types/import/validate', [
                'file' => $file,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_valid_xlsx_import_file_passes_validation(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $file = $this->fakeSpreadsheet('content-types.xlsx', ['name', 'is_public'], ['Мод', 'да']);

        $this
            ->actingAs($user)
            ->postJson('/api/content-types/import/validate', [
                'file' => $file,
            ])
            ->assertOk()
            ->assertJsonPath('valid', true);
    }

    public function test_invalid_xlsx_import_header_fails_validation(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $file = $this->fakeSpreadsheet('content-types.xlsx', ['title', 'public'], ['Мод', 'да']);

        $this
            ->actingAs($user)
            ->postJson('/api/content-types/import/validate', [
                'file' => $file,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_admin_can_import_content_types_from_file(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'content-types.csv',
            "name,is_public\nМод,да\nПлагин,нет\n"
        );

        $this
            ->actingAs($user)
            ->postJson('/api/content-types/import', [
                'file' => $file,
            ])
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('imported_count', 2)
            ->assertJsonPath('skipped_count', 0);

        $this->assertDatabaseHas('content_types', [
            'name' => 'Мод',
            'is_public' => true,
        ]);
        $this->assertDatabaseHas('content_types', [
            'name' => 'Плагин',
            'is_public' => false,
        ]);
    }

    public function test_import_skips_existing_content_types(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        ContentType::query()->create([
            'name' => 'Мод',
            'is_public' => true,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'content-types.csv',
            "name,is_public\nМод,да\nКарта,true\n"
        );

        $this
            ->actingAs($user)
            ->postJson('/api/content-types/import', [
                'file' => $file,
            ])
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('imported_count', 1)
            ->assertJsonPath('skipped_count', 1);

        $this->assertDatabaseCount('content_types', 2);
        $this->assertDatabaseHas('content_types', [
            'name' => 'Карта',
            'is_public' => true,
        ]);
    }

    public function test_admin_can_toggle_content_type_public_visibility(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $contentType = ContentType::query()->create([
            'name' => 'Мод',
            'is_public' => true,
        ]);

        $this
            ->actingAs($user)
            ->patchJson("/api/content-types/{$contentType->id}/toggle-public")
            ->assertOk()
            ->assertJsonPath('id', $contentType->id)
            ->assertJsonPath('is_public', false);

        $this->assertDatabaseHas('content_types', [
            'id' => $contentType->id,
            'is_public' => false,
        ]);
    }

    public function test_admin_can_update_content_type(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $contentType = ContentType::query()->create([
            'name' => 'Мод',
            'is_public' => true,
        ]);

        $this
            ->actingAs($user)
            ->patchJson("/api/content-types/{$contentType->id}", [
                'name' => 'Модификация',
                'is_public' => false,
            ])
            ->assertOk()
            ->assertJsonPath('id', $contentType->id)
            ->assertJsonPath('name', 'Модификация')
            ->assertJsonPath('is_public', false);

        $this->assertDatabaseHas('content_types', [
            'id' => $contentType->id,
            'name' => 'Модификация',
            'is_public' => false,
        ]);
    }

    public function test_admin_can_delete_content_type(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $contentType = ContentType::query()->create([
            'name' => 'Мод',
            'is_public' => true,
        ]);

        $this
            ->actingAs($user)
            ->deleteJson("/api/content-types/{$contentType->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Тип контента удален.');

        $this->assertDatabaseMissing('content_types', [
            'id' => $contentType->id,
        ]);
    }

    public function test_admin_cannot_delete_content_type_used_by_project(): void
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $game = Game::query()->create([
            'name' => 'STAF',
            'status' => 'active',
        ]);
        $contentType = ContentType::query()->create([
            'name' => 'Мод',
            'is_public' => true,
        ]);
        $gameContentType = GameContentType::query()->create([
            'game_id' => $game->id,
            'content_type_id' => $contentType->id,
        ]);
        Project::query()->create([
            'ownerable_type' => User::class,
            'ownerable_id' => $user->id,
            'game_content_type_id' => $gameContentType->id,
            'title' => 'Проект',
            'description' => [],
        ]);

        $this
            ->actingAs($user)
            ->deleteJson("/api/content-types/{$contentType->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'Нельзя удалить тип контента, который используется в проектах.');

        $this->assertDatabaseHas('content_types', [
            'id' => $contentType->id,
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $values
     */
    private function fakeSpreadsheet(string $name, array $headers, array $values): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'1', $header);
        }

        foreach ($values as $index => $value) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'2', $value);
        }

        $path = tempnam(sys_get_temp_dir(), 'content-type-import-');

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
}
