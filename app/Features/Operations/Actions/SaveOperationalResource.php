<?php

namespace App\Features\Operations\Actions;

use App\Features\Operations\Models\OperationalResource;
use Illuminate\Http\UploadedFile;

class SaveOperationalResource
{
    public function execute(array $data, ?OperationalResource $resource = null): OperationalResource
    {
        $resource ??= new OperationalResource;
        $resource->fill(collect($data)->except('file')->all())->save();
        if (($data['file'] ?? null) instanceof UploadedFile) {
            $resource->file_path = $data['file']->store("operations/resources/{$resource->uuid}", 'public');
            $resource->save();
        }

        return $resource->refresh();
    }
}
