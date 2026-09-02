<?php

namespace App\Features\Crew\Actions;

use App\Features\Crew\Models\RecognitionType;

class SaveRecognitionType
{
    public function execute(array $data, ?RecognitionType $type = null): RecognitionType
    {
        $type ??= new RecognitionType;
        $type->fill($data)->save();

        return $type;
    }
}
