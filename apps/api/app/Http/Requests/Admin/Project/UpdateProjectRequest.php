<?php

namespace App\Http\Requests\Admin\Project;

use App\Enums\MembershipStatus;
use App\Enums\Project\ProjectStatus;
use App\Models\Org\Organization;
use App\Models\Org\OrganizationMember;
use App\Models\User\User;
use App\Services\Admin\Licence\SpdxLicenceCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(SpdxLicenceCatalog $licenceCatalog): array
    {
        return [
            'ownerable_type' => ['required', 'string', Rule::in([User::class, Organization::class])],
            'ownerable_id' => ['required', 'integer'],
            'game_content_type_id' => ['required', 'integer', Rule::exists('game_content_types', 'id')],
            'title' => ['required', 'string', 'max:128'],
            'slug' => ['nullable', 'string', 'max:128', Rule::unique('projects', 'slug')->ignore($this->route('project'))],
            'summary' => ['nullable', 'array'],
            'description' => ['required', 'array'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'min:3'],
            'website_urls' => ['nullable', 'array'],
            'website_urls.*' => ['url', 'starts_with:https://'],
            'licence_name' => ['nullable', 'string', 'max:128', Rule::in($licenceCatalog->identifiers())],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'logo' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:'.implode(',', StoreProjectRequest::imageMimeTypes()),
                'max:'.StoreProjectRequest::LOGO_MAX_KILOBYTES,
                Rule::dimensions()
                    ->maxWidth(StoreProjectRequest::LOGO_MAX_WIDTH)
                    ->maxHeight(StoreProjectRequest::LOGO_MAX_HEIGHT),
            ],
            'screenshots' => ['nullable', 'array', 'max:'.StoreProjectRequest::SCREENSHOTS_MAX_COUNT],
            'screenshots.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:'.implode(',', StoreProjectRequest::imageMimeTypes()),
                'max:'.StoreProjectRequest::SCREENSHOT_MAX_KILOBYTES,
                Rule::dimensions()
                    ->maxWidth(StoreProjectRequest::SCREENSHOT_MAX_WIDTH)
                    ->maxHeight(StoreProjectRequest::SCREENSHOT_MAX_HEIGHT),
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
                $ownerableType = $this->string('ownerable_type')->toString();
                $ownerableId = $this->integer('ownerable_id');

                if (! in_array($ownerableType, [User::class, Organization::class], true)) {
                    return;
                }

                if ($ownerableType === User::class && $ownerableId !== $this->user()?->id) {
                    $validator->errors()->add('ownerable_id', 'Можно выбрать только текущего пользователя.');

                    return;
                }

                if ($ownerableType === Organization::class && ! OrganizationMember::query()
                    ->where('organization_id', $ownerableId)
                    ->where('user_id', $this->user()?->id)
                    ->where('status', MembershipStatus::ACTIVE)
                    ->exists()) {
                    $validator->errors()->add('ownerable_id', 'Организация не найдена среди доступных владельцев.');

                    return;
                }

                if (! $ownerableType::query()->whereKey($ownerableId)->exists()) {
                    $validator->errors()->add('ownerable_id', 'Владелец не найден.');
                }
            },
        ];
    }
}
