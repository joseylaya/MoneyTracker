<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TrackerController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ExpenseCommentController;
use App\Http\Controllers\TrackerConversationController;
use App\Http\Controllers\PushDeviceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('trackers.index');
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/dashboard', function () {
    return redirect()->route('trackers.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('/push-devices', [PushDeviceController::class, 'store'])->name('push-devices.store');
    Route::delete('/push-devices', [PushDeviceController::class, 'destroy'])->name('push-devices.destroy');
    Route::get('/home', [TrackerController::class, 'index'])->name('home');
    Route::get('/trackers', [TrackerController::class, 'index'])->name('trackers.index');
    Route::get('/trackers/create', [TrackerController::class, 'create'])->name('trackers.create');
    Route::post('/trackers', [TrackerController::class, 'store'])->name('trackers.store');
    Route::get('/trackers/{tracker}', [TrackerController::class, 'show'])->name('trackers.show');
    Route::get('/trackers/{tracker}/members', [TrackerController::class, 'members'])->name('trackers.members.index');
    Route::get('/trackers/{tracker}/conversation', [TrackerConversationController::class, 'index'])->name('trackers.conversation.index');
    Route::get('/trackers/{tracker}/conversation/messages/older', [TrackerConversationController::class, 'older'])->name('trackers.conversation.messages.older');
    Route::post('/trackers/{tracker}/conversation', [TrackerConversationController::class, 'store'])->name('trackers.conversation.store');
    Route::post('/trackers/{tracker}/conversation/{message}/reactions', [TrackerConversationController::class, 'react'])->name('trackers.conversation.reactions.store');
    Route::post('/trackers/{tracker}/conversation/settlements', [TrackerConversationController::class, 'requestSettlement'])->name('trackers.conversation.settlements.store');
    Route::post('/trackers/{tracker}/conversation/settlements/{settlementRequest}/response', [TrackerConversationController::class, 'respondToSettlement'])->name('trackers.conversation.settlements.response');
    Route::get('/trackers/{tracker}/conversation/{message}/attachments/{attachment}', [TrackerConversationController::class, 'attachment'])->name('trackers.conversation.attachments.show');
    Route::post('/trackers/{tracker}/members', [TrackerController::class, 'addMember'])->name('trackers.members.store');
    Route::patch('/trackers/{tracker}/members/{member}/role', [TrackerController::class, 'changeMemberRole'])->name('trackers.members.role.update');
    Route::get('/trackers/{tracker}/expenses/create', [TrackerController::class, 'expenseCreate'])->name('trackers.expenses.create');
    Route::post('/trackers/{tracker}/expenses', [TrackerController::class, 'storeExpense'])->name('trackers.expenses.store');
    Route::get('/trackers/{tracker}/expenses/{expense}', [TrackerController::class, 'expenseShow'])->name('trackers.expenses.show');
    Route::get('/trackers/{tracker}/expenses/{expense}/comments/older', [ExpenseCommentController::class, 'older'])->name('trackers.expenses.comments.older');
    Route::post('/trackers/{tracker}/expenses/{expense}/comments', [ExpenseCommentController::class, 'store'])->name('trackers.expenses.comments.store');
    Route::get('/trackers/{tracker}/settlements/create', [TrackerController::class, 'settlementCreate'])->name('trackers.settlements.create');
    Route::post('/trackers/{tracker}/settlements', [TrackerController::class, 'storeSettlement'])->name('trackers.settlements.store');
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
