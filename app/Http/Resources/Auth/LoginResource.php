<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $token=$this->createToken($this->email)->plainTextToken;

        return [
            'id'=>$this->id,
            'name'=>$this->name,
            'email'=>$this->email,
             'type' => 'Bearer',
            'token' => $token,
        ];
    }
}
