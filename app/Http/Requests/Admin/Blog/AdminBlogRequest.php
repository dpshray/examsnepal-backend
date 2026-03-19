<?php

namespace App\Http\Requests\Admin\Blog;

use Illuminate\Foundation\Http\FormRequest;

class AdminBlogRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title'         => 'required|string',
            'content'       => 'required|string',
            'summary'       => 'required|string',
            'author'        => 'required|string',
            'published_date' => 'required|date',
            'status' => 'required|string',
            'cta_text' => 'nullable|sometimes|string',
            'cta_link' => 'nullable|sometimes|string',
            'image'         => 'nullable|file|mimes:png,jpg',
            'category_id'   => 'required|array',
            'category_id.*' => 'exists:blog_categories,id',
            'tag_id'        => 'required|array',
            'tag_id.*'      => 'exists:blog_tags,id',
        ];
    }
}
