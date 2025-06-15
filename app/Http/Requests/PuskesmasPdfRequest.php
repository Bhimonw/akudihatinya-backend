<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PuskesmasPdfRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $currentYear = date('Y');
        $maxYear = $currentYear + 1;

        return [
            'disease_type' => [
                'required',
                'string',
                Rule::in(['ht', 'dm'])
            ],
            'year' => [
                'nullable',
                'integer',
                'min:2020',
                "max:{$maxYear}"
            ],
            'puskesmas_id' => [
                'nullable',
                'integer',
                'exists:puskesmas,id'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'disease_type.required' => 'Jenis penyakit harus diisi.',
            'disease_type.in' => 'Jenis penyakit harus berupa "ht" (Hipertensi) atau "dm" (Diabetes Melitus).',
            'year.integer' => 'Tahun harus berupa angka.',
            'year.min' => 'Tahun minimal adalah 2020.',
            'year.max' => 'Tahun maksimal adalah ' . (date('Y') + 1) . '.',
            'puskesmas_id.integer' => 'ID Puskesmas harus berupa angka.',
            'puskesmas_id.exists' => 'Puskesmas yang dipilih tidak ditemukan dalam sistem.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'disease_type' => 'jenis penyakit',
            'year' => 'tahun',
            'puskesmas_id' => 'ID Puskesmas'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default year if not provided
        if (!$this->has('year') || $this->year === null) {
            $this->merge([
                'year' => date('Y')
            ]);
        }

        // For non-admin users, set puskesmas_id to their own puskesmas
        if (!Auth::user()->isAdmin()) {
            $this->merge([
                'puskesmas_id' => Auth::user()->puskesmas_id
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional validation for admin users
            if (Auth::user()->isAdmin() && !$this->puskesmas_id) {
                $validator->errors()->add(
                    'puskesmas_id',
                    'Admin harus memilih Puskesmas untuk generate PDF.'
                );
            }

            // Validate that non-admin users can only access their own puskesmas
            if (!Auth::user()->isAdmin() && $this->puskesmas_id !== Auth::user()->puskesmas_id) {
                $validator->errors()->add(
                    'puskesmas_id',
                    'Anda hanya dapat mengakses data Puskesmas Anda sendiri.'
                );
            }
        });
    }

    /**
     * Get the validated data with proper types
     */
    public function getValidatedData(): array
    {
        $validated = $this->validated();

        return [
            'disease_type' => $validated['disease_type'],
            'year' => (int) ($validated['year'] ?? date('Y')),
            'puskesmas_id' => (int) $validated['puskesmas_id']
        ];
    }
}
