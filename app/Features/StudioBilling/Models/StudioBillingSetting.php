<?php

namespace App\Features\StudioBilling\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['business_name', 'abn', 'address', 'email', 'bank_name', 'bank_bsb', 'bank_account'])]
class StudioBillingSetting extends Model {}
