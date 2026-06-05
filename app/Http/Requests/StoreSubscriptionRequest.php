<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'municipality_slug' => ['required', 'string', Rule::exists('municipalities', 'slug')],
            'level' => ['required', Rule::in(['standard', 'simple'])],
        ];
    }
}
