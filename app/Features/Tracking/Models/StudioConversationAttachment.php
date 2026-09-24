<?php

namespace App\Features\Tracking\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'studio_conversation_message_id', 'disk', 'storage_key', 'original_name', 'mime_type', 'size_bytes'])]
class StudioConversationAttachment extends Model
{
    use HasPublicUuid;

    public function message(): BelongsTo
    {
        return $this->belongsTo(StudioConversationMessage::class, 'studio_conversation_message_id');
    }

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }
}
