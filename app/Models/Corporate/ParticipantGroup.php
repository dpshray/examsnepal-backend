<?php

namespace App\Models\Corporate;

use App\Models\Participant;
use App\Traits\SlugTrait;
use Illuminate\Database\Eloquent\Model;

class ParticipantGroup extends Model
{
    //
    use SlugTrait;
    protected $fillable = [
        'Corporate_id',
        'group_name',
        'slug',
        'description'
    ];

    public function slugSource()
    {
        return 'group_name';
    }
    public function participants()
    {
        return $this->belongsToMany(Participant::class, 'participant_in_groups', 'group_id', 'participant_id')->withTimestamps();
    }
}
