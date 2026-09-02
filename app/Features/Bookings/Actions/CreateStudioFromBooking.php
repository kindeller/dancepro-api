<?php

namespace App\Features\Bookings\Actions;

use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Studios\Actions\SaveStudio;
use App\Features\Studios\Models\Studio;
use Illuminate\Validation\ValidationException;

class CreateStudioFromBooking
{
    public function __construct(private readonly SaveStudio $saveStudio) {}

    public function execute(ConcertBooking $booking, array $data): Studio
    {
        $existingStudio = Studio::query()
            ->whereRaw('lower(trim(name)) = ?', [mb_strtolower(trim($data['name']))])
            ->first();

        if ($existingStudio) {
            throw ValidationException::withMessages([
                'name' => "{$existingStudio->name} is already in the studio directory. Select that studio for comparison instead.",
            ]);
        }

        return $this->saveStudio->execute($data);
    }
}
