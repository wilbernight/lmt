<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWorkspaceController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ColumnController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaxonomyController;
use App\Http\Controllers\TimeController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

Route::get('/invitations/{token}', [InvitationController::class, 'show'])
    ->middleware('throttle:30,1')
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('invitations.show');

Route::middleware(['auth', 'throttle:app'])->group(function () {
    // Available before the email address is verified.
    Route::get('/settings/profile', [SettingsController::class, 'profile'])->name('settings.profile');
    Route::get('/avatars/{user}', [SettingsController::class, 'showAvatar'])->name('avatars.show');
});

Route::middleware(['auth', 'verified', 'throttle:app'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'redirect'])->name('dashboard');

    Route::post('/invitations/{token}', [InvitationController::class, 'accept'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('invitations.accept');

    Route::get('/workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->middleware('throttle:10,60')->name('workspaces.store');

    // Account
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/security', [SettingsController::class, 'security'])->middleware('password.confirm')->name('security');
        Route::post('/avatar', [SettingsController::class, 'avatar'])->middleware('throttle:uploads')->name('avatar');
        Route::delete('/avatar', [SettingsController::class, 'removeAvatar'])->name('avatar.destroy');
        Route::delete('/sessions', [SettingsController::class, 'logoutOtherSessions'])->middleware('throttle:6,1')->name('sessions.destroy');
        Route::delete('/account', [SettingsController::class, 'destroyAccount'])->middleware('throttle:3,1')->name('account.destroy');
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');

    Route::post('/timer/stop', [TimeController::class, 'stop'])->name('timer.stop');

    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Site administration. Hidden (404) for non-admins; sensitive pages re-ask for the password.
    Route::prefix('admin')->name('admin.')->middleware(['site-admin', 'password.confirm', 'throttle:writes'])->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/unsuspend', [AdminUserController::class, 'unsuspend'])->name('users.unsuspend');
        Route::post('/users/{user}/logout', [AdminUserController::class, 'logout'])->name('users.logout');
        Route::post('/users/{user}/reset-2fa', [AdminUserController::class, 'resetTwoFactor'])->name('users.reset-2fa');
        Route::post('/users/{user}/verify', [AdminUserController::class, 'verify'])->name('users.verify');
        Route::get('/workspaces', [AdminWorkspaceController::class, 'index'])->name('workspaces.index');
        Route::put('/workspaces/{workspace}', [AdminWorkspaceController::class, 'update'])->name('workspaces.update');
        Route::post('/workspaces/{workspace}/lock', [AdminWorkspaceController::class, 'lock'])->name('workspaces.lock');
        Route::get('/settings', [AdminSettingsController::class, 'show'])->name('settings');
        Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    });

    // Everything inside a workspace. The "workspace" middleware returns 404 to non-members.
    Route::prefix('w/{workspace}')->middleware('workspace')->scopeBindings()->group(function () {
        Route::get('/', [DashboardController::class, 'show'])->name('workspace.dashboard');
        Route::get('/search', SearchController::class)->middleware('throttle:search')->name('search');
        Route::post('/attachments', [AttachmentController::class, 'store'])->middleware('throttle:uploads')->name('attachments.store');

        // Workspace administration
        Route::get('/settings', [WorkspaceController::class, 'settings'])->name('workspace.settings');
        Route::put('/settings', [WorkspaceController::class, 'update'])->name('workspace.update');
        Route::delete('/', [WorkspaceController::class, 'destroy'])->middleware('throttle:3,1')->name('workspace.destroy');
        Route::put('/members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('/members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
        Route::post('/invitations', [InvitationController::class, 'store'])->middleware('throttle:invitations')->name('invitations.store');
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

        Route::post('/labels', [TaxonomyController::class, 'storeLabel'])->name('labels.store');
        Route::put('/labels/{label}', [TaxonomyController::class, 'updateLabel'])->name('labels.update');
        Route::delete('/labels/{label}', [TaxonomyController::class, 'destroyLabel'])->name('labels.destroy');
        Route::post('/categories', [TaxonomyController::class, 'storeCategory'])->name('categories.store');
        Route::put('/categories/{category}', [TaxonomyController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [TaxonomyController::class, 'destroyCategory'])->name('categories.destroy');

        Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring');
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/export', [AttendanceController::class, 'export'])->middleware('throttle:10,1')->name('attendance.export');

        // Projects & board
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('/projects', [ProjectController::class, 'store'])->middleware('throttle:writes')->name('projects.store');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::put('/projects/{project}/members', [ProjectController::class, 'members'])->name('projects.members');
        Route::post('/projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::get('/projects/{project}/time', [TimeController::class, 'project'])->name('projects.time');
        Route::post('/projects/{project}/columns', [ColumnController::class, 'store'])->name('columns.store');
        Route::put('/projects/{project}/columns/reorder', [ColumnController::class, 'reorder'])->name('columns.reorder');
        Route::put('/projects/{project}/columns/{column}', [ColumnController::class, 'update'])->name('columns.update');
        Route::delete('/projects/{project}/columns/{column}', [ColumnController::class, 'destroy'])->name('columns.destroy');

        // Tasks
        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::get('/calendar', [TaskController::class, 'calendar'])->name('tasks.calendar');
        Route::get('/tasks/options', [TaskController::class, 'options'])->name('tasks.options');
        Route::post('/tasks', [TaskController::class, 'store'])->middleware('throttle:writes')->name('tasks.store');
        Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
        Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
        Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
        Route::post('/tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
        Route::post('/tasks/{task}/watch', [TaskController::class, 'watch'])->name('tasks.watch');
        Route::post('/tasks/{task}/bookmark', [TaskController::class, 'bookmark'])->name('tasks.bookmark');
        Route::post('/tasks/{task}/checklist', [ChecklistController::class, 'store'])->name('checklist.store');
        Route::put('/tasks/{task}/checklist/reorder', [ChecklistController::class, 'reorder'])->name('checklist.reorder');
        Route::patch('/tasks/{task}/checklist/{checklistItem}', [ChecklistController::class, 'update'])->name('checklist.update');
        Route::delete('/tasks/{task}/checklist/{checklistItem}', [ChecklistController::class, 'destroy'])->name('checklist.destroy');
        Route::post('/tasks/{task}/timer', [TimeController::class, 'start'])->middleware('throttle:writes')->name('timer.start');
        Route::post('/tasks/{task}/time', [TimeController::class, 'store'])->middleware('throttle:writes')->name('time.store');
        Route::delete('/tasks/{task}/time/{timeEntry}', [TimeController::class, 'destroy'])->name('time.destroy');
        Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store'])->middleware('throttle:writes')->name('comments.store');
        Route::patch('/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'update'])->name('comments.update');
        Route::delete('/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('comments.destroy');

        // Chat
        Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
        Route::post('/chat/direct', [ChatController::class, 'direct'])->name('chat.direct');
        Route::post('/chat/groups', [ChatController::class, 'storeGroup'])->middleware('throttle:writes')->name('chat.groups.store');
        Route::get('/chat/{conversation}', [ChatController::class, 'index'])->name('chat.show');
        Route::put('/chat/{conversation}', [ChatController::class, 'updateGroup'])->name('chat.groups.update');
        Route::post('/chat/{conversation}/leave', [ChatController::class, 'leave'])->name('chat.leave');
        Route::get('/chat/{conversation}/messages', [ChatController::class, 'messages'])->name('chat.messages');
        Route::post('/chat/{conversation}/messages', [ChatController::class, 'send'])->middleware('throttle:messages')->name('chat.send');
        Route::patch('/chat/{conversation}/messages/{message}', [ChatController::class, 'updateMessage'])->name('chat.messages.update');
        Route::delete('/chat/{conversation}/messages/{message}', [ChatController::class, 'destroyMessage'])->name('chat.messages.destroy');
        Route::post('/chat/{conversation}/read', [ChatController::class, 'read'])->name('chat.read');
        Route::post('/chat/{conversation}/mute', [ChatController::class, 'mute'])->name('chat.mute');

        // Letters
        Route::get('/letters', [LetterController::class, 'index'])->name('letters.index');
        Route::get('/letters/create', [LetterController::class, 'create'])->name('letters.create');
        Route::post('/letters', [LetterController::class, 'store'])->middleware('throttle:writes')->name('letters.store');
        Route::get('/letters/{letter}', [LetterController::class, 'show'])->name('letters.show');
        Route::get('/letters/{letter}/print', [LetterController::class, 'print'])->middleware('throttle:30,1')->name('letters.print');
        Route::post('/letters/{letter}/reply', [LetterController::class, 'reply'])->middleware('throttle:writes')->name('letters.reply');
        Route::post('/letters/{letter}/refer', [LetterController::class, 'refer'])->middleware('throttle:writes')->name('letters.refer');
        Route::post('/letters/{letter}/archive', [LetterController::class, 'archive'])->name('letters.archive');
        Route::put('/letters/{letter}/labels', [LetterController::class, 'labels'])->name('letters.labels');

        // Notes
        Route::get('/notes', [NoteController::class, 'index'])->name('notes.index');
        Route::post('/notes', [NoteController::class, 'store'])->middleware('throttle:writes')->name('notes.store');
        Route::patch('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
        Route::post('/notes/{note}/archive', [NoteController::class, 'archive'])->name('notes.archive');
        Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
        Route::post('/notes/{note}/restore', [NoteController::class, 'restore'])->withoutScopedBindings()->name('notes.restore');
        Route::delete('/notes/{note}/force', [NoteController::class, 'forceDestroy'])->withoutScopedBindings()->name('notes.force-destroy');

        // Customers
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/export', [CustomerController::class, 'export'])->middleware('throttle:10,1')->name('customers.export');
        Route::post('/customers', [CustomerController::class, 'store'])->middleware('throttle:writes')->name('customers.store');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::post('/customers/{customer}/interactions', [CustomerController::class, 'storeInteraction'])->name('customers.interactions.store');
        Route::delete('/customers/{customer}/interactions/{interaction}', [CustomerController::class, 'destroyInteraction'])->name('customers.interactions.destroy');
    });
});
