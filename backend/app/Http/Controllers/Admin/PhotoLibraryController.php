<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewLibraryPhotoRequest;
use App\Http\Requests\StoreLibraryPhotoRequest;
use App\Models\Event;
use App\Models\LibraryPhoto;
use App\Services\AuditLogService;
use App\Services\MediaStorageService;
use App\Services\PhotoLibrary;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The event photography library in the admin console: coverage of every kind of event, the brief for producing
 * each kind's photos, uploads with provenance and consent records, two-person review against the photography
 * standards, focal points and alt text, and retiring photos.
 */
class PhotoLibraryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize(Admin::PHOTO_LIBRARY_MANAGE);
        $photos = LibraryPhoto::with(['uploader', 'reviewer'])->orderByDesc('id')->get();
        $events = Event::allTenants()->whereNotNull('photo_subject')->selectRaw('photo_subject, count(*) as n')->groupBy('photo_subject')->pluck('n', 'photo_subject');

        return view('admin.photo-library.index', [
            'subjects' => PhotoLibrary::subjects(),
            'groups' => (array) config('photo_library.groups'),
            'standards' => (array) config('photo_library.standards'),
            'photos' => $photos,
            'coverage' => $photos->groupBy('subject')->map(fn ($rows) => $rows->countBy('status')),
            'eventsBySubject' => $events,
            'pending' => $photos->where('status', 'pending_review')->values(),
            'subject' => (string) $request->query('subject', ''),
            'canApprove' => Gate::allows(Admin::PHOTO_LIBRARY_APPROVE),
        ]);
    }

    public function store(StoreLibraryPhotoRequest $request, PhotoLibrary $library): RedirectResponse
    {
        try {
            $photo = $library->store($request->file('photo'), $request->validated(), $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['photo' => $e->getMessage()]);
        }
        AuditLogService::log(action: 'library_photo_uploaded', targetEntity: 'library_photos', targetId: (string) $photo->id,
            details: "{$photo->title} ({$photo->subjectLabel()}); source: {$photo->source} ({$photo->source_detail}); licence: {$photo->licence}.", actor: $request->user());

        return redirect()->route('admin.console.photo-library.index', ['subject' => $photo->subject])
            ->with('success', "Added \"{$photo->title}\". It appears nowhere until a system administrator other than you approves it.");
    }

    public function update(Request $request, LibraryPhoto $photo): RedirectResponse
    {
        Gate::authorize(Admin::PHOTO_LIBRARY_MANAGE);
        $data = $request->validate([
            'alt_text' => ['required', 'string', 'min:15', 'max:300'],
            'focal_x' => ['required', 'integer', 'between:0,100'],
            'focal_y' => ['required', 'integer', 'between:0,100'],
        ]);
        $photo->update($data);
        AuditLogService::log(action: 'library_photo_updated', targetEntity: 'library_photos', targetId: (string) $photo->id,
            details: "Alt text and focal point updated for {$photo->title}.", actor: $request->user());

        return back()->with('success', 'Saved.');
    }

    public function review(ReviewLibraryPhotoRequest $request, LibraryPhoto $photo): RedirectResponse
    {
        if ($photo->status !== 'pending_review') {
            return back()->with('error', 'This photo has already been reviewed.');
        }
        $data = $request->validated();
        $approved = $data['decision'] === 'approve';
        DB::transaction(function () use ($photo, $data, $approved, $request) {
            $photo->forceFill([
                'status' => $approved ? 'approved' : 'rejected',
                'standards_checked' => $approved ? array_keys((array) config('photo_library.standards')) : null,
                'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_notes' => $data['notes'] ?? null,
            ])->save();
            AuditLogService::log(action: 'library_photo_' . ($approved ? 'approved' : 'rejected'), targetEntity: 'library_photos',
                targetId: (string) $photo->id, details: "{$photo->title} ({$photo->subjectLabel()})" . (!empty($data['notes']) ? ": {$data['notes']}" : ''), actor: $request->user());
        });

        return back()->with('success', $approved ? 'Approved. It can now appear, labelled as illustrative, on events of this kind with no flyer.' : 'Rejected.');
    }

    public function retire(Request $request, LibraryPhoto $photo): RedirectResponse
    {
        Gate::authorize(Admin::PHOTO_LIBRARY_MANAGE);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        DB::transaction(function () use ($photo, $data, $request) {
            $photo->forceFill(['status' => 'retired', 'review_notes' => $data['reason']])->save();
            $photo->delete(); // kept (soft deleted) with its provenance; its files stay until purged
            AuditLogService::log(action: 'library_photo_retired', targetEntity: 'library_photos', targetId: (string) $photo->id,
                details: "{$photo->title} retired: {$data['reason']}", actor: $request->user());
        });

        return back()->with('success', 'Retired. It no longer appears anywhere.');
    }
}
