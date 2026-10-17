<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NoteController extends Controller
{
    public const VIEWS = ['all', 'archived', 'trash'];

    public function index(Request $request, Workspace $workspace): Response
    {
        $user = $request->user();
        $view = in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'all';

        $notes = Note::query()
            ->inWorkspace($workspace)
            ->where('user_id', $user->id)
            ->when($view === 'all', fn ($q) => $q->whereNull('archived_at'))
            ->when($view === 'archived', fn ($q) => $q->whereNotNull('archived_at'))
            ->when($view === 'trash', fn ($q) => $q->onlyTrashed())
            ->when($request->filled('label'), fn ($q) => $q->whereHas('labels', fn ($l) => $l->whereKey($request->string('label'))))
            ->when($request->filled('q'), fn ($q) => $q->whereIn('notes.id', $this->matchingIds($q->clone(), ['title', 'body', 'items'], (string) $request->string('q'))))
            ->with('labels:id,name,color')
            ->orderByDesc('pinned')
            ->latest('updated_at')
            ->limit(500)
            ->get()
            ->map(fn (Note $n) => $this->payload($n));

        return Inertia::render('Notes/Index', [
            'view' => $view,
            'notes' => $notes,
            'labels' => $workspace->labels()->where('scope', 'note')->get(['id', 'name', 'color']),
            'colors' => Note::COLORS,
            'filters' => $request->only(['q', 'label']),
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $data = $request->validate($this->rules());

        abort_if(Note::where('user_id', $request->user()->id)->count() >= 2000, 422);

        $note = new Note($this->clean($data));
        $note->workspace_id = $workspace->id;
        $note->user_id = $request->user()->id;
        $note->save();
        $note->syncLabels($data['labels'] ?? [], 'note');

        return back();
    }

    public function update(Request $request, Workspace $workspace, Note $note): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $note);

        $data = $request->validate(collect($this->rules())->map(fn ($r) => ['sometimes', ...$r])->all());

        $note->update($this->clean($data));

        if (array_key_exists('labels', $data)) {
            $note->syncLabels($data['labels'] ?? [], 'note');
        }

        return $request->wantsJson() ? response()->json($this->payload($note->load('labels'))) : back();
    }

    public function archive(Workspace $workspace, Note $note): RedirectResponse
    {
        $this->authorize('update', $note);

        $note->forceFill(['archived_at' => $note->archived_at ? null : now(), 'pinned' => false])->save();

        return back();
    }

    public function destroy(Workspace $workspace, Note $note): RedirectResponse
    {
        $this->authorize('delete', $note);

        $note->delete();

        return back()->with('success', 'یادداشت به سطل زباله رفت.');
    }

    public function restore(Workspace $workspace, string $note): RedirectResponse
    {
        $model = Note::onlyTrashed()->inWorkspace($workspace)->findOrFail($note);
        $this->authorize('delete', $model);

        $model->restore();

        return back()->with('success', 'یادداشت بازیابی شد.');
    }

    public function forceDestroy(Workspace $workspace, string $note): RedirectResponse
    {
        $model = Note::onlyTrashed()->inWorkspace($workspace)->findOrFail($note);
        $this->authorize('delete', $model);

        $model->labels()->detach();
        $model->forceDelete();

        return back()->with('success', 'یادداشت برای همیشه حذف شد.');
    }

    private function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:20000'],
            'color' => ['nullable', Rule::in(Note::COLORS)],
            'pinned' => ['boolean'],
            'items' => ['nullable', 'array', 'max:100'],
            'items.*.text' => ['required', 'string', 'max:300'],
            'items.*.done' => ['boolean'],
            'labels' => ['array', 'max:10'],
            'labels.*' => ['string', 'size:26'],
        ];
    }

    /** Keep only known keys inside checklist items. */
    private function clean(array $data): array
    {
        if (array_key_exists('items', $data)) {
            $data['items'] = collect($data['items'] ?? [])
                ->map(fn ($i) => ['id' => Str::random(8), 'text' => trim($i['text']), 'done' => (bool) ($i['done'] ?? false)])
                ->values()->all() ?: null;
        }

        return Arr::only($data, ['title', 'body', 'items', 'color', 'pinned']);
    }

    private function payload(Note $note): array
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'body' => $note->body,
            'items' => $note->items ?? [],
            'color' => $note->color,
            'pinned' => $note->pinned,
            'archived' => $note->archived_at !== null,
            'trashed' => $note->trashed(),
            'labels' => $note->labels->map->only(['id', 'name', 'color']),
            'updated_at' => $note->updated_at->toIso8601String(),
        ];
    }
}
