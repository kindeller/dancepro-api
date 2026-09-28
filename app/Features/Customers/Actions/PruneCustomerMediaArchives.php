<?php

namespace App\Features\Customers\Actions;

use App\Features\Customers\Models\CustomerMediaArchive;
use Illuminate\Support\Facades\Storage;

class PruneCustomerMediaArchives
{
    public function execute(): int
    {
        $removed = 0;
        CustomerMediaArchive::query()->where('expires_at', '<=', now())->eachById(function (CustomerMediaArchive $archive) use (&$removed): void {
            Storage::disk($archive->storage_disk)->delete($archive->storage_key);
            $archive->delete();
            $removed++;
        });

        return $removed;
    }
}
