<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'is_google_user' => $this->isGoogleUser(),
            'email_verified_at' => $this->when(
                $this->email_verified_at,
                fn () => $this->email_verified_at->format('Y-m-d H:i:s')
            ),
            'created_at' => $this->when(
                $this->created_at,
                fn () => $this->created_at->format('Y-m-d H:i:s')
            ),
        ];
    }
}
