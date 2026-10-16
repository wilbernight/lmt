<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ActivityNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Task reports (ثبت گزارش) with mentions, progress and time spent. */
class TaskCommentController extends Controller
{
    public function store(Request $request, Workspace $workspace, Task $task): JsonResponse
    {
        $this->authorize('comment', $task);
        $user = $request->user();

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'spent_minutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'mentions' => ['array', 'max:20'],
            'mentions.*' => ['integer'],
            'attachments' => ['array', 'max:10'],
            'attachments.*' => ['string', 'size:26'],
        ]);

        $comment = new TaskComment(['body' => trim($data['body']), 'progress' => $data['progress'] ?? null, 'spent_minutes' => $data['spent_minutes'] ?? null]);
        $comment->task_id = $task->id;
        $comment->user_id = $user->id;
        $comment->save();
        $comment->claimAttachments($data['attachments'] ?? [], $user->id, $workspace->id);

        $task->log('commented');
        $task->touch();

        $url = route('tasks.index', [$workspace, 'task' => $task->id]);

        // Mentioned members who can actually see the task.
        $mentioned = User::whereIn('id', $workspace->filterMemberIds($data['mentions'] ?? []))->get()
            ->filter(fn (User $u) => $u->can('view', $task));

        ActivityNotification::dispatch($mentioned, new ActivityNotification(
            'task.mentioned', "{$user->name} در «{$task->title}» از شما نام برد", $comment->body, $url, $workspace->id, $user->toCard(),
        ), $user->id);

        $involved = $task->assignees()->get()
            ->push($task->creator)
            ->merge($task->watchers()->get())
            ->unique('id')
            ->reject(fn (User $u) => $mentioned->contains('id', $u->id));

        ActivityNotification::dispatch($involved, new ActivityNotification(
            'task.commented', "{$user->name} برای «{$task->title}» گزارش ثبت کرد", $comment->body, $url, $workspace->id, $user->toCard(),
        ), $user->id);

        return response()->json(['id' => $comment->id], 201);
    }

    public function update(Request $request, Workspace $workspace, Task $task, TaskComment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $comment->fill($request->validate(['body' => ['required', 'string', 'max:5000']]));
        $comment->edited_at = now();
        $comment->save();

        return response()->json(['ok' => true]);
    }

    public function destroy(Workspace $workspace, Task $task, TaskComment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->json(['ok' => true]);
    }
}
