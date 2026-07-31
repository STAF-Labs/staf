<?php

namespace App\Http\Controllers\Api\Admin\ContentType;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentType\StoreContentTypeRequest;
use App\Http\Requests\Admin\ContentType\UpdateContentTypeRequest;
use App\Http\Requests\Admin\ContentType\ValidateContentTypeImportRequest;
use App\Http\Resources\Game\ContentType\ContentTypeResource;
use App\Models\Game\ContentType\ContentType;
use App\Services\Admin\ContentType\ContentTypeImportParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContentTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContentType::query()
            ->with('gameContentTypes.game')
            ->latest('id');

        return response()->json([
            'data' => ContentTypeResource::collection($query->get())->resolve($request),
            'total' => ContentType::query()->count(),
            'filtered_total' => ContentType::query()->count(),
        ]);
    }

    public function store(StoreContentTypeRequest $request): JsonResponse
    {
        $contentType = ContentType::create($request->validated());

        return response()->json(
            ContentTypeResource::make($contentType->load('gameContentTypes.game'))->resolve($request),
            201
        );
    }

    public function update(UpdateContentTypeRequest $request, ContentType $contentType): JsonResponse
    {
        $contentType->update($request->validated());

        return response()->json(
            ContentTypeResource::make($contentType->load('gameContentTypes.game'))->resolve($request)
        );
    }

    public function togglePublic(Request $request, ContentType $contentType): JsonResponse
    {
        $contentType->forceFill([
            'is_public' => ! $contentType->is_public,
        ])->save();

        return response()->json(
            ContentTypeResource::make($contentType->load('gameContentTypes.game'))->resolve($request)
        );
    }

    public function validateImport(
        ValidateContentTypeImportRequest $request,
        ContentTypeImportParser $parser
    ): JsonResponse
    {
        $result = $parser->parse($request->file('file'));

        if (! $result['valid']) {
            throw ValidationException::withMessages([
                'file' => [$result['message']],
            ]);
        }

        return response()->json($result);
    }

    public function import(
        ValidateContentTypeImportRequest $request,
        ContentTypeImportParser $parser
    ): JsonResponse
    {
        $result = $parser->parse($request->file('file'));

        if (! $result['valid']) {
            throw ValidationException::withMessages([
                'file' => [$result['message']],
            ]);
        }

        $importedCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use ($result, &$importedCount, &$skippedCount): void {
            foreach ($result['rows'] as $row) {
                if (ContentType::query()
                    ->whereRaw('lower(name) = ?', [mb_strtolower($row['name'])])
                    ->exists()) {
                    $skippedCount++;

                    continue;
                }

                ContentType::create([
                    'name' => $row['name'],
                    'is_public' => $row['is_public'],
                ]);

                $importedCount++;
            }
        });

        return response()->json([
            'message' => 'Импорт завершен.',
            'total' => count($result['rows']),
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount,
        ]);
    }
}
