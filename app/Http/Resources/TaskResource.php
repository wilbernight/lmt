<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'priority' => $this->priority,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'position' => $this->position,
            'column_id' => $this->column_id,
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'color' => $this->project->color,
            ] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category?->only(['id', 'name', 'color'])),
            'labels' => $this->whenLoaded('labels', fn () => $this->labels->map->only(['id', 'name', 'color'])),
            'assignees' => $this->whenLoaded('assignees', fn () => $this->assignees->map->toCard()),
            'people' => $this->whenLoaded('people', fn () => $this->people->map->toCard()),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator->toCard()),
            'checklist_total' => $this->whenCounted('checklist'),
            'checklist_done' => $this->when(isset($this->checklist_done_count), fn () => $this->checklist_done_count),
            'comments_count' => $this->whenCounted('comments'),
            'attachments_count' => $this->whenCounted('attachments'),
        ];
    }

    /** Relations and counts every task card needs. */
    public static function withCard(): array
    {
        return [
            'project:id,name,color',
            'category:id,name,color',
            'labels:id,name,color',
            'assignees:id,name,avatar_path,last_seen_at',
            'people:id,name',
            'creator:id,name,avatar_path,last_seen_at',
        ];
    }

    public static function cardCounts(): array
    {
        return [
            'checklist',
            'checklist as checklist_done_count' => fn ($q) => $q->where('is_done', true),
            'comments',
            'attachments',
        ];
    }
}
