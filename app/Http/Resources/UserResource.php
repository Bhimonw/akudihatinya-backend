<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\ProfilePictureService;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profilePictureService = app(ProfilePictureService::class);
        
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'profile_picture' => $this->profile_picture ? $profilePictureService->getProfilePictureUrl($this->profile_picture) : null,
            'role' => $this->role,
            'puskesmas' => $this->when($this->isPuskesmas(), function () {
                return new PuskesmasResource($this->puskesmas);
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}