<?php

namespace App\Http\Requests;

use App\Constants\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class MarkCompletedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ValidationRules::STATUS_UPDATE,
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status is required.',
            'status.in' => 'Invalid status type.',
        ];
    }
}
