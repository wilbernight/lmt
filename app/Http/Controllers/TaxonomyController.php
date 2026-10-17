<?php

namespace App\Http\Controllers;

use App\Models\Label;
use App\Models\TaskCategory;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Labels (برچسب‌ها) and task categories (دسته‌بندی کارها). */
class TaxonomyController extends Controller
{
    private const COLORS = ['slate', 'rose', 'orange', 'amber', 'lime', 'emerald', 'teal', 'sky', 'indigo', 'violet', 'pink'];

    public function storeLabel(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('contribute', $workspace);

        $data = $request->validate([
            'scope' => ['required', Rule::in(Label::SCOPES)],
            'name' => ['required', 'string', 'max:40', $this->uniqueName($workspace->labels()->where('scope', $request->input('scope')))],
            'color' => ['required', Rule::in(self::COLORS)],
        ]);

        abort_if($workspace->labels()->count() >= 200, 422);

        $workspace->labels()->create($data);

        return back()->with('success', 'برچسب ساخته شد.');
    }

    public function updateLabel(Request $request, Workspace $workspace, Label $label): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $label->update($request->validate([
            'name' => ['required', 'string', 'max:40', $this->uniqueName($workspace->labels()->where('scope', $label->scope)->whereKeyNot($label->id))],
            'color' => ['required', Rule::in(self::COLORS)],
        ]));

        return back();
    }

    public function destroyLabel(Workspace $workspace, Label $label): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $label->delete();

        return back()->with('success', 'برچسب حذف شد.');
    }

    public function storeCategory(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('contribute', $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', $this->uniqueName($workspace->categories())],
            'color' => ['required', Rule::in(self::COLORS)],
        ]);

        abort_if($workspace->categories()->count() >= 100, 422);

        $workspace->categories()->create($data);

        return back()->with('success', 'دسته‌بندی ساخته شد.');
    }

    public function updateCategory(Request $request, Workspace $workspace, TaskCategory $category): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $category->update($request->validate([
            'name' => ['required', 'string', 'max:60', $this->uniqueName($workspace->categories()->whereKeyNot($category->id))],
            'color' => ['required', Rule::in(self::COLORS)],
        ]));

        return back();
    }

    public function destroyCategory(Workspace $workspace, TaskCategory $category): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $category->delete();

        return back()->with('success', 'دسته‌بندی حذف شد.');
    }

    /** A validation rule: no other record in the query has this name (names are encrypted, so compared decrypted). */
    private function uniqueName($query): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($query) {
            $wanted = $this->normaliseSearch((string) $value);

            if ($query->get(['id', 'name'])->contains(fn ($m) => $this->normaliseSearch((string) $m->name) === $wanted)) {
                $fail('این نام قبلاً استفاده شده است.');
            }
        };
    }
}
