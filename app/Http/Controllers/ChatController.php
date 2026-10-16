<?php

namespace App\Http\Controllers;

use App\Events\ConversationEvent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ActivityNotification;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function index(Request $request, Workspace $workspace, ?Conversation $conversation = null): Response
    {
        $user = $request->user();

        if ($conversation) {
            $this->authorize('view', $conversation);
            $this->markRead($conversation, $user);
        }

        return Inertia::render('Chat/Index', [
            'conversations' => $this->conversationList($workspace, $user),
            'active' => $conversation ? $this->conversationPayload($conversation, $user) : null,
            'messages' => $conversation ? $this->page($conversation) : [],
        ]);
    }

    /** Older messages, for infinite scroll. */
    public function messages(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $before = $request->validate(['before' => ['nullable', 'string', 'size:26']])['before'] ?? null;

        return response()->json($this->page($conversation, $before));
    }

    public function direct(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = $request->user();
        $otherId = (int) $request->validate(['user_id' => ['required', 'integer']])['user_id'];

        abort_if($otherId === $user->id || $workspace->filterMemberIds([$otherId]) === [], 422);

        $key = Conversation::directKey($workspace->id, $user->id, $otherId);

        $conversation = DB::transaction(function () use ($workspace, $user, $otherId, $key) {
            $existing = Conversation::where('direct_key', $key)->lockForUpdate()->first();

            if ($existing) {
                return $existing;
            }

            $conversation = new Conversation(['type' => 'direct']);
            $conversation->workspace_id = $workspace->id;
            $conversation->direct_key = $key;
            $conversation->created_by = $user->id;
            $conversation->save();
            $conversation->participants()->attach([$user->id => ['role' => 'member'], $otherId => ['role' => 'member']]);

            return $conversation;
        });

        return redirect()->route('chat.show', [$workspace, $conversation]);
    }

    public function storeGroup(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('contribute', $workspace);
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'members' => ['required', 'array', 'min:1', 'max:200'],
            'members.*' => ['integer'],
        ]);

        $conversation = DB::transaction(function () use ($workspace, $user, $data) {
            $conversation = new Conversation(['type' => 'group', 'name' => trim($data['name'])]);
            $conversation->workspace_id = $workspace->id;
            $conversation->created_by = $user->id;
            $conversation->save();

            $members = collect($workspace->filterMemberIds($data['members']))
                ->mapWithKeys(fn ($id) => [$id => ['role' => 'member']])
                ->put($user->id, ['role' => 'admin']);

            $conversation->participants()->attach($members);

            return $conversation;
        });

        return redirect()->route('chat.show', [$workspace, $conversation]);
    }

    public function updateGroup(Request $request, Workspace $workspace, Conversation $conversation): RedirectResponse
    {
        $this->authorize('manage', $conversation);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'members' => ['required', 'array', 'min:1', 'max:200'],
            'members.*' => ['integer'],
        ]);

        $conversation->update(['name' => trim($data['name'])]);

        $admins = $conversation->participants()->wherePivot('role', 'admin')->pluck('users.id');
        $members = collect($workspace->filterMemberIds($data['members']))
            ->mapWithKeys(fn ($id) => [$id => ['role' => $admins->contains($id) ? 'admin' : 'member']])
            ->put($request->user()->id, ['role' => 'admin']);

        $conversation->participants()->sync($members);

        return back()->with('success', 'گروه به‌روزرسانی شد.');
    }

    public function leave(Request $request, Workspace $workspace, Conversation $conversation): RedirectResponse
    {
        $this->authorize('view', $conversation);
        abort_unless($conversation->type === 'group', 422);

        $conversation->participants()->detach($request->user()->id);

        if (! $conversation->participants()->wherePivot('role', 'admin')->exists()) {
            $next = $conversation->participants()->first();
            $next && $conversation->participants()->updateExistingPivot($next->id, ['role' => 'admin']);
        }

        return redirect()->route('chat.index', $workspace);
    }

    public function send(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        $this->authorize('send', $conversation);
        $user = $request->user();

        $data = $request->validate([
            'body' => ['nullable', 'required_without:attachments', 'string', 'max:4000'],
            'reply_to_id' => ['nullable', 'string', 'size:26'],
            'attachments' => ['array', 'max:10'],
            'attachments.*' => ['string', 'size:26'],
        ]);

        $replyTo = isset($data['reply_to_id'])
            ? $conversation->messages()->whereKey($data['reply_to_id'])->value('id')
            : null;

        $message = new Message(['body' => isset($data['body']) ? trim($data['body']) : null, 'reply_to_id' => $replyTo]);
        $message->conversation_id = $conversation->id;
        $message->user_id = $user->id;
        $message->save();
        $message->claimAttachments($data['attachments'] ?? [], $user->id, $workspace->id);

        $conversation->forceFill(['last_message_at' => now()])->save();
        $this->markRead($conversation, $user);

        $payload = $message->load(['user', 'replyTo.user', 'attachments'])->toPayload();
        Realtime::send(new ConversationEvent($conversation->id, 'message.created', $payload));

        // Ping offline, non-muted participants.
        $recipients = $conversation->participants()
            ->where('users.id', '!=', $user->id)
            ->wherePivot('muted', false)
            ->get()
            ->filter(fn (User $u) => ! $u->last_seen_at || $u->last_seen_at->lt(now()->subMinutes(3)));

        ActivityNotification::dispatch($recipients, new ActivityNotification(
            'chat.message',
            $conversation->type === 'group' ? "پیام جدید در «{$conversation->name}»" : "پیام جدید از {$user->name}",
            $message->body,
            route('chat.show', [$workspace, $conversation]),
            $workspace->id,
            $user->toCard(),
        ), $user->id);

        return response()->json($payload, 201);
    }

    public function updateMessage(Request $request, Workspace $workspace, Conversation $conversation, Message $message): JsonResponse
    {
        $this->authorize('updateMessage', [$conversation, $message]);

        $message->fill($request->validate(['body' => ['required', 'string', 'max:4000']]));
        $message->edited_at = now();
        $message->save();

        $payload = $message->load(['user', 'replyTo.user', 'attachments'])->toPayload();
        Realtime::send(new ConversationEvent($conversation->id, 'message.updated', $payload));

        return response()->json($payload);
    }

    public function destroyMessage(Workspace $workspace, Conversation $conversation, Message $message): JsonResponse
    {
        $this->authorize('deleteMessage', [$conversation, $message]);

        $message->delete();
        Realtime::send(new ConversationEvent($conversation->id, 'message.deleted', ['id' => $message->id]));

        return response()->json(['ok' => true]);
    }

    public function read(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $this->markRead($conversation, $request->user());

        return response()->json(['ok' => true]);
    }

    public function mute(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $muted = ! (bool) $conversation->participants()->whereKey($request->user()->id)->value('conversation_user.muted');
        $conversation->participants()->updateExistingPivot($request->user()->id, ['muted' => $muted]);

        return response()->json(['muted' => $muted]);
    }

    // ------------------------------------------------------------------

    private function markRead(Conversation $conversation, User $user): void
    {
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
    }

    private function page(Conversation $conversation, ?string $before = null): array
    {
        return $conversation->messages()
            ->withTrashed()
            ->when($before, fn ($q) => $q->where('id', '<', $before))
            ->with(['user', 'replyTo.user', 'attachments'])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map->toPayload()
            ->all();
    }

    private function conversationList(Workspace $workspace, User $user): array
    {
        return Conversation::query()
            ->inWorkspace($workspace)
            ->forUser($user)
            ->with(['participants:id,name,avatar_path,last_seen_at', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Conversation $c) use ($user) {
                $me = $c->participants->firstWhere('id', $user->id);
                $lastRead = $me?->pivot->last_read_at;

                return [
                    ...$this->conversationPayload($c, $user),
                    'last_message' => $c->latestMessage && ! $c->latestMessage->trashed()
                        ? mb_substr((string) $c->latestMessage->body, 0, 80) ?: '📎 فایل'
                        : null,
                    'last_message_at' => $c->last_message_at?->toIso8601String(),
                    'unread' => $c->messages()
                        ->where('user_id', '!=', $user->id)
                        ->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))
                        ->count(),
                ];
            })
            ->all();
    }

    private function conversationPayload(Conversation $conversation, User $user): array
    {
        $participants = $conversation->relationLoaded('participants')
            ? $conversation->participants
            : $conversation->participants()->get(['users.id', 'users.name', 'users.avatar_path', 'users.last_seen_at']);

        $other = $conversation->type === 'direct' ? $participants->firstWhere('id', '!=', $user->id) : null;
        $me = $participants->firstWhere('id', $user->id);

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'name' => $other?->name ?? $conversation->name,
            'other' => $other?->toCard(),
            'participants' => $participants->map(fn ($p) => [...$p->toCard(), 'role' => $p->pivot->role]),
            'muted' => (bool) $me?->pivot->muted,
            'can_manage' => $conversation->type === 'group' && $me?->pivot->role === 'admin',
        ];
    }
}
