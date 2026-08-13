<?php

namespace App\Http\Resources\Institute;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstitutePublicProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'username' => $this->username,
            'fullname' => $this->fullname,
            'org' => $this->org,
            'about' => $this->about,
            'logo' => $this->logoUrl(),
            'banner_image' => $this->bannerUrl(),
            'location' => $this->location,
            'email' => $this->email,
            'phone' => $this->phone,
            'facebook' => $this->facebook,
            'twitter' => $this->twitter,
            'linkedin' => $this->linkedin,
        ];
    }
}
