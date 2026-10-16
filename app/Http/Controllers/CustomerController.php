<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Customer files (پرونده مشتریان) – a light CRM. */
class CustomerController extends Controller
{
    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize('viewAny', [Customer::class, $workspace]);

        $customers = $this->filtered($request, $workspace)
            ->with(['owner:id,name,avatar_path,last_seen_at', 'labels:id,name,color'])
            ->withCount('interactions')
            ->latest('updated_at')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (Customer $c) => [
                ...$c->only(['id', 'kind', 'name', 'company', 'phone', 'mobile', 'email', 'city', 'stage', 'deal_value']),
                'owner' => $c->owner?->toCard(),
                'labels' => $c->labels->map->only(['id', 'name', 'color']),
                'interactions_count' => $c->interactions_count,
                'updated_at' => $c->updated_at->toIso8601String(),
            ]);

        $pipeline = Customer::query()->inWorkspace($workspace)
            ->selectRaw('stage, count(*) as total, coalesce(sum(deal_value), 0) as value')
            ->groupBy('stage')->get()->keyBy('stage');

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'stages' => Customer::STAGES,
            'pipeline' => collect(Customer::STAGES)->map(fn ($label, $key) => [
                'key' => $key, 'label' => $label,
                'total' => (int) ($pipeline[$key]->total ?? 0),
                'value' => (int) ($pipeline[$key]->value ?? 0),
            ])->values(),
            'labels' => $workspace->labels()->where('scope', 'customer')->get(['id', 'name', 'color']),
            'filters' => $request->only(['q', 'stage', 'label', 'owner']),
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('create', [Customer::class, $workspace]);
        $data = $request->validate($this->rules());

        $customer = new Customer(Arr::only($data, (new Customer)->getFillable()));
        $customer->workspace_id = $workspace->id;
        $customer->created_by = $request->user()->id;
        $customer->owner_id = $this->owner($workspace, $data['owner_id'] ?? $request->user()->id);
        $customer->save();
        $customer->syncLabels($data['labels'] ?? [], 'customer');

        AuditLog::record('customer.created', $customer);

        return redirect()->route('customers.show', [$workspace, $customer])->with('success', 'مشتری ثبت شد.');
    }

    public function show(Request $request, Workspace $workspace, Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $customer->load([
            'owner:id,name,avatar_path,last_seen_at', 'creator:id,name', 'labels:id,name,color', 'attachments',
            'interactions' => fn ($q) => $q->with('user:id,name,avatar_path,last_seen_at')->limit(200),
        ]);

        return Inertia::render('Customers/Show', [
            'customer' => [
                ...$customer->only(['id', 'kind', 'name', 'company', 'job_title', 'phone', 'mobile', 'email', 'website', 'city', 'address', 'stage', 'deal_value', 'notes']),
                'owner' => $customer->owner?->toCard(),
                'owner_id' => $customer->owner_id,
                'creator' => $customer->creator?->name,
                'labels' => $customer->labels->map->only(['id', 'name', 'color']),
                'attachments' => $customer->attachments->map->toPayload(),
                'created_at' => $customer->created_at->toIso8601String(),
                'interactions' => $customer->interactions->map(fn (CustomerInteraction $i) => [
                    'id' => $i->id, 'type' => $i->type, 'body' => $i->body,
                    'user' => $i->user->toCard(), 'happened_at' => $i->happened_at->toIso8601String(),
                    'can_delete' => $i->user_id === $request->user()->id,
                ]),
            ],
            'stages' => Customer::STAGES,
            'types' => CustomerInteraction::TYPES,
            'labels' => $workspace->labels()->where('scope', 'customer')->get(['id', 'name', 'color']),
            'can' => ['delete' => $request->user()->can('delete', $customer)],
        ]);
    }

    public function update(Request $request, Workspace $workspace, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $data = $request->validate($this->rules());

        $customer->fill(Arr::only($data, $customer->getFillable()));
        $customer->owner_id = $this->owner($workspace, $data['owner_id'] ?? null);
        $customer->save();
        $customer->syncLabels($data['labels'] ?? [], 'customer');

        return back()->with('success', 'اطلاعات مشتری ذخیره شد.');
    }

    public function destroy(Workspace $workspace, Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        AuditLog::record('customer.deleted', $customer, ['name' => $customer->name]);
        $customer->delete();

        return redirect()->route('customers.index', $workspace)->with('success', 'مشتری حذف شد.');
    }

    public function storeInteraction(Request $request, Workspace $workspace, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(CustomerInteraction::TYPES))],
            'body' => ['required', 'string', 'max:5000'],
            'happened_at' => ['nullable', 'date', 'before_or_equal:+1 day'],
        ]);

        $interaction = new CustomerInteraction(['type' => $data['type'], 'body' => trim($data['body']), 'happened_at' => $data['happened_at'] ?? now()]);
        $interaction->customer_id = $customer->id;
        $interaction->user_id = $request->user()->id;
        $interaction->save();
        $customer->touch();

        return back();
    }

    public function destroyInteraction(Request $request, Workspace $workspace, Customer $customer, CustomerInteraction $interaction): RedirectResponse
    {
        $this->authorize('update', $customer);
        abort_unless($interaction->user_id === $request->user()->id, 403);

        $interaction->delete();

        return back();
    }

    /** CSV export, formula-injection safe, opened correctly by Excel (UTF-8 BOM). */
    public function export(Request $request, Workspace $workspace): StreamedResponse
    {
        $this->authorize('viewAny', [Customer::class, $workspace]);
        AuditLog::record('customer.exported', properties: [], workspaceId: $workspace->id);

        $query = $this->filtered($request, $workspace)->with('owner:id,name');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['نام', 'شرکت', 'سمت', 'تلفن', 'موبایل', 'ایمیل', 'شهر', 'مرحله', 'ارزش معامله', 'مسئول']);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $c) {
                    fputcsv($out, array_map([$this, 'csvSafe'], [
                        $c->name, $c->company, $c->job_title, $c->phone, $c->mobile, $c->email, $c->city,
                        Customer::STAGES[$c->stage] ?? $c->stage, $c->deal_value, $c->owner?->name,
                    ]));
                }
            });

            fclose($out);
        }, 'customers-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function filtered(Request $request, Workspace $workspace)
    {
        return Customer::query()->inWorkspace($workspace)
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', $this->like($request->string('q')))
                ->orWhere('company', 'like', $this->like($request->string('q')))
                ->orWhere('phone', 'like', $this->like($request->string('q')))
                ->orWhere('mobile', 'like', $this->like($request->string('q')))
                ->orWhere('email', 'like', $this->like($request->string('q')))))
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->string('stage')))
            ->when($request->filled('owner'), fn ($q) => $q->where('owner_id', $request->integer('owner')))
            ->when($request->filled('label'), fn ($q) => $q->whereHas('labels', fn ($l) => $l->whereKey($request->string('label'))));
    }

    private function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['person', 'company'])],
            'name' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9۰-۹+\-\s()]+$/u'],
            'mobile' => ['nullable', 'string', 'max:30', 'regex:/^[0-9۰-۹+\-\s()]+$/u'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
            'stage' => ['required', Rule::in(array_keys(Customer::STAGES))],
            'deal_value' => ['nullable', 'integer', 'min:0', 'max:999999999999999'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'owner_id' => ['nullable', 'integer'],
            'labels' => ['array', 'max:10'],
            'labels.*' => ['string', 'size:26'],
        ];
    }

    private function owner(Workspace $workspace, ?int $id): ?int
    {
        return $id ? ($workspace->filterMemberIds([$id])[0] ?? null) : null;
    }
}
