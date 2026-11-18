<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Document;
use App\Services\DocumentFlow;
use App\Support\Html;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    /**
     * ?tab=active|closed|all|inbox|completed
     * inbox = documents where it's the current user's turn to comment / sign.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = $this->visibleQuery($request)->with([
            'documentType:id,value,name,name_kh',
            'creator:id,name,name_kh',
            'attachments',
            'currentComment.user:id,name,name_kh',
        ]);

        switch ($request->query('tab', 'active')) {
            case 'active':
                $query->where('is_closed', false);
                break;
            case 'closed':
                abort_unless($user->can('document_complete_access'), 403);
                $query->where('is_closed', true);
                break;
            case 'completed':
                $query->where('is_complete', true);
                break;
            case 'inbox':
                $query->where('is_closed', false)->whereHas('comments', fn ($c) => $c->where('user_id', $user->id)->where('status', 'current'));
                break;
        }

        return TableQuery::respond(
            $query,
            $request,
            ['letter_no', 'incoming_code', 'document_code', 'from_source', 'description'],
            ['id', 'letter_no', 'incoming_code', 'document_code', 'from_source', 'dateline', 'is_closed', 'is_complete', 'created_at'],
        );
    }

    public function show(Request $request, Document $document)
    {
        $user = $request->user();
        abort_unless($user->can('document_show') && $document->visibleTo($user), 403, 'You are not part of this document flow.');

        $document->load([
            'documentType', 'category', 'organisation',
            'creator:id,name,name_kh',
            'attachments',
            'comments.user' => fn ($q) => $q->select('id', 'name', 'name_kh', 'title_id', 'position_id', 'signature')
                ->with('title:id,name,name_kh,abbr,abbr_kh', 'position:id,name,name_kh'),
            'histories.user:id,name,name_kh',
        ]);

        $current = $document->comments->firstWhere('status', 'current');
        $isMyTurn = $current && $current->user_id === $user->id && ! $document->is_complete && $user->can('comment_create');

        return [
            'document' => $document,
            'permissions' => [
                'is_my_turn' => $isMyTurn,
                'is_final_step' => $isMyTurn && $current->is_final,
                'can_edit' => $user->can('document_edit'),
                'can_close' => $user->can('document_complete_edit'),
                'can_print' => $user->can('document_print_access'),
                'can_manage_flow' => $user->can('comment_grand_edit'),
                'can_remove_step' => $user->can('comment_delete'),
                'can_status_view' => $user->can('comment_status_view'),
                'can_upload' => $user->can('document_edit') || $isMyTurn,
                // a reviewer can send the document back to anyone who already commented
                'return_targets' => $isMyTurn
                    ? $document->comments->where('status', 'done')->values()->map(fn ($c) => [
                        'id' => $c->id, 'name' => $c->user?->name, 'name_kh' => $c->user?->name_kh,
                    ])
                    : [],
                'edit_hour' => (int) AppSetting::get('edit_hour', 24),
            ],
        ];
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $document = Document::create(array_merge(
            collect($data)->except([
                'reviewers', 'final_approver_id', 'attachments',
                'date_complete', 'is_complete', 'is_closed',
            ])->all(),
            [
                'created_by' => $request->user()->id,
                'status' => 'in_review',
                'incoming_code' => $data['incoming_code'] ?? $this->nextIncomingCode(),
            ]
        ));

        AttachmentController::storeFiles($request, $document);
        $document->log('created');
        DocumentFlow::build($document, $data['reviewers'] ?? [], (int) $data['final_approver_id']);

        return response()->json($document->fresh(), 201);
    }

    public function update(Request $request, Document $document)
    {
        abort_unless($document->visibleTo($request->user()), 403, 'You are not part of this document flow.');

        $data = $this->validated($request, $document);

        $document->update(collect($data)->except([
            'reviewers', 'final_approver_id', 'attachments',
            'date_complete', 'is_complete', 'is_closed',
        ])->all());
        AttachmentController::storeFiles($request, $document);

        // The flow can be changed until the Boss has completed the document.
        if (! $document->is_complete && isset($data['final_approver_id'])) {
            DocumentFlow::build($document, $data['reviewers'] ?? [], (int) $data['final_approver_id']);
        }
        $document->log('updated');

        return $document->fresh();
    }

    public function destroy(Request $request, Document $document)
    {
        abort_unless($request->user()->can('document_delete') || $request->user()->can('document_delete_all'), 403,
            'You do not have permission to delete this document.');
        abort_unless($document->visibleTo($request->user()), 403, 'You are not part of this document flow.');

        $document->delete();

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        abort_unless($request->user()->can('document_delete_all'), 403,
            'You do not have permission to bulk delete documents.');

        $ids = $request->validate(['ids' => 'required|array'])['ids'];
        Document::whereIn('id', $ids)->get()->each->delete();

        return response()->noContent();
    }

    /** បិទឯកសារ / reopen */
    public function close(Request $request, Document $document)
    {
        abort_unless($request->user()->can('document_complete_edit'), 403,
            'You do not have permission to close or reopen this document.');
        abort_unless($document->visibleTo($request->user()), 403, 'You are not part of this document flow.');

        $closed = $request->boolean('closed', true);
        $document->update(['is_closed' => $closed]);
        $document->log($closed ? 'closed' : 'reopened');

        return $document;
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $base = fn () => $this->visibleQuery($request);
        $warningHours = (int) AppSetting::get('dateline_warning', 24);

        return [
            'total' => $base()->count(),
            'active' => $base()->where('is_closed', false)->count(),
            'closed' => $base()->where('is_closed', true)->count(),
            'completed' => $base()->where('is_complete', true)->count(),
            'in_review' => $base()->where('is_complete', false)->where('is_closed', false)->count(),
            'my_turn' => $base()->where('is_closed', false)
                ->whereHas('comments', fn ($c) => $c->where('user_id', $user->id)->where('status', 'current'))->count(),
            'overdue' => $base()->where('is_complete', false)->where('is_closed', false)
                ->whereNotNull('dateline')->where('dateline', '<', now())->count(),
            'due_soon' => $base()->where('is_complete', false)->where('is_closed', false)
                ->whereBetween('dateline', [now(), now()->addHours($warningHours)])
                ->orderBy('dateline')->limit(10)
                ->get(['id', 'document_code', 'incoming_code', 'from_source', 'dateline']),
            'my_turn_list' => $base()->where('is_closed', false)
                ->whereHas('comments', fn ($c) => $c->where('user_id', $user->id)->where('status', 'current'))
                ->latest()->limit(10)
                ->get(['id', 'document_code', 'incoming_code', 'from_source', 'dateline', 'created_at']),
            'recent' => $base()->with('currentComment.user:id,name,name_kh')->latest()->limit(8)
                ->get(['id', 'document_code', 'incoming_code', 'from_source', 'status', 'is_complete', 'is_closed', 'created_at']),
        ];
    }

    protected function visibleQuery(Request $request): Builder
    {
        $user = $request->user();
        $query = Document::query();

        if (! $user->seesAllDocuments()) {
            $query->where(fn (Builder $w) => $w->where('created_by', $user->id)
                ->orWhereHas('comments', fn ($c) => $c->where('user_id', $user->id)));
        }

        return $query;
    }

    protected function validated(Request $request, ?Document $document = null): array
    {
        $data = $request->validate([
            'letter_no' => 'nullable|string|max:255',
            'incoming_code' => 'nullable|string|max:255',
            'document_code' => 'nullable|string|max:255',
            'from_source' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'document_type_id' => 'required|exists:document_types,id',
            'category_id' => 'nullable|exists:categories,id',
            'organisation_id' => 'nullable|exists:organisations,id',
            'dateline' => 'nullable|date',
            'reviewers' => 'nullable|array',
            'reviewers.*' => 'integer|exists:users,id',
            'final_approver_id' => [$document ? 'nullable' : 'required', 'integer', 'exists:users,id'],
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:51200',
        ]);

        if (! empty($data['final_approver_id'])) {
            $approver = \App\Models\User::find($data['final_approver_id']);
            abort_unless($approver && $approver->hasPermission('document_approve_access'), 422,
                'The selected Boss does not have the "document_approve_access" permission.');
        }

        if (array_key_exists('description', $data)) {
            $data['description'] = Html::clean($data['description']);
        }

        return $data;
    }

    /** e.g. 2388កម/2026 – running number per year. */
    protected function nextIncomingCode(): string
    {
        $year = now()->year;
        $count = Document::withTrashed()->whereYear('created_at', $year)->count() + 1;

        return $count.'កម/'.$year;
    }
}
