<?php

namespace App\Features\CompetitionContacts\Actions;

use App\Features\CompetitionContacts\Models\CompetitionContact;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SaveCompetitionContact
{
    public function execute(array $data, ?CompetitionContact $contact = null): CompetitionContact
    {
        return DB::transaction(function () use ($data, $contact): CompetitionContact {
            $contact ??= new CompetitionContact;
            $staff = $data['staff'];
            $primary = $staff[0];
            $primaryEmails = $this->emails($primary['emails']);

            $contact->fill([
                ...collect($data)->except(['logo', 'staff'])->all(),
                'organiser_name' => $primary['name'],
                'organiser_email' => $primaryEmails[0],
                'organiser_phone' => $primary['phone'] ?? '',
            ])->save();

            $contact->staff()->delete();
            foreach ($staff as $position => $person) {
                $contact->staff()->create([
                    'name' => $person['name'],
                    'role' => $person['role'] ?? null,
                    'emails' => $this->emails($person['emails']),
                    'phone' => $person['phone'] ?? null,
                    'position' => $position,
                ]);
            }

            if (($data['logo'] ?? null) instanceof UploadedFile) {
                $contact->logo_path = $data['logo']->storeAs(
                    "logos/competition-contacts/{$contact->uuid}",
                    'logo.'.$data['logo']->extension(),
                    config('contact-directory.logo_disk'),
                );
                $contact->save();
            }

            return $contact->load('staff');
        });
    }

    /** @return list<string> */
    private function emails(string $emails): array
    {
        return collect(explode(',', $emails))->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->filter()->unique()->values()->all();
    }
}
