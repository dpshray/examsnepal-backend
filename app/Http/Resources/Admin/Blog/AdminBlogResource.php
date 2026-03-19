<?php

namespace App\Http\Resources\Admin\Blog;

use App\Models\Blog\Blog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBlogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'summary' => $this->summary,
            'status' => $this->status,
            'cta_text' => $this->cta_text,
            'cta_link' => $this->cta_link,
            'author' => $this->author,
            'published_date' => $this->published_date,
            'image' => $this->getFirstMediaUrl(Blog::BLOG_IMAGE),
            'categories' => $this->category->map(function ($category) {
                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'slug' => $category->slug,
                ];
            }),
            'tag' => $this->tag->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'title' => $tag->title,
                    'slug' => $tag->slug,
                ];
            }),
        ];
    }
}
