<?php

namespace App\Features\Concerts\Actions;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertAccess;
use App\Features\Concerts\Support\ConcertAccessMethod;
use Illuminate\Http\Request;

class UnlockConcert
{
    public function execute(Concert $concert, Request $request, string $studentName, string $password): bool
    {
        $successful = $concert->passwordMatches($password);

        ConcertAccess::create([
            'concert_id' => $concert->id,
            'user_id' => $request->user()?->id,
            'access_method' => ConcertAccessMethod::Password,
            'accessed_at' => now(),
            'session_identifier' => $request->session()->getId(),
            'student_name' => $studentName,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->headers->get('referer'),
            'was_successful' => $successful,
            'failure_reason' => $successful ? null : 'invalid_password',
        ]);

        if ($successful) {
            $request->session()->put("concert_access.{$concert->uuid}", true);
        }

        return $successful;
    }
}
