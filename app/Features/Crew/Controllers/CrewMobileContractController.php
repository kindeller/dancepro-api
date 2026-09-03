<?php

namespace App\Features\Crew\Controllers;

use App\Features\Crew\Actions\RefreshCrewOnboardingStatus;
use App\Features\Crew\Actions\SignCrewContract;
use App\Features\Crew\Models\CrewContract;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Requests\SignCrewMobileContractRequest;
use App\Features\Crew\Support\CrewContractSignatureStatus;
use App\Features\Crew\Support\CrewContractStatus;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobileContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profile = $request->user()->crewProfile;
        $contracts = CrewContract::query()->where('status', CrewContractStatus::Active)
            ->with(['signatures' => fn ($query) => $query->where('crew_profile_id', $profile->id)])
            ->orderByDesc('effective_from')->get();

        return ApiResponse::success('Contracts returned.', $contracts->map(fn (CrewContract $contract): array => $this->resource($contract, $profile)));
    }

    public function sign(SignCrewMobileContractRequest $request, CrewContract $contract, SignCrewContract $sign, RefreshCrewOnboardingStatus $refresh): JsonResponse
    {
        abort_unless($contract->status === CrewContractStatus::Active, 404);
        $profile = $request->user()->crewProfile;
        $signature = $profile->contractSignatures()
            ->where('crew_contract_id', $contract->id)
            ->where('status', CrewContractSignatureStatus::Signed)
            ->first();
        $signature ??= $sign->execute($profile, $contract, $request->string('signed_name')->toString(), $request->ip(), $request->userAgent());
        $refresh->execute($profile->refresh());

        return ApiResponse::success('Contract signed.', [
            'id' => $contract->uuid,
            'signed_at' => $signature->signed_at->toIso8601String(),
        ]);
    }

    private function resource(CrewContract $contract, CrewProfile $profile): array
    {
        $signature = $contract->signatures->firstWhere('crew_profile_id', $profile->id);

        return [
            'id' => $contract->uuid,
            'name' => $contract->name,
            'version' => $contract->version,
            'status' => $contract->status->value,
            'content' => $contract->content,
            'signed_at' => $signature?->signed_at?->toIso8601String(),
        ];
    }
}
