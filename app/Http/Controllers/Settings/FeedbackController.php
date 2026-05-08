<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FeedbackStoreRequest;
use App\Models\FeedbackReport;
use Illuminate\Http\RedirectResponse;

class FeedbackController extends Controller
{
    public function store(FeedbackStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->resolveActiveProfile();
        $validated = $request->validated();

        FeedbackReport::create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'kind' => $validated['kind'],
            'subject' => $validated['subject'],
            'page_context' => $this->blankToNull($validated['page_context'] ?? null),
            'message' => $validated['message'],
            'submitted_from_path' => $this->cleanPath($request->headers->get('referer')),
            'user_agent' => $this->blankToNull($request->userAgent()),
            'status' => 'new',
        ]);

        return redirect()
            ->to(route('profile.edit').'#feedback')
            ->with('feedback_status', 'Thanks. Your note is in and ready for review.');
    }

    private function blankToNull(?string $value): ?string
    {
        $trimmed = is_string($value) ? trim($value) : null;

        return $trimmed === '' ? null : $trimmed;
    }

    private function cleanPath(?string $referer): ?string
    {
        if (! is_string($referer) || trim($referer) === '') {
            return null;
        }

        $path = parse_url($referer, PHP_URL_PATH);
        $query = parse_url($referer, PHP_URL_QUERY);

        if (! is_string($path) || $path === '') {
            return null;
        }

        return $query ? $path.'?'.$query : $path;
    }
}
