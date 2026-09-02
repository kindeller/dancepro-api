<?php

namespace App\Features\CompetitionContacts\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['competition_contact_id', 'name', 'role', 'emails', 'phone', 'position'])]
class CompetitionContactStaff extends Model
{
    protected $table = 'competition_contact_staff';

    public function competitionContact(): BelongsTo
    {
        return $this->belongsTo(CompetitionContact::class);
    }

    /** @return list<string> */
    public function emailAddresses(): array
    {
        return $this->emails ?? [];
    }

    public function emailString(): string
    {
        return implode(', ', $this->emailAddresses());
    }

    protected function casts(): array
    {
        return ['emails' => 'array'];
    }
}
