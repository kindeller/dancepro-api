<?php

namespace App\Features\Tracking\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['task_key', 'mode', 'offset_days', 'send_time'])]
class ConcertTrackerAutomationRule extends Model {}
