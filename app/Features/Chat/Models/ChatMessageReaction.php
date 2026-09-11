<?php

namespace App\Features\Chat\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event_message_id', 'direct_chat_message_id', 'user_id', 'reaction'])]
class ChatMessageReaction extends Model {}
