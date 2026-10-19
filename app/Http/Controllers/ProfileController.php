<?php

namespace App\Http\Controllers;

use App\Models\EnterpriseDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    /**
     * Enterprise profile page.
     *   /enterprise-profile          → enterprise: own profile (both tabs) · staff: list of enterprises
     *   /enterprise-profile/{user}   → staff: that enterprise's profile, read-only (Profile tab only)
     */
    public function index(?User $user = null): View
    {
        return view('profile.index', ['userId' => $user?->id]);
    }

    /**
     * Private download of an enterprise profile document.
     * Allowed: the enterprise that owns the profile, staff who can view all EOIs, and the Investment Committee
     * (members scoring and the secretary recording the decision).
     */
    public function document(EnterpriseDocument $document): StreamedResponse
    {
        $user = auth()->user();

        abort_unless(
            $document->profile?->user_id === $user->id || $user->can('eoi.view-all') || $user->can('gate3.score') || $user->can('gate3.decide'),
            403
        );

        abort_unless(Storage::disk(EnterpriseDocument::DISK)->exists($document->path), 404);

        return Storage::disk(EnterpriseDocument::DISK)->download($document->path, $document->original_name);
    }
}