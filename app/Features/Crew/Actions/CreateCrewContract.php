<?php

namespace App\Features\Crew\Actions;

use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Services\SanitiseContractHtml;
use App\Models\User;

class CreateCrewContract
{
    public function __construct(private readonly SanitiseContractHtml $sanitiser) {}

    public function execute(array $data, User $createdBy): CrewContract
    {
        $data['content'] = $this->sanitiser->execute($data['content']);
        $data['document_checksum'] = hash('sha256', $data['content']);
        $contract = new CrewContract;
        $contract->fill($data);
        $contract->createdBy()->associate($createdBy);
        $contract->save();

        return $contract;
    }
}
