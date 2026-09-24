<?php

namespace App\Features\Programmes\Models;

use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammeRevision extends Model
{
    use HasPublicUuid;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'version' => 'integer', 'confirmed_at' => 'datetime'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ProgrammeSource::class, 'programme_source_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id')->withTrashed();
    }
}
