<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Morilog\Jalali\CalendarUtils;

/**
 * Someone without an account, referenced by free-typed name: task assignee,
 * letter recipient, meeting participant.
 */
class Person extends Model
{
    use BelongsToWorkspace, HasUlids;

    protected $fillable = ['name'];

    protected function casts(): array
    {
        return ['name' => 'encrypted'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_person')->withTimestamps();
    }

    public function letters(): BelongsToMany
    {
        return $this->belongsToMany(Letter::class, 'letter_recipient_people')->withPivot('type')->withTimestamps();
    }

    public function minutes(): BelongsToMany
    {
        return $this->belongsToMany(Minute::class, 'minute_participants', 'person_id', 'minute_id')->withPivot('attended')->withTimestamps();
    }

    /** Card shape mirroring User::toCard(), plus the external marker. */
    public function toCard(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'initials' => $this->initials(), 'external' => true];
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name ?? '')) ?: [];

        return mb_substr($parts[0] ?? '?', 0, 1).mb_substr($parts[1] ?? '', 0, 1);
    }

    /** Same normalisation as search, so «ي»/«ی», ۰۹/09 and spacing variants find each other. */
    public static function normalize(string $name): string
    {
        $name = CalendarUtils::convertNumbers(trim($name), true);
        $name = strtr($name, ['ي' => 'ی', 'ك' => 'ک', 'ة' => 'ه', '‌' => ' ']);

        return mb_strtolower(preg_replace('/\s+/u', ' ', $name) ?? '');
    }

    /**
     * Resolve free-typed names into Person rows of this workspace, creating the missing ones.
     * Returns normalized name => person id (duplicates collapse into one row).
     *
     * @param  array<int, string>  $names
     * @return array<string, string>
     */
    public static function resolveMap(Workspace $workspace, array $names, ?int $creatorId = null): array
    {
        $clean = [];

        foreach ($names as $name) {
            $norm = static::normalize((string) $name);

            if ($norm !== '' && mb_strlen($norm) <= 120 && ! isset($clean[$norm])) {
                $clean[$norm] = trim((string) $name);
            }
        }

        if ($clean === []) {
            return [];
        }

        $map = $workspace->people()->get(['id', 'name'])
            ->mapWithKeys(fn (self $p) => [static::normalize($p->name) => $p->id])
            ->all();

        foreach ($clean as $norm => $name) {
            if (! isset($map[$norm])) {
                $person = new self(['name' => $name]);
                $person->workspace_id = $workspace->id;
                $person->created_by = $creatorId;
                $person->save();

                $map[$norm] = $person->id;
            }
        }

        return collect($clean)->mapWithKeys(fn ($name, $norm) => [$norm => $map[$norm]])->all();
    }

    /** @return array<int, string> unique person ids for the given names */
    public static function resolveMany(Workspace $workspace, array $names, ?int $creatorId = null): array
    {
        return array_values(array_unique(array_values(static::resolveMap($workspace, $names, $creatorId))));
    }
}
