<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin && $this->user()?->is_active;
    }

    public function rules(): array
    {
        $fileRules = match ($this->input('type')) {
            'book' => ['nullable', 'file', 'mimes:pdf', 'max:51200'],
            'music' => ['nullable', 'file', 'mimes:mp3', 'max:102400'],
            default => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:20480', 'dimensions:max_width=10000,max_height=10000'],
        };

        return [
            'title' => 'required|string|max:160',
            'creator' => 'required|string|max:120',
            'type' => ['required', Rule::in(['book', 'music', 'painting'])],
            'description' => 'required|string|max:10000',
            'price' => 'required|decimal:0,2|numeric|min:1|max:1000000',
            'stock' => [Rule::excludeIf($this->input('type') !== 'painting'), 'required', 'integer', 'min:0', 'max:10000'],
            'category_id' => 'nullable|exists:categories,id',
            'details' => 'nullable|string|max:200',
            'featured' => 'nullable|boolean',
            'accent' => 'required|in:violet,rose,teal,amber',
            'status' => 'required|in:draft,published,archived',
            'content' => $fileRules,
        ];
    }
}
