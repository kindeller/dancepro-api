<?php

namespace App\Features\Operations\Actions;

use App\Features\Operations\Models\OperationalResource;
use App\Features\Operations\Services\OperationalDocumentMetadata;
use App\Features\Operations\Services\OperationsFileStorage;
use Illuminate\Http\UploadedFile;

class SaveOperationalResource
{
    public function __construct(
        private readonly OperationsFileStorage $files,
        private readonly OperationalDocumentMetadata $metadata,
    ) {}

    public function execute(array $data, ?OperationalResource $resource = null): OperationalResource
    {
        $resource ??= new OperationalResource;
        $previousPath = $resource->file_path;
        $resource->fill(collect($data)->except('file')->all())->save();
        if (($data['file'] ?? null) instanceof UploadedFile) {
            $resource->file_path = $this->files->store($data['file'], "operations/resources/{$resource->uuid}");
            $resource->fill($this->metadata->forPath($resource->file_path));
            $resource->save();
            $this->files->deleteReplaced($previousPath, $resource->file_path);
        }

        return $resource->refresh();
    }
}
