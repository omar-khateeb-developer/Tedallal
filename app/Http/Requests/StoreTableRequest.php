<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'status' => ['sometimes', 'in:active,inactive'],
            'pos_x' => ['sometimes', 'numeric'],
            'pos_y' => ['sometimes', 'numeric'],
            'shape' => ['sometimes', 'string', 'in:circle,rectangle,booth,vip'],
            'rotation' => ['sometimes', 'integer'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'zone' => ['sometimes', 'string', 'max:64'],
            'width' => ['sometimes', 'numeric', 'min:40', 'max:500'],
            'height' => ['sometimes', 'numeric', 'min:40', 'max:500'],
            'iot_device_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
