<?php

namespace App\Http\Requests\Admin\Project;

use App\Models\Game\Project\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderProjectScreenshotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $project = $this->route('project');

        if ($user === null || ! $project instanceof Project) {
            return false;
        }

        return $user->can('update', $project);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'media_ids' => ['required', 'array'],
            'media_ids.*' => ['integer', 'distinct', Rule::exists('media', 'id')],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $project = $this->route('project');

                if (! $project instanceof Project) {
                    return;
                }

                $requestedMediaIds = collect($this->input('media_ids', []))
                    ->map(fn ($mediaId): int => (int) $mediaId)
                    ->values();
                $projectMediaIds = $project->getMedia('screenshots')
                    ->pluck('id')
                    ->values();

                if (
                    $requestedMediaIds->count() !== $projectMediaIds->count()
                    || $requestedMediaIds->diff($projectMediaIds)->isNotEmpty()
                    || $projectMediaIds->diff($requestedMediaIds)->isNotEmpty()
                ) {
                    $validator->errors()->add(
                        'media_ids',
                        'Порядок должен содержать все скриншоты этого проекта без чужих файлов.'
                    );
                }
            },
        ];
    }
}
