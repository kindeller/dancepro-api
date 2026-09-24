<?php

namespace App\Features\Tracking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['unassigned_alert_user_id', 'escalation_after_days', 'editing_target_days', 'approval_target_days', 'email_business_name', 'email_logo_url', 'email_phone', 'email_address', 'email_website', 'email_reply_address', 'email_legal_footer'])]
class ConcertTrackerSetting extends Model
{
    public function unassignedAlertUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unassigned_alert_user_id');
    }
}
