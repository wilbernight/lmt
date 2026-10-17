<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskCategory extends Model
{
    use BelongsToWorkspace, HasUlids;

    protected $fillable = ['name', 'color'];

    protected function casts(): array
    {
        return ['name' => 'encrypted'];
    }

    protected $hidden = ['workspace_id', 'created_at', 'updated_at'];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'category_id');
    }
}
