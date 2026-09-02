<?php

namespace App\Features\Chat\Models;

use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'direct_chat_conversation_id', 'author_user_id', 'body'])]
class DirectChatMessage extends Model
{
    use HasPublicUuid;

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DirectChatConversation::class, 'direct_chat_conversation_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
