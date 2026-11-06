<?php

namespace App\Http\Controllers;

use App\Services\EventManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The host's event photo gallery on the edit page. Every action goes through EventManagementService, which checks
 * that the signed-in account manages the event (404 otherwise), validates and re-encodes the image, keeps the
 * original, makes the responsive variants and audits the change.
 */
class EventPhotosController extends Controller
{
    public function __construct(private readonly EventManagementService $events) {}

    public function store(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'alt_text' => ['required', 'string', 'min:2', 'max:250'],
            'position_x' => ['nullable', 'integer', 'between:0,100'],
            'position_y' => ['nullable', 'integer', 'between:0,100'],
        ], [
            'photo.required' => 'Choose a photo to add.',
            'photo.mimes' => 'Upload a JPG, PNG or WebP photo.',
            'photo.max' => 'Photos must be 5 MB or smaller.',
            'alt_text.required' => 'Describe the photo for people who cannot see it.',
        ]);

        try {
            $this->events->uploadEventMedia($request->user(), $id, $request->file('photo'), 'gallery', $data['alt_text'],
                (int) ($data['position_x'] ?? 50), (int) ($data['position_y'] ?? 50));
        } catch (\InvalidArgumentException $e) {
            return $this->back($id)->withErrors(['photo' => $e->getMessage()])->withInput();
        }

        return $this->back($id)->with('status', 'Photo added to the gallery.');
    }

    public function update(Request $request, string $id, string $mediaId): RedirectResponse
    {
        $data = $request->validate([
            'alt_text' => ['required', 'string', 'min:2', 'max:250'],
            'position_x' => ['required', 'integer', 'between:0,100'],
            'position_y' => ['required', 'integer', 'between:0,100'],
        ]);
        try {
            $this->events->updateEventMedia($request->user(), $id, $mediaId, $data['alt_text'], (int) $data['position_x'], (int) $data['position_y']);
        } catch (\InvalidArgumentException $e) {
            return $this->back($id)->with('error', $e->getMessage());
        }

        return $this->back($id)->with('status', 'Photo details saved.');
    }

    public function destroy(Request $request, string $id, string $mediaId): RedirectResponse
    {
        $this->events->removeEventMedia($request->user(), $id, $mediaId);

        return $this->back($id)->with('status', 'Photo removed. The original and every size were deleted.');
    }

    private function back(string $id): RedirectResponse
    {
        return redirect()->to(route('events.edit', $id) . '#event-photos');
    }
}
