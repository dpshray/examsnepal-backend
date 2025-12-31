<?php

namespace App\Models\Corporate;

use App\Models\Participant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExamResultToken extends Model
{
    //
    protected $fillable = [
        'corporate_exam_id',
        'participant_id',
        'email',
        'result_token',
    ];

    /**
     * Generate or get existing token for a participant
     * This ensures ONE token per student per exam (all sections use same token)
     */
    public static function getOrCreateToken($exam_id, $participant_id = null, $email = null)
    {
        $attributes = ['corporate_exam_id' => $exam_id];

        if ($participant_id) {
            $attributes['participant_id'] = $participant_id;
            $attributes['email'] = null; // Make sure email is null for private exams
        } else {
            $attributes['email'] = $email;
            $attributes['participant_id'] = null; // Make sure participant_id is null for public exams
        }

        // firstOrCreate ensures we get SAME token if already exists
        $tokenRecord = self::firstOrCreate(
            $attributes,
            ['result_token' => self::generateUniqueToken()]
        );

        return $tokenRecord->result_token;
    }

    /**
     * Generate unique random token
     */
    private static function generateUniqueToken()
    {
        do {
            // Generate 32 character random string
            $token = Str::random(32);
        } while (self::where('result_token', $token)->exists());

        return $token;
    }

    /**
     * Get participant info from token
     */
    public static function getParticipantFromToken($exam_id, $result_token)
    {
        $tokenRecord = self::where('corporate_exam_id', $exam_id)
            ->where('result_token', $result_token)
            ->first();

        if (!$tokenRecord) {
            return null;
        }

        return [
            'participant_id' => $tokenRecord->participant_id,
            'email' => $tokenRecord->email,
            'is_public' => $tokenRecord->participant_id === null,
        ];
    }

    // Relationships
    public function exam()
    {
        return $this->belongsTo(CorporateExam::class, 'corporate_exam_id');
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}
