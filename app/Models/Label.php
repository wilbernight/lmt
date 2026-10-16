<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    use BelongsToWorkspace, HasUlids;

    public const SCOPES = ['task', 'letter', 'note', 'customer'];

    protected $fillable = ['name', 'color', 'scope'];

    protected $hidden = ['pivot', 'workspace_id', 'created_at', 'updated_at'];
}
