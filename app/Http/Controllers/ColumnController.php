<?php

namespace App\Http\Controllers;

use App\Events\BoardChanged;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Workspace;
use App\Support\Realtime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ColumnController extends Controller
{
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', Rule::in(ProjectController::COLORS)],
            'is_done' => ['boolean'],
            'wip_limit' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function store(Request $request, Workspace $workspace, Project $project): RedirectResponse
    {
        $this->authorize('manage', $project);

        abort_if($project->columns()->count() >= 20, 422, 'حداکثر ۲۰ ستون مجاز است.');

        $project->columns()->create($request->validate($this->rules()) + [
            'position' => (int) $project->columns()->max('position') + 1,
        ]);

        Realtime::send(new BoardChanged($project->id));

        return back();
    }

    public function update(Request $request, Workspace $workspace, Project $project, BoardColumn $column): RedirectResponse
    {
        $this->authorize('manage', $project);

        $column->update($request->validate($this->rules()));
        Realtime::send(new BoardChanged($project->id));

        return back();
    }

    public function reorder(Request $request, Workspace $workspace, Project $project): RedirectResponse
    {
        $this->authorize('manage', $project);

        $ids = $request->validate(['ids' => ['required', 'array', 'max:20'], 'ids.*' => ['string']])['ids'];

        DB::transaction(function () use ($project, $ids) {
            foreach (array_values($ids) as $position => $id) {
                $project->columns()->whereKey($id)->update(['position' => $position]);
            }
        });

        Realtime::send(new BoardChanged($project->id));

        return back();
    }

    public function destroy(Workspace $workspace, Project $project, BoardColumn $column): RedirectResponse
    {
        $this->authorize('manage', $project);

        abort_if($project->columns()->count() <= 1, 422, 'پروژه باید حداقل یک ستون داشته باشد.');

        DB::transaction(function () use ($project, $column) {
            $fallback = $project->columns()->whereKeyNot($column->id)->orderBy('position')->first();
            $column->tasks()->update(['column_id' => $fallback->id]);
            $column->delete();
        });

        Realtime::send(new BoardChanged($project->id));

        return back()->with('success', 'ستون حذف شد و کارهایش به ستون اول منتقل شدند.');
    }
}
