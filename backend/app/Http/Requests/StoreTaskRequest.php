<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy check happens in the controller
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'assigned_to' => $this->assigneeRules(),
        ];
    }

    /** Admins may assign anyone; regular users may only assign themselves. */
    protected function assigneeRules(): array
    {
        $rules = ['nullable', 'integer', Rule::exists('users', 'id')];

        if (! $this->user()->isAdmin()) {
            $rules[] = Rule::in([$this->user()->id]);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'assigned_to.in' => 'You can only assign tasks to yourself.',
        ];
    }
}
