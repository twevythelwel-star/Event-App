<?php

namespace App\Http\Requests;

use App\Enums\TenantRole;
use App\Models\Event;
use App\Models\OrganizerUser;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $event = Event::where('slug', $this->route('eventSlug'))->first();

        // Unknown events fall through so the controller can answer with a 404
        if (!$event) {
            return (bool) $user;
        }

        return self::canManageMenu($user, $event);
    }

    /**
     * Only platform admins or managers of the event's own organizer can modify its food menu.
     * The organizer is always taken from the event, never from client input.
     */
    public static function canManageMenu(?User $user, Event $event): bool
    {
        if (!$user) {
            return false;
        }

        $membership = OrganizerUser::where('user_id', $user->id)
            ->where('organizer_id', $event->organizer_id)
            ->first();

        if (!$membership) {
            return $user->role === TenantRole::PLATFORM_ADMIN->value;
        }

        // OrganizerUser casts role to TenantRole; tolerate raw strings from uncast queries
        $role = $membership->role instanceof TenantRole ? $membership->role : TenantRole::tryFrom((string) $membership->role);
        return $role && $role->canManageEventConfiguration();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'category' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'pricing_type' => ['required', 'in:fixed,customizable,custom_amount'],
            'price_usd' => ['required', 'numeric', 'min:0'],
            'price_jmd' => ['required', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'custom_option_groups' => ['nullable', 'array'],
            'custom_option_groups.*.id' => ['required_with:custom_option_groups', 'string'],
            'custom_option_groups.*.title' => ['required_with:custom_option_groups', 'string'],
            'custom_option_groups.*.required' => ['required_with:custom_option_groups', 'boolean'],
            'custom_option_groups.*.choices' => ['required_with:custom_option_groups', 'array', 'min:1'],
            'custom_amount_config' => ['nullable', 'array'],
            'allow_special_instructions' => ['nullable', 'boolean'],
            'available_stock' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
