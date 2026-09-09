<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TrackerController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ExpenseCommentController;
use App\Http\Controllers\TrackerConversationController;
use App\Http\Controllers\PushDeviceController;
use App\Http\Controllers\TrackerNotificationController;
use App\Http\Controllers\FirebaseMessagingServiceWorkerController;
use App\Http\Controllers\PersonalFinanceController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\ItineraryRouteController;
use App\Http\Controllers\TrackerPlanningController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('home');
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});
Route::get('/firebase-messaging-sw.js', FirebaseMessagingServiceWorkerController::class)->name('firebase-messaging-sw');
Route::get('/join/{token}', [TrackerController::class, 'joinShared'])->name('trackers.share.join');
Route::get('/dashboard', function () {
    return redirect()->route('home');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('/push-devices', [PushDeviceController::class, 'store'])->name('push-devices.store');
    Route::delete('/push-devices', [PushDeviceController::class, 'destroy'])->name('push-devices.destroy');
    Route::get('/notifications', [TrackerNotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [TrackerNotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/group/read', [TrackerNotificationController::class, 'readGroup'])->name('notifications.group.read');
    Route::patch('/notifications/{notification}/read', [TrackerNotificationController::class, 'read'])->name('notifications.read');
    Route::delete('/notifications/{notification}/group', [TrackerNotificationController::class, 'dismissGroup'])->name('notifications.group.dismiss');
    Route::delete('/notifications/{notification}', [TrackerNotificationController::class, 'dismiss'])->name('notifications.dismiss');
    Route::middleware('email')->group(function () {
    Route::get('/home', [PersonalFinanceController::class, 'index'])->name('home');
    Route::get('/personal/accounts', [PersonalFinanceController::class, 'accounts'])->name('personal.accounts');
    Route::post('/personal/accounts', [PersonalFinanceController::class, 'storeAccount'])->name('personal.accounts.store');
    Route::patch('/personal/accounts/{account}', [PersonalFinanceController::class, 'updateAccount'])->name('personal.accounts.update');
    Route::delete('/personal/accounts/{account}', [PersonalFinanceController::class, 'destroyAccount'])->name('personal.accounts.destroy');
    Route::post('/personal/income', [PersonalFinanceController::class, 'storeIncome'])->name('personal.income.store');
    Route::post('/personal/expenses', [PersonalFinanceController::class, 'storeExpense'])->name('personal.expenses.store');
    Route::post('/personal/transfers', [PersonalFinanceController::class, 'transfer'])->name('personal.transfers.store');
    Route::post('/personal/commitments', [PersonalFinanceController::class, 'storeCommitment'])->name('personal.commitments.store');
    Route::post('/personal/commitments/{commitment}/pay', [PersonalFinanceController::class, 'payCommitment'])->name('personal.commitments.pay');
    Route::patch('/personal/commitments/{commitment}', [PersonalFinanceController::class, 'updateCommitment'])->name('personal.commitments.update');
    Route::delete('/personal/commitments/{commitment}', [PersonalFinanceController::class, 'destroyCommitment'])->name('personal.commitments.destroy');
    Route::patch('/personal/settings', [PersonalFinanceController::class, 'saveSettings'])->name('personal.settings.update');
    Route::put('/personal/buckets', [PersonalFinanceController::class, 'saveBucket'])->name('personal.buckets.save');
    Route::delete('/personal/buckets/{bucket}', [PersonalFinanceController::class, 'destroyBucket'])->name('personal.buckets.destroy');
    Route::post('/personal/reconciliations', [PersonalFinanceController::class, 'reconcile'])->name('personal.reconciliations.store');
    });
    Route::get('/trackers', [TrackerController::class, 'index'])->name('trackers.index');
    Route::get('/trackers/create', [TrackerController::class, 'create'])->name('trackers.create');
    Route::post('/trackers', [TrackerController::class, 'store'])->name('trackers.store');
    Route::patch('/trackers/{tracker}/archive', [TrackerController::class, 'archive'])->name('trackers.archive');
    Route::patch('/trackers/{tracker}/restore', [TrackerController::class, 'restore'])->name('trackers.restore');
    Route::delete('/trackers/{tracker}', [TrackerController::class, 'destroy'])->name('trackers.destroy');
    Route::get('/trackers/{tracker}', [TrackerController::class, 'show'])->name('trackers.show');
    Route::get('/trackers/{tracker}/planning', [TrackerPlanningController::class, 'index'])->name('trackers.planning.index');
    Route::post('/trackers/{tracker}/tasks', [TrackerPlanningController::class, 'storeTask'])->name('trackers.tasks.store');
    Route::patch('/trackers/{tracker}/tasks/{task}/toggle', [TrackerPlanningController::class, 'toggleTask'])->name('trackers.tasks.toggle');
    Route::delete('/trackers/{tracker}/tasks/{task}', [TrackerPlanningController::class, 'destroyTask'])->name('trackers.tasks.destroy');
    Route::post('/trackers/{tracker}/planned-expenses', [TrackerPlanningController::class, 'storePlannedExpense'])->name('trackers.planned-expenses.store');
    Route::delete('/trackers/{tracker}/planned-expenses/{plannedExpense}', [TrackerPlanningController::class, 'destroyPlannedExpense'])->name('trackers.planned-expenses.destroy');
    Route::get('/trackers/{tracker}/itinerary', [ItineraryController::class, 'index'])->name('trackers.itinerary.index');
    Route::get('/trackers/{tracker}/itinerary/days/{day}/navigate', [ItineraryController::class, 'navigate'])->name('trackers.itinerary.navigate');
    Route::post('/trackers/{tracker}/itinerary/days', [ItineraryController::class, 'storeDay'])->name('trackers.itinerary.days.store');
    Route::patch('/trackers/{tracker}/itinerary/days/{day}', [ItineraryController::class, 'updateDay'])->name('trackers.itinerary.days.update');
    Route::delete('/trackers/{tracker}/itinerary/days/{day}', [ItineraryController::class, 'destroyDay'])->name('trackers.itinerary.days.destroy');
    Route::post('/trackers/{tracker}/itinerary/days/{day}/items', [ItineraryController::class, 'storeItem'])->name('trackers.itinerary.items.store');
    Route::patch('/trackers/{tracker}/itinerary/items/{item}', [ItineraryController::class, 'updateItem'])->name('trackers.itinerary.items.update');
    Route::patch('/trackers/{tracker}/itinerary/items/{item}/completion', [ItineraryController::class, 'completeItem'])->name('trackers.itinerary.items.completion');
    Route::delete('/trackers/{tracker}/itinerary/items/{item}', [ItineraryController::class, 'destroyItem'])->name('trackers.itinerary.items.destroy');
    Route::patch('/trackers/{tracker}/itinerary/reorder', [ItineraryController::class, 'reorder'])->name('trackers.itinerary.reorder');
    Route::get('/trackers/{tracker}/itinerary/days/{day}/route', ItineraryRouteController::class)->name('trackers.itinerary.route');
    Route::post('/trackers/{tracker}/share-link', [TrackerController::class, 'shareLink'])->name('trackers.share-link.store');
    Route::get('/trackers/{tracker}/members', [TrackerController::class, 'members'])->name('trackers.members.index');
    Route::get('/trackers/{tracker}/members/suggestions', [TrackerController::class, 'memberSuggestions'])->name('trackers.members.suggestions');
    Route::get('/trackers/{tracker}/conversation', [TrackerConversationController::class, 'index'])->name('trackers.conversation.index');
    Route::get('/trackers/{tracker}/conversation/messages/older', [TrackerConversationController::class, 'older'])->name('trackers.conversation.messages.older');
    Route::post('/trackers/{tracker}/conversation/presence', [TrackerConversationController::class, 'presence'])->name('trackers.conversation.presence');
    Route::post('/trackers/{tracker}/conversation', [TrackerConversationController::class, 'store'])->name('trackers.conversation.store');
    Route::post('/trackers/{tracker}/conversation/{message}/reactions', [TrackerConversationController::class, 'react'])->name('trackers.conversation.reactions.store');
    Route::post('/trackers/{tracker}/conversation/settlements', [TrackerConversationController::class, 'requestSettlement'])->name('trackers.conversation.settlements.store');
    Route::post('/trackers/{tracker}/conversation/settlements/{settlementRequest}/response', [TrackerConversationController::class, 'respondToSettlement'])->name('trackers.conversation.settlements.response');
    Route::get('/trackers/{tracker}/conversation/{message}/attachments/{attachment}', [TrackerConversationController::class, 'attachment'])->name('trackers.conversation.attachments.show');
    Route::post('/trackers/{tracker}/members', [TrackerController::class, 'addMember'])->name('trackers.members.store');
    Route::patch('/trackers/{tracker}/members/{member}/role', [TrackerController::class, 'changeMemberRole'])->name('trackers.members.role.update');
    Route::get('/trackers/{tracker}/expenses/create', [TrackerController::class, 'expenseCreate'])->name('trackers.expenses.create');
    Route::post('/trackers/{tracker}/expenses', [TrackerController::class, 'storeExpense'])->name('trackers.expenses.store');
    Route::get('/trackers/{tracker}/expenses/{expense}/edit', [TrackerController::class, 'expenseEdit'])->name('trackers.expenses.edit');
    Route::patch('/trackers/{tracker}/expenses/{expense}', [TrackerController::class, 'updateExpense'])->name('trackers.expenses.update');
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
