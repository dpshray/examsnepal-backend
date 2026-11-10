<?php

namespace App\Traits;

use App\Models\Corporate\CorporateExam;
use App\Models\Corporate\ParticipantGroup;
use Illuminate\Support\Facades\Auth;

/**
 * Institute (corporate) endpoints bind records straight from the URL, so every
 * handler must confirm the record belongs to the logged-in institute -
 * institute accounts are self-registered.
 */
trait EnsuresCorporateOwnership
{
    protected function ensureOwnsCorporateExam(?CorporateExam $exam): void
    {
        abort_unless($exam && (int) $exam->corporate_id === (int) Auth::guard('users')->id(), 403, 'You do not have permission to do this.');
    }

    protected function ensureOwnsParticipantGroup(ParticipantGroup $group): void
    {
        // The column really is "Corporate_id" (capital C) on participant_groups.
        abort_unless((int) $group->Corporate_id === (int) Auth::guard('users')->id(), 403, 'Unauthorized access to this group');
    }
}
