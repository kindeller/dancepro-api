<?php

namespace App\Features\Chat\Models;

use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'participant_key'])]
class DirectChatConversation extends Model
{
    use HasPublicUuid;

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'direct_chat_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DirectChatMessage::class);
    }

    public static function participantKey(int $firstUserId, int $secondUserId): string
    {
        $ids = [$firstUserId, $secondUserId];
        sort($ids);

        return implode(':', $ids);
    }
}
