<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SecurityException;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventManagementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Event Host editing and creation of their own events and menus for the single-page app.
 */
class HostEventsController extends Controller
{
    public function __construct(
        protected EventManagementService $events
    ) {}

    public function index(Request $request): JsonResponse
    {
        $rows = $this->events->eventsQuery($request->user())->get();

        return response()->json(['data' => EventResource::collection($rows)->resolve($request)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organizer_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:128', 'regex:/^[a-z0-9-]+$/'],
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:128'],
            'date_str' => ['required', 'string', 'min:3', 'max:100'],
            'event_date_iso' => ['sometimes', 'nullable', 'string', 'max:32'],
            'time_str' => ['required', 'string', 'max:100'],
            'start_time' => ['sometimes', 'nullable', 'string', 'max:32'],
            'end_time' => ['sometimes', 'nullable', 'string', 'max:32'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'venue_name' => ['required', 'string', 'min:2', 'max:200'],
            'venue_address' => ['required', 'string', 'max:500'],
            'coordinates' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'currency' => ['sometimes', 'nullable', Rule::in(['JMD', 'USD'])],
            'pickup_instructions' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'organizer_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'organizer_phone' => ['required', 'string', 'min:7', 'max:40'],
            'organizer_whatsapp' => ['sometimes', 'nullable', 'string', 'max:40'],
            'organizer_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'packaging_fee_usd' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'packaging_fee_jmd' => ['sometimes', 'integer', 'min:0', 'max:20000'],
            'advance_order_deadline' => ['required', 'date'],
            'supports_food_ordering' => ['sometimes', 'boolean'],
            'ticket_tiers' => ['sometimes', 'nullable', 'array', 'max:20'],
        ]);

        return $this->run(fn () => $this->present($request, $this->events->createEvent($request->user(), $data)), 201);
    }

    public function update(Request $request, string $eventId): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'min:3', 'max:200'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:128'],
            'date_str' => ['sometimes', 'string', 'min:3', 'max:100'],
            'event_date_iso' => ['sometimes', 'nullable', 'string', 'max:32'],
            'time_str' => ['sometimes', 'string', 'max:100'],
            'start_time' => ['sometimes', 'nullable', 'string', 'max:32'],
            'end_time' => ['sometimes', 'nullable', 'string', 'max:32'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'venue_name' => ['sometimes', 'string', 'min:2', 'max:200'],
            'venue_address' => ['sometimes', 'string', 'max:500'],
            'coordinates' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'currency' => ['sometimes', 'nullable', Rule::in(['JMD', 'USD'])],
            'pickup_instructions' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'organizer_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'organizer_phone' => ['sometimes', 'string', 'max:40'],
            'organizer_whatsapp' => ['sometimes', 'nullable', 'string', 'max:40'],
            'organizer_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'packaging_fee_usd' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'packaging_fee_jmd' => ['sometimes', 'integer', 'min:0', 'max:20000'],
            'advance_order_deadline' => ['sometimes', 'date'],
            'is_ordering_open' => ['sometimes', 'boolean'],
            'supports_food_ordering' => ['sometimes', 'boolean'],
            'ticket_tiers' => ['sometimes', 'nullable', 'array', 'max:20'],
        ]);

        if ($data === []) {
            return response()->json(['error' => 'Nothing to update.'], 422);
        }

        return $this->run(fn () => $this->present($request, $this->events->updateEvent($request->user(), $eventId, $data)));
    }

    public function setStatus(Request $request, string $eventId): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(EventManagementService::STATUSES)]]);

        return $this->run(fn () => $this->present($request, $this->events->setStatus($request->user(), $eventId, $data['status'])));
    }

    public function storeMenuItem(Request $request, string $eventId): JsonResponse
    {
        $data = $request->validate($this->menuRules(creating: true));

        return $this->run(fn () => $this->present($request, $this->events->createMenuItem($request->user(), $eventId, $data)), 201);
    }

    public function updateMenuItem(Request $request, string $eventId, string $itemId): JsonResponse
    {
        $data = $request->validate($this->menuRules(creating: false));

        return $this->run(fn () => $this->present($request, $this->events->updateMenuItem($request->user(), $eventId, $itemId, $data)));
    }

    public function destroyMenuItem(Request $request, string $eventId, string $itemId): JsonResponse
    {
        return $this->run(fn () => $this->present($request, $this->events->deleteMenuItem($request->user(), $eventId, $itemId)));
    }

    public function uploadFlyer(Request $request, string $eventId): JsonResponse
    {
        $request->validate(['flyer' => ['required', 'file']]);

        return $this->run(fn () => $this->present($request, $this->events->uploadFlyer($request->user(), $eventId, $request->file('flyer'))));
    }

    public function removeFlyer(Request $request, string $eventId): JsonResponse
    {
        return $this->run(fn () => $this->present($request, $this->events->removeFlyer($request->user(), $eventId)));
    }

    public function uploadMenuPhoto(Request $request, string $eventId, string $itemId): JsonResponse
    {
        $request->validate(['photo' => ['required', 'file']]);

        return $this->run(fn () => $this->present($request, $this->events->uploadMenuPhoto($request->user(), $eventId, $itemId, $request->file('photo'))));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function menuRules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'min:2', 'max:150'],
            'category' => [$required, 'string', 'max:64'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'pricing_type' => [$required, Rule::in(['fixed', 'customizable', 'custom_amount'])],
            'price_usd' => [$required, 'numeric', 'min:0', 'max:10000'],
            'price_jmd' => [$required, 'integer', 'min:0', 'max:1000000'],
            // Links only: absolute http(s) URLs or site paths; uploads go through the photo endpoint
            'image_url' => ['sometimes', 'nullable', 'string', 'max:500', 'regex:/^(https?:\/\/|\/)[^\s"\'<>]*$/i'],
            'custom_option_groups' => ['sometimes', 'nullable', 'array', 'max:10'],
            'custom_option_groups.*.id' => ['required', 'string', 'max:64'],
            'custom_option_groups.*.title' => ['required', 'string', 'max:100'],
            'custom_option_groups.*.required' => ['required', 'boolean'],
            'custom_option_groups.*.choices' => ['required', 'array', 'min:1', 'max:20'],
            'custom_option_groups.*.choices.*.label' => ['required', 'string', 'max:150'],
            'custom_option_groups.*.choices.*.extraPriceUSD' => ['required', 'numeric', 'min:0'],
            'custom_option_groups.*.choices.*.extraPriceJMD' => ['required', 'numeric', 'min:0'],
            'custom_amount_config' => ['sometimes', 'nullable', 'array'],
            'allow_special_instructions' => ['sometimes', 'boolean'],
            'available_stock' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    private function present(Request $request, Event $event): array
    {
        return ['data' => (new EventResource($event))->resolve($request)];
    }

    private function run(callable $action, int $status = 200): JsonResponse
    {
        try {
            return response()->json($action(), $status);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => $e->getMessage() ?: 'Not found.'], 404);
        } catch (SecurityException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
