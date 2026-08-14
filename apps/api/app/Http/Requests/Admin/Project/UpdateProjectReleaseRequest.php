<?php

namespace App\Http\Requests\Admin\Project;

use App\Enums\Project\ProjectReleaseType;
use App\Http\Requests\Admin\Project\Concerns\ValidatesProjectReleaseDimensions;
use App\Models\Game\Project\Project;
use App\Rules\FilledTipTapDocument;
use App\Services\Admin\Project\ProjectUploadLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectReleaseRequest extends FormRequest
{
    use ValidatesProjectReleaseDimensions;

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
            'file' => [
                'nullable',
                'file',
            ],
            'title' => ['required', 'string', 'max:64'],
            'type' => ['required', Rule::enum(ProjectReleaseType::class)],
            'changelog' => ['required', 'array', new FilledTipTapDocument],
            'dimension_value_ids' => ['nullable', 'array'],
            'dimension_value_ids.*' => ['integer', 'distinct', Rule::exists('dimension_values', 'id')],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateReleaseDimensionValues($validator);
            },
            function (Validator $validator): void {
                $this->validateUploadTotalSize($validator);
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareReleasePayloadForValidation();
    }

    private function validateUploadTotalSize(Validator $validator): void
    {
        $limits = app(ProjectUploadLimits::class);

        if ($limits->exceedsLimit($this, ['file'])) {
            $validator->errors()->add('file', $limits->errorMessage());
        }
    }
}
