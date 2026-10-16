<?php

namespace App\Http\Controllers;

use App\Events\BoardChanged;
use App\Models\ChecklistItem;
use App\Models\Task;
use App\Models\Workspace;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistController extends Controller
{
    public function store(Request $request, Workspace $workspace, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate(['body' => ['required', 'string', 'max:300']]);
        abort_if($task->checklist()->count() >= 100, 422);

        $item = $task->checklist()->create([
            'body' => trim($data['body']),
            'position' => (int) $task->checklist()->max('position') + 1,
        ]);

        $this->changed($task);

        return response()->json($item->only(['id', 'body', 'is_done', 'position']), 201);
    }

    public function update(Request $request, Workspace $workspace, Task $task, ChecklistItem $checklistItem): JsonResponse
    {
        $data = $request->validate([
            'body' => ['sometimes', 'required', 'string', 'max:300'],
            'is_done' => ['sometimes', 'boolean'],
        ]);

        // Ticking an item is "work"; rewording it is an edit.
        $this->authorize(array_key_exists('body', $data) ? 'update' : 'work', $task);

        $checklistItem->fill($data);

        if (array_key_exists('is_done', $data)) {
            $checklistItem->done_by = $data['is_done'] ? $request->user()->id : null;
            $task->log($data['is_done'] ? 'checked' : 'unchecked', ['item' => $checklistItem->body]);
        }

        $checklistItem->save();
        $this->changed($task);

        return response()->json(['ok' => true, 'progress' => $task->progress()]);
    }

    public function reorder(Request $request, Workspace $workspace, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $ids = $request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['string']])['ids'];

        DB::transaction(function () use ($task, $ids) {
            foreach (array_values($ids) as $position => $id) {
                $task->checklist()->whereKey($id)->update(['position' => $position]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function destroy(Workspace $workspace, Task $task, ChecklistItem $checklistItem): JsonResponse
    {
        $this->authorize('update', $task);

        $checklistItem->delete();
        $this->changed($task);

        return response()->json(['ok' => true, 'progress' => $task->progress()]);
    }

    private function changed(Task $task): void
    {
        $task->touch();

        if ($task->project_id) {
            Realtime::send(new BoardChanged($task->project_id));
        }
    }
}
