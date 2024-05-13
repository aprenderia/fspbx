<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use libphonenumber\NumberParseException;
use Propaganistas\LaravelPhone\PhoneNumber;

class UpdatePhoneNumberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'destination_accountcode' => [
                'nullable',
                'string',
            ],
            'destination_actions' => [
                'nullable',
                'array',
            ],
            'destination_actions.*.selectedCategory' => [
                Rule::in(['', 'extensions', 'ringgroup', 'ivrs', 'voicemails', 'others'])
            ],
            'destination_actions.*.value.value' => [
                'required_if:destination_actions.*.selectedCategory,!=,""',
                'string'
            ],
            'destination_conditions' => [
                'nullable',
                'array',
            ],
            'destination_conditions.condition_app' => [
                'nullable',
                Rule::in(['transfer'])
            ],
            'destination_conditions.condition_field.value' => [
                'nullable',
                'required_if:destination_conditions.condition_app,==,"transfer"',
            ],
            'destination_conditions.condition_expression' => [
                'required_if:destination_conditions.condition_app,==,"transfer"',
                'phone:US'
            ],
            'destination_conditions.condition_data.*.value.value' => [
                'required_if:destination_conditions.condition_app,==,"transfer"',
                'string'
            ],
            'destination_cid_name_prefix' => [
                'nullable',
                'string',
            ],
            'destination_description' => [
                'nullable',
                'string',
            ],
            'destination_distinctive_ring' => [
                'nullable',
                'string',
            ],
            'destination_enabled' => [
                Rule::in([true, false]),
            ],
            'destination_record' => [
                Rule::in([true, false]),
            ],
            'destination_caller_id_name' => [
                'nullable',
                'string',
            ],
            'domain_uuid' => [
                'required',
                Rule::notIn(['NULL']), // Ensures 'domain_uuid' is not 'NULL'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'domain_uuid.not_in' => 'Company must be selected.'
        ];
    }

    public function prepareForValidation(): void
    {
        if (!$this->has('domain_uuid')) {
            $this->merge(['domain_uuid' => session('domain_uuid')]);
        }
    }
}
