<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CulturalCategory;
use Illuminate\Http\JsonResponse;

class CulturalCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $catalog = CulturalCategory::query()
            ->where('is_active', true)
            ->with(['regions.countries.cuisines.dishes'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CulturalCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'regions' => $category->regions->map(fn ($region) => [
                    'id' => $region->id,
                    'name' => $region->name,
                    'countries' => $region->countries->map(fn ($country) => [
                        'id' => $country->id,
                        'name' => $country->name,
                        'flag' => $country->flag_emoji,
                        'imageUrl' => $country->cuisine_image_url,
                        'cuisines' => $country->cuisines->map(fn ($cuisine) => [
                            'id' => $cuisine->id,
                            'name' => $cuisine->name,
                            'dishes' => $cuisine->dishes->map(fn ($dish) => [
                                'id' => $dish->id,
                                'name' => $dish->name,
                                'alternativeSpellings' => $dish->alternative_spellings ?? [],
                                'isFusion' => (bool) $dish->is_fusion,
                                'dietaryType' => $dish->dietary_type,
                            ])->values(),
                        ])->values(),
                    ])->values(),
                ])->values(),
            ])->values();

        return response()->json(['data' => $catalog]);
    }
}
