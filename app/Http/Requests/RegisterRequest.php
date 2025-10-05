<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\ValidationRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => ValidationRules::NAME,
            'email' => ValidationRules::EMAIL,
            'password' => ValidationRules::PASSWORD,
            'role_id' => ValidationRules::ROLE,
            'specialty_id' => ValidationRules::SPECIALITY
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors()
        ], 422));
    }
}
