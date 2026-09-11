<?php

namespace App\Http\Controllers\Institute\Classroom;

use App\Http\Controllers\Controller;
use App\Http\Resources\Corporate\Classroom\ClassDiscussionPostResource;
use App\Http\Resources\Corporate\Classroom\ClassDiscussionReplyResource;
use App\Models\Corporate\ClassDiscussionPost;
use App\Models\Corporate\ClassDiscussionReply;
use App\Models\Corporate\Classroom;
use App\Models\InstituteStudent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class StudentClassDiscussionController extends Controller
{
    public function index(string $slug)
    {
        $class = $this->resolveEnrolledClass($slug);

        $posts = $class->discussionPosts()
            ->with(['author', 'replies.author'])
            ->withCount('replies')
            ->get();

        return Response::apiSuccess('Class discussion', ClassDiscussionPostResource::collection($posts));
    }

    public function store(Request $request, string $slug)
    {
        $student = Auth::guard('institute_student')->user();
        $class = $this->resolveEnrolledClass($slug);

        $data = $request->validate(['content' => 'required|string|max:5000']);

        $post = $class->discussionPosts()->create([
            'author_id' => $student->id,
            'author_type' => InstituteStudent::class,
            'content' => $data['content'],
        ]);

        return Response::apiSuccess('Posted successfully', new ClassDiscussionPostResource($post->load('author')));
    }

    public function storeReply(Request $request, string $slug, ClassDiscussionPost $post)
    {
        $student = Auth::guard('institute_student')->user();
        $class = $this->resolveEnrolledClass($slug);
        $this->authorizePostBelongsToClass($class, $post);

        $data = $request->validate(['content' => 'required|string|max:5000']);

        $reply = $post->replies()->create([
            'author_id' => $student->id,
            'author_type' => InstituteStudent::class,
            'content' => $data['content'],
        ]);

        return Response::apiSuccess('Reply posted successfully', new ClassDiscussionReplyResource($reply->load('author')));
    }

    /**
     * Students may only delete their own posts - unlike the teacher
     * controller, this is not a moderation capability.
     */
    public function destroy(string $slug, ClassDiscussionPost $post)
    {
        $student = Auth::guard('institute_student')->user();
        $class = $this->resolveEnrolledClass($slug);
        $this->authorizePostBelongsToClass($class, $post);
        $this->authorizeOwnContent($student, $post->author_id, $post->author_type);

        $post->delete();

        return Response::apiSuccess('Post deleted successfully');
    }

    public function destroyReply(string $slug, ClassDiscussionPost $post, ClassDiscussionReply $reply)
    {
        $student = Auth::guard('institute_student')->user();
        $class = $this->resolveEnrolledClass($slug);
        $this->authorizePostBelongsToClass($class, $post);
        if ($reply->class_discussion_post_id !== $post->id) {
            throw new AuthorizationException('This reply does not belong to this post.');
        }
        $this->authorizeOwnContent($student, $reply->author_id, $reply->author_type);

        $reply->delete();

        return Response::apiSuccess('Reply deleted successfully');
    }

    private function authorizePostBelongsToClass(Classroom $class, ClassDiscussionPost $post): void
    {
        if ($post->class_id !== $class->id) {
            throw new AuthorizationException('This post does not belong to this class.');
        }
    }

    private function authorizeOwnContent(InstituteStudent $student, int $authorId, string $authorType): void
    {
        if ($authorType !== InstituteStudent::class || $authorId !== $student->id) {
            throw new AuthorizationException('You can only delete your own posts.');
        }
    }

    private function resolveEnrolledClass(string $slug): Classroom
    {
        $student = Auth::guard('institute_student')->user();

        $class = Classroom::where('slug', $slug)
            ->where('institute_id', $student->institute_id)
            ->firstOrFail();

        $pivot = $class->students()->where('institute_student_id', $student->id)->first();
        abort_unless($pivot?->pivot?->status === 'enrolled', 403, 'You must be enrolled in this class to view this.');

        return $class;
    }
}
