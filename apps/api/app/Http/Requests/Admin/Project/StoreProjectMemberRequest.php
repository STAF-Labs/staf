<?php

namespace App\Http\Requests\Admin\Project;

use App\Enums\CommonStatus;
use App\Models\Game\Project\Project;
use App\Models\Game\Project\ProjectMember;
use App\Models\User\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectMemberRequest extends FormRequest
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
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('status', CommonStatus::ACTIVE->value),
            ],
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
                $userId = $this->integer('user_id');

                if (! $project instanceof Project || $userId <= 0) {
                    return;
                }

                if (
                    $project->ownerable_type === User::class
                    && (int) $project->ownerable_id === $userId
                ) {
                    $validator->errors()->add('user_id', 'Автор проекта уже является участником.');
                }

                if (
                    ProjectMember::query()
                        ->where('project_id', $project->id)
                        ->where('user_id', $userId)
                        ->exists()
                ) {
                    $validator->errors()->add('user_id', 'Пользователь уже добавлен в проект.');
                }
            },
        ];
    }
}
