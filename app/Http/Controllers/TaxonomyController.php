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
            'name' => ['required', 'string', 'max:40', Rule::unique('labels')->where('workspace_id', $workspace->id)->where('scope', $request->input('scope'))],
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
            'name' => ['required', 'string', 'max:40', Rule::unique('labels')->where('workspace_id', $workspace->id)->where('scope', $label->scope)->ignore($label->id)],
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
            'name' => ['required', 'string', 'max:60', Rule::unique('task_categories')->where('workspace_id', $workspace->id)],
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
            'name' => ['required', 'string', 'max:60', Rule::unique('task_categories')->where('workspace_id', $workspace->id)->ignore($category->id)],
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
}
