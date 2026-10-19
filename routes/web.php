<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ExpressionOfInterestController;
use App\Http\Controllers\ManageUsersController;
use App\Http\Controllers\RolesPermissionsController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ComposeEmailController;
use App\Http\Controllers\AccountSettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;



Route::get('/', function () {
    return view('welcome');
});
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
       ->middleware(['signed', 'throttle:6,1'])
       ->name('verification.verify');



Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');


Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {

    Route::get('/compose-email', [ComposeEmailController::class, 'index'])->name('compose.email');
    Route::get('/compose-email/{email}/attachment/{i}', [ComposeEmailController::class, 'attachment'])->whereNumber('i')->name('compose.email.attachment');
    Route::get('/investments/{investment}/proof', [\App\Http\Controllers\InvestmentProofController::class, 'download'])->name('investments.proof');
    Route::get('/gate4/{authorisation}/{file}/download', [\App\Http\Controllers\Gate4FileController::class, 'download'])->whereIn('file', ['md_request', 'md_signed', 'ps_request', 'ps_signed'])->name('gate4.file');
    Route::get('/enterprise-profile/{user?}', [ProfileController::class, 'index'])->middleware('permission:eoi.submit|eoi.view-all|users.manage')->name('profile.index');
    Route::get('/enterprise-documents/{document}', [ProfileController::class, 'document'])->name('enterprise-documents.download');
    Route::get('/expression-of-interest', [ExpressionOfInterestController::class, 'index'])->name('expression-of-interest.index');
    Route::get('/show-more/{id}', [ExpressionOfInterestController::class, 'show'])->name('expression-of-interest.show');
    Route::get('/manage-users', [ManageUsersController::class, 'index'])->middleware('permission:users.manage')->name('users.index');
    Route::get('/roles-permissions', [RolesPermissionsController::class, 'index'])->middleware('permission:roles.manage')->name('roles.index');
    Route::get('/due-diligence/{dueDiligence}/memorandum', [\App\Http\Controllers\DueDiligenceController::class, 'memorandum'])->name('due-diligence.memorandum');
    Route::get('/gate5/{kind}/{id}/download', [\App\Http\Controllers\Gate5FileController::class, 'download'])->whereIn('kind', ['condition', 'agreement', 'deployment'])->name('gate5.file');
    Route::get('/account-settings/{id}', [AccountSettingsController::class, 'index'])->name('account.settings');
    
});
require __DIR__.'/auth.php';
