<?php

namespace App\Http\Controllers\Corporate\Classroom;

use App\Http\Controllers\Controller;
use App\Http\Resources\Corporate\Classroom\ClassDiscussionPostResource;
use App\Http\Resources\Corporate\Classroom\ClassDiscussionReplyResource;
use App\Models\Corporate\ClassDiscussionPost;
use App\Models\Corporate\ClassDiscussionReply;
use App\Models\Corporate\Classroom;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class ClassDiscussionController extends Controller
{
    public function index(Classroom $class)
    {
        $this->authorizeOwner($class);

        $posts = $class->discussionPosts()
            ->with(['author', 'replies.author'])
            ->withCount('replies')
            ->get();

        return Response::apiSuccess('Class discussion', ClassDiscussionPostResource::collection($posts));
    }

    public function store(Request $request, Classroom $class)
    {
        $this->authorizeOwner($class);

        $data = $request->validate(['content' => 'required|string|max:5000']);

        $post = $class->discussionPosts()->create([
            'author_id' => Auth::user()->id,
            'author_type' => User::class,
            'content' => $data['content'],
        ]);

        return Response::apiSuccess('Posted successfully', new ClassDiscussionPostResource($post->load('author')));
    }

    public function storeReply(Request $request, Classroom $class, ClassDiscussionPost $post)
    {
        $this->authorizeOwner($class);
        $this->authorizePostBelongsToClass($class, $post);

        $data = $request->validate(['content' => 'required|string|max:5000']);

        $reply = $post->replies()->create([
            'author_id' => Auth::user()->id,
            'author_type' => User::class,
            'content' => $data['content'],
        ]);

        return Response::apiSuccess('Reply posted successfully', new ClassDiscussionReplyResource($reply->load('author')));
    }

    /**
     * Teachers can delete any post in their own class (moderation), not
     * only ones they authored themselves.
     */
    public function destroy(Classroom $class, ClassDiscussionPost $post)
    {
        $this->authorizeOwner($class);
        $this->authorizePostBelongsToClass($class, $post);

        $post->delete();

        return Response::apiSuccess('Post deleted successfully');
    }

    public function destroyReply(Classroom $class, ClassDiscussionPost $post, ClassDiscussionReply $reply)
    {
        $this->authorizeOwner($class);
        $this->authorizePostBelongsToClass($class, $post);
        $this->authorizeReplyBelongsToPost($post, $reply);

        $reply->delete();

        return Response::apiSuccess('Reply deleted successfully');
    }

    private function authorizeOwner(Classroom $class): void
    {
        if ($class->institute_id !== Auth::user()->id) {
            throw new AuthorizationException('You do not have access to this class.');
        }
    }

    private function authorizePostBelongsToClass(Classroom $class, ClassDiscussionPost $post): void
    {
        if ($post->class_id !== $class->id) {
            throw new AuthorizationException('This post does not belong to this class.');
        }
    }

    private function authorizeReplyBelongsToPost(ClassDiscussionPost $post, ClassDiscussionReply $reply): void
    {
        if ($reply->class_discussion_post_id !== $post->id) {
            throw new AuthorizationException('This reply does not belong to this post.');
        }
    }
}
