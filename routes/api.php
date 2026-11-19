<?php
// routes/api.php — Co-Investment platform

use App\Http\Controllers\Api\CollaborationMatchController;
use App\Http\Controllers\Api\CollaborationProfileController;
use Illuminate\Support\Facades\Route;

// ========== LEHSFF COLLABORATION PLATFORM (machine-to-machine) ==========
// TEMPORARY: no token check on these routes while testing — put VerifyCollaborationToken back before going live
Route::prefix('collaboration')->group(function () {

    // Does this e-mail have an account here?  GET /api/collaboration/profile-status?email=...
    Route::get('/profile-status', [CollaborationProfileController::class, 'show'])->name('api.collaboration.profile-status');

    // A new matchmaking result from the Collaboration Platform  POST /api/collaboration/matches
    Route::post('/matches', [CollaborationMatchController::class, 'store'])->name('api.collaboration.matches.store');
});