<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Cuisine;
use App\Models\CulturalCategory;
use App\Models\CulturalRegion;
use App\Models\Dish;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CulturalCatalogController extends Controller
{
    private const TYPES = [
        'categories' => CulturalCategory::class,
        'regions' => CulturalRegion::class,
        'countries' => Country::class,
        'cuisines' => Cuisine::class,
        'dishes' => Dish::class,
    ];

    public function store(Request $request, string $type): JsonResponse
    {
        $model = $this->model($request, $type);
        $data = $this->validated($request, $type);
        if ($type === 'categories') {
            $data['id'] ??= Str::slug($data['name']);
        } elseif ($type === 'regions') {
            $data['id'] ??= Str::slug($data['cultural_category_id'] . '-' . $data['name']);
        } elseif ($type === 'cuisines') {
            $data['id'] ??= Str::slug($data['country_id'] . '-' . $data['name']);
        }
        if ($type === 'countries') {
            $regionIds = $data['region_ids'];
            unset($data['region_ids']);
        }
        $record = $model::create($data);
        if ($type === 'countries') $record->regions()->sync($regionIds);

        return response()->json(['data' => $record->fresh()], 201);
    }

    public function update(Request $request, string $type, string $id): JsonResponse
    {
        $model = $this->model($request, $type);
        $record = $model::query()->findOrFail($id);
        $data = $this->validated($request, $type, true);
        if ($type === 'countries' && array_key_exists('region_ids', $data)) {
            $regionIds = $data['region_ids'];
            unset($data['region_ids']);
            $record->regions()->sync($regionIds);
        }
        $record->fill($data)->save();

        return response()->json(['data' => $record->fresh()]);
    }

    public function deactivate(Request $request, string $type, string $id): JsonResponse
    {
        $model = $this->model($request, $type);
        $record = $model::query()->findOrFail($id);
        $record->forceFill(['is_active' => false])->save();

        return response()->json(['data' => $record->fresh()]);
    }

    private function model(Request $request, string $type): string
    {
        abort_unless($request->user()?->hasRole(AccountType::SYSTEM_ADMIN), 403, 'Only System Administrators may maintain the cultural catalog.');
        abort_unless(isset(self::TYPES[$type]), 404);
        return self::TYPES[$type];
    }

    private function validated(Request $request, string $type, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';
        $rules = [
            'categories' => ['id' => [$updating ? 'prohibited' : 'sometimes', 'string', 'max:48'], 'name' => [$required, 'string', 'max:120'], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean']],
            'regions' => ['id' => [$updating ? 'prohibited' : 'sometimes', 'string', 'max:64'], 'cultural_category_id' => [$required, 'exists:cultural_categories,id'], 'name' => [$required, 'string', 'max:120'], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean']],
            'countries' => ['id' => [$updating ? 'prohibited' : 'required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'], 'name' => [$required, 'string', 'max:120'], 'flag_emoji' => ['nullable', 'string', 'max:8'], 'cuisine_image_url' => ['nullable', 'url', 'max:500'], 'region_ids' => [$required, 'array', 'min:1', 'max:20'], 'region_ids.*' => ['string', 'exists:cultural_regions,id'], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean']],
            'cuisines' => ['id' => ['sometimes', 'string', 'max:80'], 'country_id' => [$required, 'exists:countries,id'], 'name' => [$required, 'string', 'max:160'], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean']],
            'dishes' => ['cuisine_id' => [$required, 'exists:cuisines,id'], 'name' => [$required, 'string', 'max:160'], 'alternative_spellings' => ['sometimes', 'array', 'max:10'], 'alternative_spellings.*' => ['string', 'max:160'], 'is_fusion' => ['sometimes', 'boolean'], 'dietary_type' => ['sometimes', Rule::in(['any', 'vegetarian', 'vegan'])], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean']],
        ];

        return $request->validate($rules[$type]);
    }
}
