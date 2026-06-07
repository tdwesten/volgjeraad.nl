<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMunicipalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'lowercase', 'alpha_dash', Rule::unique('municipalities', 'slug')],
            // Harde indexnaam-validatie: voorkomt path/index-injectie (geen slashes,
            // wildcards, komma's of _all) in de Elasticsearch-padsegmenten.
            'ori_index' => ['required', 'string', 'max:255', 'regex:/^ori_[a-z0-9._-]+$/'],
            'timezone' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            // YouTube channel-id heeft de vorm UC + 22 tekens.
            'youtube_channel_id' => ['nullable', 'string', 'regex:/^UC[A-Za-z0-9_-]{22}$/'],
        ];
    }
}
