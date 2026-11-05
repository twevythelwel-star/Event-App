<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Cuisine;
use App\Models\CulturalCategory;
use App\Models\CulturalRegion;
use App\Models\Dish;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CulturalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'caribbean' => ['Caribbean', [
                'greater-antilles' => ['Greater Antilles', [
                    'JM' => ['Jamaica', '🇯🇲'], 'CU' => ['Cuba', '🇨🇺'], 'HT' => ['Haiti', '🇭🇹'],
                    'DO' => ['Dominican Republic', '🇩🇴'], 'PR' => ['Puerto Rico', '🇵🇷'],
                ]],
                'lesser-antilles' => ['Lesser Antilles', [
                    'AG' => ['Antigua and Barbuda', '🇦🇬'], 'BB' => ['Barbados', '🇧🇧'], 'DM' => ['Dominica', '🇩🇲'],
                    'GD' => ['Grenada', '🇬🇩'], 'LC' => ['Saint Lucia', '🇱🇨'], 'VC' => ['Saint Vincent and the Grenadines', '🇻🇨'],
                    'AW' => ['Aruba', '🇦🇼'], 'CW' => ['Curaçao', '🇨🇼'], 'GP' => ['Guadeloupe', '🇬🇵'],
                    'MQ' => ['Martinique', '🇲🇶'], 'SX' => ['Sint Maarten', '🇸🇽'],
                ]],
                'eastern-caribbean' => ['Eastern Caribbean', [
                    'KN' => ['Saint Kitts and Nevis', '🇰🇳'], 'TT' => ['Trinidad and Tobago', '🇹🇹'],
                ]],
                'western-caribbean' => ['Western Caribbean', [
                    'BZ' => ['Belize', '🇧🇿'], 'KY' => ['Cayman Islands', '🇰🇾'], 'JM' => ['Jamaica', '🇯🇲'],
                ]],
                'northern-caribbean' => ['Northern Caribbean', [
                    'BS' => ['The Bahamas', '🇧🇸'], 'TC' => ['Turks and Caicos Islands', '🇹🇨'],
                ]],
                'southern-caribbean' => ['Southern Caribbean', [
                    'GY' => ['Guyana', '🇬🇾'], 'SR' => ['Suriname', '🇸🇷'], 'TT' => ['Trinidad and Tobago', '🇹🇹'],
                ]],
            ]],
            'african' => ['African', [
                'west-africa' => ['West Africa', ['NG' => ['Nigeria', '🇳🇬'], 'GH' => ['Ghana', '🇬🇭'], 'SN' => ['Senegal', '🇸🇳'], 'CI' => ["Côte d’Ivoire", '🇨🇮'], 'GM' => ['The Gambia', '🇬🇲'], 'GN' => ['Guinea', '🇬🇳'], 'LR' => ['Liberia', '🇱🇷'], 'SL' => ['Sierra Leone', '🇸🇱'], 'TG' => ['Togo', '🇹🇬'], 'BJ' => ['Benin', '🇧🇯'], 'BF' => ['Burkina Faso', '🇧🇫'], 'ML' => ['Mali', '🇲🇱']]],
                'east-africa' => ['East Africa', ['ET' => ['Ethiopia', '🇪🇹'], 'KE' => ['Kenya', '🇰🇪'], 'TZ' => ['Tanzania', '🇹🇿'], 'UG' => ['Uganda', '🇺🇬'], 'RW' => ['Rwanda', '🇷🇼'], 'SO' => ['Somalia', '🇸🇴'], 'ER' => ['Eritrea', '🇪🇷'], 'MG' => ['Madagascar', '🇲🇬']]],
                'central-africa' => ['Central Africa', ['CM' => ['Cameroon', '🇨🇲'], 'CD' => ['Democratic Republic of the Congo', '🇨🇩'], 'CG' => ['Republic of the Congo', '🇨🇬'], 'GA' => ['Gabon', '🇬🇦'], 'CF' => ['Central African Republic', '🇨🇫'], 'TD' => ['Chad', '🇹🇩'], 'AO' => ['Angola', '🇦🇴']]],
                'north-africa' => ['North Africa', ['EG' => ['Egypt', '🇪🇬'], 'MA' => ['Morocco', '🇲🇦'], 'DZ' => ['Algeria', '🇩🇿'], 'TN' => ['Tunisia', '🇹🇳'], 'LY' => ['Libya', '🇱🇾'], 'SD' => ['Sudan', '🇸🇩']]],
                'southern-africa' => ['Southern Africa', ['ZA' => ['South Africa', '🇿🇦'], 'ZW' => ['Zimbabwe', '🇿🇼'], 'MZ' => ['Mozambique', '🇲🇿'], 'BW' => ['Botswana', '🇧🇼'], 'NA' => ['Namibia', '🇳🇦'], 'ZM' => ['Zambia', '🇿🇲'], 'LS' => ['Lesotho', '🇱🇸'], 'MW' => ['Malawi', '🇲🇼']]],
            ]],
            'north-american' => ['North American', [
                'north-america' => ['North America', ['US' => ['United States', '🇺🇸'], 'CA' => ['Canada', '🇨🇦']]],
            ]],
            'latin-american' => ['Latin American', [
                'central-america' => ['Central America', ['GT' => ['Guatemala', '🇬🇹'], 'CR' => ['Costa Rica', '🇨🇷'], 'PA' => ['Panama', '🇵🇦'], 'SV' => ['El Salvador', '🇸🇻']]],
                'south-america' => ['South America', ['BR' => ['Brazil', '🇧🇷'], 'CO' => ['Colombia', '🇨🇴'], 'PE' => ['Peru', '🇵🇪'], 'AR' => ['Argentina', '🇦🇷'], 'VE' => ['Venezuela', '🇻🇪'], 'CL' => ['Chile', '🇨🇱'], 'EC' => ['Ecuador', '🇪🇨'], 'BO' => ['Bolivia', '🇧🇴'], 'PY' => ['Paraguay', '🇵🇾'], 'UY' => ['Uruguay', '🇺🇾']]],
                'mexico' => ['Mexico', ['MX' => ['Mexico', '🇲🇽']]],
                'caribbean-latin-america' => ['Caribbean Latin America', ['CU' => ['Cuba', '🇨🇺'], 'DO' => ['Dominican Republic', '🇩🇴'], 'PR' => ['Puerto Rico', '🇵🇷']]],
            ]],
            'south-american' => ['South American', [
                'south-america' => ['South America', ['BR' => ['Brazil', '🇧🇷'], 'CO' => ['Colombia', '🇨🇴'], 'PE' => ['Peru', '🇵🇪'], 'AR' => ['Argentina', '🇦🇷'], 'VE' => ['Venezuela', '🇻🇪']]],
            ]],
            'european' => ['European', [
                'northern-europe' => ['Northern Europe', ['GB' => ['United Kingdom', '🇬🇧'], 'IE' => ['Ireland', '🇮🇪'], 'SE' => ['Sweden', '🇸🇪'], 'NO' => ['Norway', '🇳🇴']]],
                'southern-europe' => ['Southern Europe', ['IT' => ['Italy', '🇮🇹'], 'ES' => ['Spain', '🇪🇸'], 'GR' => ['Greece', '🇬🇷'], 'PT' => ['Portugal', '🇵🇹']]],
                'eastern-europe' => ['Eastern Europe', ['PL' => ['Poland', '🇵🇱'], 'UA' => ['Ukraine', '🇺🇦'], 'RO' => ['Romania', '🇷🇴']]],
                'western-europe' => ['Western Europe', ['FR' => ['France', '🇫🇷'], 'DE' => ['Germany', '🇩🇪'], 'NL' => ['Netherlands', '🇳🇱'], 'BE' => ['Belgium', '🇧🇪']]],
            ]],
            'east-asian' => ['East Asian', [
                'east-asia' => ['East Asia', ['CN' => ['China', '🇨🇳'], 'JP' => ['Japan', '🇯🇵'], 'KR' => ['South Korea', '🇰🇷'], 'TW' => ['Taiwan', '🇹🇼']]],
            ]],
            'south-asian' => ['South Asian', [
                'south-asia' => ['South Asia', ['IN' => ['India', '🇮🇳'], 'PK' => ['Pakistan', '🇵🇰'], 'BD' => ['Bangladesh', '🇧🇩'], 'LK' => ['Sri Lanka', '🇱🇰'], 'NP' => ['Nepal', '🇳🇵']]],
            ]],
            'southeast-asian' => ['Southeast Asian', [
                'southeast-asia' => ['Southeast Asia', ['TH' => ['Thailand', '🇹🇭'], 'VN' => ['Vietnam', '🇻🇳'], 'PH' => ['Philippines', '🇵🇭'], 'ID' => ['Indonesia', '🇮🇩'], 'MY' => ['Malaysia', '🇲🇾'], 'SG' => ['Singapore', '🇸🇬'], 'KH' => ['Cambodia', '🇰🇭'], 'LA' => ['Laos', '🇱🇦'], 'MM' => ['Myanmar', '🇲🇲'], 'BN' => ['Brunei', '🇧🇳'], 'TL' => ['Timor-Leste', '🇹🇱']]],
            ]],
            'asian' => ['Asian', [
                'asian-east-asia' => ['East Asia', ['CN' => ['China', '🇨🇳'], 'JP' => ['Japan', '🇯🇵'], 'KR' => ['South Korea', '🇰🇷'], 'TW' => ['Taiwan', '🇹🇼']]],
                'asian-south-asia' => ['South Asia', ['IN' => ['India', '🇮🇳'], 'PK' => ['Pakistan', '🇵🇰'], 'BD' => ['Bangladesh', '🇧🇩'], 'LK' => ['Sri Lanka', '🇱🇰'], 'NP' => ['Nepal', '🇳🇵'], 'AF' => ['Afghanistan', '🇦🇫'], 'BT' => ['Bhutan', '🇧🇹'], 'MV' => ['Maldives', '🇲🇻']]],
                'asian-southeast-asia' => ['Southeast Asia', ['TH' => ['Thailand', '🇹🇭'], 'VN' => ['Vietnam', '🇻🇳'], 'PH' => ['Philippines', '🇵🇭'], 'ID' => ['Indonesia', '🇮🇩'], 'MY' => ['Malaysia', '🇲🇾'], 'SG' => ['Singapore', '🇸🇬'], 'KH' => ['Cambodia', '🇰🇭'], 'LA' => ['Laos', '🇱🇦'], 'MM' => ['Myanmar', '🇲🇲'], 'BN' => ['Brunei', '🇧🇳'], 'TL' => ['Timor-Leste', '🇹🇱']]],
                'central-asia' => ['Central Asia', ['KZ' => ['Kazakhstan', '🇰🇿'], 'KG' => ['Kyrgyzstan', '🇰🇬'], 'TJ' => ['Tajikistan', '🇹🇯'], 'TM' => ['Turkmenistan', '🇹🇲'], 'UZ' => ['Uzbekistan', '🇺🇿']]],
                'asian-west-asia' => ['West Asia', ['LB' => ['Lebanon', '🇱🇧'], 'PS' => ['Palestine', '🇵🇸'], 'JO' => ['Jordan', '🇯🇴'], 'IR' => ['Iran', '🇮🇷'], 'TR' => ['Türkiye', '🇹🇷'], 'IQ' => ['Iraq', '🇮🇶'], 'SY' => ['Syria', '🇸🇾'], 'YE' => ['Yemen', '🇾🇪'], 'AM' => ['Armenia', '🇦🇲'], 'AZ' => ['Azerbaijan', '🇦🇿'], 'GE' => ['Georgia', '🇬🇪']]],
            ]],
            'middle-eastern' => ['Middle Eastern', [
                'west-asia' => ['West Asia', ['LB' => ['Lebanon', '🇱🇧'], 'PS' => ['Palestine', '🇵🇸'], 'JO' => ['Jordan', '🇯🇴'], 'IR' => ['Iran', '🇮🇷'], 'TR' => ['Türkiye', '🇹🇷']]],
            ]],
            'pacific-islander' => ['Pacific Islander', [
                'pacific-islands' => ['Pacific Islands', ['WS' => ['Samoa', '🇼🇸'], 'FJ' => ['Fiji', '🇫🇯'], 'TO' => ['Tonga', '🇹🇴'], 'NZ' => ['New Zealand', '🇳🇿']]],
            ]],
            'multicultural' => ['Multicultural', ['multicultural' => ['Multicultural', []]]],
            'international' => ['International', ['international' => ['International', []]]],
            'other' => ['Other', ['other' => ['Other', []]]],
        ];

        foreach ($catalog as $categoryId => [$categoryName, $regions]) {
            CulturalCategory::updateOrCreate(['id' => $categoryId], ['name' => $categoryName, 'is_active' => true]);
            foreach ($regions as $regionId => [$regionName, $countries]) {
                CulturalRegion::updateOrCreate(['id' => $regionId], [
                    'cultural_category_id' => $categoryId, 'name' => $regionName, 'is_active' => true,
                ]);
                foreach ($countries as $countryId => [$countryName, $flag]) {
                    $country = Country::updateOrCreate(['id' => $countryId], [
                        'name' => $countryName, 'flag_emoji' => $flag, 'is_active' => true,
                    ]);
                    if ($countryId === 'JM' && !$country->cuisine_image_url) {
                        $country->update(['cuisine_image_url' => '/images/jerk_chicken.jpg']);
                    }
                    $country->regions()->syncWithoutDetaching([$regionId]);
                }
            }
        }

        $cuisines = [
            'JM' => ['Jamaican Cuisine' => ['Jerk Chicken', 'Curry Goat', 'Ackee and Saltfish', 'Oxtail', 'Escovitch Fish', 'Brown Stew Chicken', 'Rice and Peas', 'Festival', 'Bammy', 'Patties']],
            'HT' => ['Haitian Cuisine' => ['Griot', 'Diri Kole', 'Soup Joumou', 'Tassot', 'Pikliz', 'Akra']],
            'DO' => ['Dominican Cuisine' => ['La Bandera', 'Mangú', 'Sancocho', 'Mofongo']],
            'CU' => ['Cuban Cuisine' => ['Ropa Vieja', 'Moros y Cristianos', 'Lechón Asado', 'Yuca con Mojo']],
            'PR' => ['Puerto Rican Cuisine' => ['Mofongo', 'Arroz con Gandules', 'Pastelón', 'Pernil']],
            'NG' => ['Nigerian Cuisine' => ['Jollof Rice', 'Egusi Soup', 'Suya', 'Pounded Yam']],
            'GH' => ['Ghanaian Cuisine' => ['Waakye', 'Banku and Tilapia', 'Fufu and Light Soup', 'Kelewele']],
            'ET' => ['Ethiopian Cuisine' => ['Injera', 'Doro Wat', 'Shiro', 'Misir Wat']],
            'MX' => ['Mexican Cuisine' => ['Mole', 'Tamales', 'Pozole', 'Tacos']],
            'BR' => ['Brazilian Cuisine' => ['Feijoada', 'Pão de Queijo', 'Moqueca']],
            'CO' => ['Colombian Cuisine' => ['Bandeja Paisa', 'Arepas', 'Ajiaco']],
            'JP' => ['Japanese Cuisine' => ['Sushi', 'Ramen', 'Tempura']],
            'IN' => ['Indian Cuisine' => ['Biryani', 'Chana Masala', 'Dosa']],
            'TH' => ['Thai Cuisine' => ['Pad Thai', 'Tom Yum', 'Green Curry']],
            'LB' => ['Lebanese Cuisine' => ['Tabbouleh', 'Kibbeh', 'Mujadara']],
            'PH' => ['Filipino Cuisine' => ['Chicken Adobo', 'Sinigang', 'Pancit']],
            'ID' => ['Indonesian Cuisine' => ['Rendang', 'Gado-Gado', 'Nasi Goreng']],
            'VN' => ['Vietnamese Cuisine' => ['Phở', 'Bánh Mì', 'Gỏi Cuốn']],
            'MY' => ['Malaysian Cuisine' => ['Nasi Lemak', 'Char Kway Teow', 'Roti Canai']],
            'PK' => ['Pakistani Cuisine' => ['Nihari', 'Chapli Kebab', 'Chicken Karahi']],
            'BD' => ['Bangladeshi Cuisine' => ['Shorshe Ilish', 'Bhuna Khichuri', 'Panta Bhat']],
            'NP' => ['Nepali Cuisine' => ['Dal Bhat', 'Momo', 'Sel Roti']],
            'LK' => ['Sri Lankan Cuisine' => ['Rice and Curry', 'Kottu Roti', 'Hoppers']],
            'UZ' => ['Uzbek Cuisine' => ['Plov', 'Samsa', 'Lagman']],
            'KZ' => ['Kazakh Cuisine' => ['Beshbarmak', 'Baursak', 'Kazy']],
            'MA' => ['Moroccan Cuisine' => ['Couscous', 'Tagine', 'Harira']],
            'KE' => ['Kenyan Cuisine' => ['Ugali', 'Nyama Choma', 'Sukuma Wiki']],
            'ZA' => ['South African Cuisine' => ['Bobotie', 'Chakalaka', 'Bunny Chow']],
            'CA' => ['Canadian Cuisine' => ['Poutine', 'Tourtière', 'Butter Tarts']],
            'CL' => ['Chilean Cuisine' => ['Pastel de Choclo', 'Empanadas', 'Cazuela']],
            'PE' => ['Peruvian Cuisine' => ['Ceviche', 'Lomo Saltado', 'Papa a la Huancaína']],
            'AR' => ['Argentine Cuisine' => ['Asado', 'Empanadas', 'Locro']],
            'IT' => ['Italian Cuisine' => ['Risotto', 'Lasagna', 'Arancini']],
            'US' => ['United States Cuisine' => ['Barbecue', 'Gumbo', 'Cornbread']],
        ];

        $dietaryTypes = [
            'Pikliz' => 'vegan', 'Steamed Callaloo' => 'vegan', 'Ital Stew' => 'vegan',
            'Misir Wat' => 'vegan', 'Shiro' => 'vegan', 'Chana Masala' => 'vegan',
            'Gado-Gado' => 'vegan', 'Tabbouleh' => 'vegan', 'Mujadara' => 'vegan',
            'Dosa' => 'vegetarian', 'Pão de Queijo' => 'vegetarian', 'Festival' => 'vegetarian',
        ];

        foreach ($cuisines as $countryId => $countryCuisines) {
            foreach ($countryCuisines as $cuisineName => $dishNames) {
                $cuisineId = Str::slug($countryId . '-' . $cuisineName);
                Cuisine::updateOrCreate(['id' => $cuisineId], [
                    'country_id' => $countryId, 'name' => $cuisineName, 'is_active' => true,
                ]);
                foreach ($dishNames as $index => $dishName) {
                    Dish::updateOrCreate(['cuisine_id' => $cuisineId, 'name' => $dishName], [
                        'alternative_spellings' => [], 'dietary_type' => $dietaryTypes[$dishName] ?? 'any',
                        'sort_order' => $index, 'is_active' => true,
                    ]);
                }
            }
        }
    }
}
