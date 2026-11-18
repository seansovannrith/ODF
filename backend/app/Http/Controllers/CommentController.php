<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Comment;
use App\Models\Document;
use App\Services\DocumentFlow;
use App\Support\Html;
use App\Support\TableQuery;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /** Comment List. comment_grand_access sees all, others see their own. */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Comment::with([
            'document:id,document_code,incoming_code,from_source',
            'user:id,name,name_kh',
        ])->whereHas('document');

        if (! $user->can('comment_grand_access')) {
            $query->where('user_id', $user->id);
        }

        return TableQuery::respond(
            $query,
            $request,
            ['comment', 'user.name', 'document.document_code'],
            ['id', 'step_order', 'status', 'is_submit', 'is_esign', 'submitted_at'],
            exact: ['user_id', 'status', 'is_submit', 'is_esign', 'document_id'],
        );
    }

    public function show(Request $request, Comment $comment)
    {
        abort_unless($comment->document && $comment->document->visibleTo($request->user()), 403);

        return $comment->load('document:id,document_code,incoming_code,from_source', 'user:id,name,name_kh');
    }

    /** "Add Comment": insert a person (and optionally their comment) into a document's flow. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'document_id' => 'required|exists:documents,id',
            'user_id' => 'required|exists:users,id',
            'comment' => 'nullable|string',
            'is_submit' => 'boolean',
            'is_esign' => 'boolean',
        ]);

        $document = Document::findOrFail($data['document_id']);
        $comment = DocumentFlow::addStep(
            $document, (int) $data['user_id'], Html::clean($data['comment'] ?? null),
            (bool) ($data['is_submit'] ?? false), (bool) ($data['is_esign'] ?? false)
        );

        return response()->json($comment->load('document:id,document_code', 'user:id,name'), 201);
    }

    /** The current person submits their comment. The Boss may also e-sign and close. */
    public function submit(Request $request, Document $document)
    {
        $data = $request->validate([
            'comment' => 'nullable|string',
            'esign' => 'boolean',
            'close' => 'boolean',
        ]);

        $current = $document->comments()->where('status', 'current')->first();
        abort_unless($request->user()->can('comment_create'), 403);
        abort_unless($current && $current->user_id === $request->user()->id && ! $document->is_complete, 403, 'It is not your turn on this document.');
        abort_if(! $current->is_final && blank(strip_tags($data['comment'] ?? '')), 422, 'Please write a comment.');

        if (($data['esign'] ?? false) && ! $request->user()->signature) {
            abort(422, 'Please upload your signature in your profile before signing.');
        }

        DocumentFlow::submit($document, $current, Html::clean($data['comment'] ?? null), $data['esign'] ?? false, $data['close'] ?? false);

        return response()->json(['message' => 'Submitted']);
    }

    /** Send the document back to someone who already commented. */
    public function returnTo(Request $request, Document $document)
    {
        $data = $request->validate([
            'comment_id' => 'required|integer',
            'note' => 'nullable|string|max:1000',
        ]);

        $current = $document->comments()->where('status', 'current')->first();
        abort_unless($current && $current->user_id === $request->user()->id, 403, 'It is not your turn on this document.');

        $target = $document->comments()->where('id', $data['comment_id'])->where('status', 'done')->firstOrFail();
        DocumentFlow::moveTo($document, $target, 'returned', $data['note'] ?? null);

        return response()->json(['message' => 'Returned']);
    }

    /**
     * Edit a comment.
     *  - comment_edit: your own submitted comment, within "edit_hour" hours
     *  - comment_grand_edit: any comment, any time, plus the Submit / Is Esign flags
     */
    public function update(Request $request, Comment $comment)
    {
        $user = $request->user();
        $hours = (int) AppSetting::get('edit_hour', 24);
        $grand = $user->can('comment_grand_edit');
        $owns = $user->can('comment_edit') && $comment->user_id === $user->id && $comment->status === 'done'
            && $comment->submitted_at && $comment->submitted_at->gt(now()->subHours($hours));

        abort_unless($grand || $owns, 403, "Comments can only be edited within {$hours} hours.");

        $data = $request->validate(['comment' => 'nullable|string', 'is_submit' => 'boolean', 'is_esign' => 'boolean']);
        $changes = ['comment' => Html::clean($data['comment'] ?? null)];
        if ($grand) {
            $changes += array_intersect_key($data, array_flip(['is_submit', 'is_esign']));
        }
        $comment->update($changes);
        $comment->document->log('comment_edited', $comment->user?->name);

        return $comment;
    }

    /** Undo icon: re-open this step. */
    public function reset(Request $request, Comment $comment)
    {
        abort_unless($request->user()->can('comment_grand_edit'), 403);
        DocumentFlow::moveTo($comment->document, $comment, 'reset');

        return response()->json(['message' => 'Step re-opened']);
    }

    /** X icon / Delete: remove this person from the flow. */
    public function destroy(Request $request, Comment $comment)
    {
        abort_unless($request->user()->can('comment_delete'), 403);
        DocumentFlow::remove($comment->document, $comment);

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        abort_unless($request->user()->can('comment_delete'), 403);
        $ids = $request->validate(['ids' => 'required|array'])['ids'];
        Comment::with('document')->whereIn('id', $ids)->where('is_final', false)->get()
            ->each(fn (Comment $c) => $c->document && DocumentFlow::remove($c->document, $c));

        return response()->noContent();
    }
}
