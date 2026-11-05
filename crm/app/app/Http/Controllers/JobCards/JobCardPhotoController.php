<?php

namespace App\Http\Controllers\JobCards;

use App\Http\Controllers\Controller;
use App\Models\JobCard;
use App\Models\JobCardPhoto;
use App\Services\JobCards\JobCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobCardPhotoController extends Controller
{
    public function __construct(private readonly JobCardService $service) {}

    /**
     * Upload one or more photos. The phone sends one file per request with a client_ids[] UUID
     * and retries on failure; a repeated UUID returns the saved photo instead of a copy.
     */
    public function store(Request $request, JobCard $jobCard): JsonResponse|RedirectResponse
    {
        Gate::authorize('work', $jobCard);

        $data = $request->validate([
            'stage' => ['required', Rule::in(JobCardPhoto::STAGES)],
            'photos' => ['required', 'array', 'min:1', 'max:12'],
            'photos.*' => ['required', 'file', 'max:15360', 'mimes:jpg,jpeg,png,webp,heic,heif'],
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['nullable', 'uuid'],
        ], [
            'photos.required' => 'Pick at least one photo.',
            'photos.*.mimes' => 'Only photos (JPG, PNG, WebP or iPhone HEIC) can be added.',
            'photos.*.max' => 'A photo can be at most 15 MB.',
            'photos.max' => 'Add up to 12 photos at a time.',
        ]);

        $photos = $this->service->addPhotos($jobCard, $data['photos'], $data['stage'], $request->user(), $data['client_ids'] ?? []);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'photos' => array_map(fn (JobCardPhoto $p) => [
                    'id' => $p->id, 'stage' => $p->stage, 'url' => $p->url(), 'thumb_url' => $p->url(true),
                ], $photos),
                'count' => $jobCard->photos()->count(),
            ], 201);
        }

        return back()->with('status', count($photos).' photo'.(count($photos) === 1 ? '' : 's').' added.');
    }

    /** Streams a private photo after the same check as the job card itself. */
    public function show(Request $request, JobCard $jobCard, JobCardPhoto $photo): StreamedResponse
    {
        abort_unless($photo->job_card_id === $jobCard->id, 404);
        Gate::authorize('view', $jobCard);

        $path = $request->boolean('thumb') && $photo->thumb_path ? $photo->thumb_path : $photo->path;
        $disk = Storage::disk(JobCardPhoto::DISK);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, basename($path), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
