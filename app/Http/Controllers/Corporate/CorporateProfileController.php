<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\Profile\UpdateCorporateProfileRequest;
use App\Http\Resources\Institute\InstitutePublicProfileResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CorporateProfileController extends Controller
{
    public function show()
    {
        $institute = Auth::user();

        return Response::apiSuccess('Institute profile', new InstitutePublicProfileResource($institute));
    }

    public function update(UpdateCorporateProfileRequest $request)
    {
        $institute = Auth::user();
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $this->deleteStoredFile($institute->image);
            $data['image'] = $request->file('logo')->store('institutes/logos', 'public');
        }
        unset($data['logo']);

        if ($request->hasFile('banner')) {
            $this->deleteStoredFile($institute->banner_image);
            $data['banner_image'] = $request->file('banner')->store('institutes/banners', 'public');
        }
        unset($data['banner']);

        $institute->update($data);

        return Response::apiSuccess('Profile updated successfully', new InstitutePublicProfileResource($institute));
    }

    private function deleteStoredFile(?string $path): void
    {
        if ($path && ! Str::startsWith($path, ['http://', 'https://']) && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
