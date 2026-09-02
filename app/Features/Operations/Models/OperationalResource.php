<?php

namespace App\Features\Operations\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['uuid', 'section_number', 'title', 'resource_type', 'event_type', 'event_type_definition_id', 'role_code', 'summary', 'content', 'file_path', 'external_url', 'sort_order', 'is_active'])]
class OperationalResource extends Model
{
    use HasPublicUuid;

    public function fileUrl(): ?string
    {
        return $this->file_path ? route('internal-documents.resources.show', $this) : null;
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
