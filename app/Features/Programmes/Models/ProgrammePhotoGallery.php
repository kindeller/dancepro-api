<?php

namespace App\Features\Programmes\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class ProgrammePhotoGallery extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];
}
