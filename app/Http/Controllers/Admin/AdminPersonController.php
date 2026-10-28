<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The directory of people without an account (task assignees, letter
 * recipients, meeting participants) — editable from the site admin panel.
 */
class AdminPersonController extends Controller
{
    public function index(Request $request): Response
    {
        $rows = Person::query()
            ->when($request->filled('q'), fn ($q) => $q->whereIn('people.id', $this->matchingIds($q->clone(), ['name'], (string) $request->string('q'))))
            ->with(['workspace:id,name,color', 'creator:id,name'])
            ->withCount(['tasks', 'letters', 'minutes', 'minuteActions'])
            ->latest('created_at')
            ->get();

        // Names are encrypted at rest, so they are sorted after decryption.
        $people = $rows->sortBy('name')->values()->map(fn (Person $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'initials' => $p->initials(),
            'workspace' => ['id' => $p->workspace->id, 'name' => $p->workspace->name, 'color' => $p->workspace->color],
            'creator' => $p->creator?->name,
            'tasks_count' => $p->tasks_count,
            'letters_count' => $p->letters_count,
            'minutes_count' => $p->minutes_count,
            'actions_count' => $p->minute_actions_count,
            'created_at' => $p->created_at->toIso8601String(),
        ]);

        $page = LengthAwarePaginator::resolveCurrentPage();

        return Inertia::render('Admin/People', [
            'people' => (new LengthAwarePaginator(
                $people->forPage($page, 25)->values(),
                $people->count(),
                25,
                $page,
                ['path' => route('admin.people.index'), 'pageName' => 'page'],
            ))->withQueryString(),
            'filters' => $request->only(['q']),
        ]);
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        $name = (string) $request->string('name');
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'نام باید حداکثر ۱۲۰ نویسه باشد.']);
        }

        // The normaliser folds Persian variants, so duplicates are caught even with a different spelling.
        $clash = $person->workspace->people()->get()
            ->first(fn (Person $p) => $p->id !== $person->id && Person::normalize($p->name) === Person::normalize($name));

        if ($clash !== null) {
            throw ValidationException::withMessages(['name' => 'در این میزکار شخصی با همین نام وجود دارد.']);
        }

        $before = $person->name;
        $person->forceFill(['name' => $name])->save();

        AuditLog::record('admin.person_renamed', $person, ['from' => $before, 'to' => $name]);

        return back()->with('success', 'نام شخص به‌روزرسانی شد.');
    }

    public function destroy(Request $request, Person $person): RedirectResponse
    {
        AuditLog::record('admin.person_deleted', $person, ['name' => $person->name]);
        $person->delete();

        return back()->with('success', 'شخص حذف شد.');
    }
}
