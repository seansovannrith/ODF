<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Document;
use App\Models\UserAlert;
use Illuminate\Support\Facades\DB;

/**
 * The document flow:
 *   reviewer 1 -> reviewer 2 -> ... -> Boss (final, completes & signs)
 *
 * Every person in the flow is one row in `comments`, ordered by step_order.
 * Exactly one row is "current" (whose turn it is) until the Boss completes.
 */
class DocumentFlow
{
    /**
     * Create or rebuild the flow. Steps already "done" are kept; everything after them
     * is replaced by the new reviewer list followed by the final approver (Boss).
     */
    public static function build(Document $doc, array $reviewerIds, int $finalApproverId): void
    {
        DB::transaction(function () use ($doc, $reviewerIds, $finalApproverId) {
            $done = $doc->comments()->where('status', 'done')->get();
            $doneUserIds = $done->pluck('user_id')->map(fn ($id) => (int) $id)->all();
            $previous = $doc->comments()->where('status', 'current')->first();

            $doc->comments()->where('status', '!=', 'done')->delete();

            $step = (int) $done->max('step_order');
            $ids = collect($reviewerIds)->map(fn ($id) => (int) $id)->unique()
                ->reject(fn ($id) => $id === $finalApproverId || in_array($id, $doneUserIds, true))
                ->values();

            foreach ($ids as $userId) {
                $doc->comments()->create(['user_id' => $userId, 'step_order' => ++$step, 'status' => 'waiting']);
            }
            $doc->comments()->create([
                'user_id' => $finalApproverId, 'step_order' => ++$step, 'is_final' => true, 'status' => 'waiting',
            ]);

            $first = $doc->comments()->where('status', 'waiting')->reorder('step_order')->first();
            $sameUser = $previous && $previous->user_id === $first->user_id;
            static::activate($doc, $first, $sameUser ? $previous->received_at : null, ! $sameUser);
        });
    }

    /** A reviewer (or the Boss) submits their comment and the document moves on. */
    public static function submit(Document $doc, Comment $comment, ?string $text, bool $esign = false, bool $close = false): void
    {
        DB::transaction(function () use ($doc, $comment, $text, $esign, $close) {
            $comment->update([
                'comment' => $text,
                'is_submit' => true,
                'is_esign' => $comment->is_final && $esign,
                'status' => 'done',
                'submitted_at' => now(),
            ]);

            if ($comment->is_final) {
                $doc->update([
                    'is_complete' => true,
                    'status' => 'completed',
                    'date_complete' => now(),
                    'is_closed' => $close || $doc->is_closed,
                ]);
                $doc->log('completed', $esign ? 'Signed electronically' : null);

                if ($doc->created_by && $doc->created_by !== $comment->user_id) {
                    UserAlert::send(
                        "ឯកសារ {$doc->document_code} ត្រូវបានបញ្ចប់ | Document {$doc->document_code} has been completed",
                        "/documents/{$doc->id}",
                        [$doc->created_by]
                    );
                }

                return;
            }

            $doc->log('commented');
            $next = $doc->comments()->where('status', 'waiting')
                ->where('step_order', '>', $comment->step_order)->reorder('step_order')->first();

            if ($next) {
                static::activate($doc, $next);
                $doc->update(['status' => 'in_review']);
            }
        });
    }

    /**
     * Send the document back to an earlier person (return) or re-open a step (admin reset).
     * Every step from the target onward becomes "waiting" again.
     */
    public static function moveTo(Document $doc, Comment $target, string $action = 'returned', ?string $note = null): void
    {
        DB::transaction(function () use ($doc, $target, $action, $note) {
            $doc->comments()
                ->where('step_order', '>', $target->step_order)
                ->update(['status' => 'waiting', 'is_submit' => false, 'is_esign' => false, 'submitted_at' => null]);

            $doc->update([
                'status' => $action === 'returned' ? 'returned' : 'in_review',
                'is_complete' => false,
                'date_complete' => null,
            ]);

            $target->update(['is_submit' => false, 'is_esign' => false, 'submitted_at' => null]);
            static::activate($doc, $target->fresh());
            $doc->log($action, $note);
        });
    }

    /** Remove a person from the flow (not the Boss). If it was their turn, pass to the next. */
    public static function remove(Document $doc, Comment $comment): void
    {
        abort_if($comment->is_final, 422, 'The final approver cannot be removed. Edit the document to change the Boss.');

        DB::transaction(function () use ($doc, $comment) {
            $wasCurrent = $comment->status === 'current';
            $comment->delete();
            $doc->log('removed_reviewer', $comment->user?->name);

            if ($wasCurrent) {
                $next = $doc->comments()->where('status', 'waiting')
                    ->where('step_order', '>', $comment->step_order)->reorder('step_order')->first();
                if ($next) {
                    static::activate($doc, $next);
                }
            }
        });
    }

    /**
     * "Add Comment" screen: put a person into the flow just before the Boss.
     * If it is already submitted it is stored as done; otherwise they wait their turn
     * (or get the turn right away if they come before the current person).
     */
    public static function addStep(Document $doc, int $userId, ?string $text, bool $submitted, bool $esign = false): Comment
    {
        return DB::transaction(function () use ($doc, $userId, $text, $submitted, $esign) {
            $final = $doc->comments()->where('is_final', true)->first();
            if ($final) {
                $step = $final->step_order;
                $doc->comments()->where('step_order', '>=', $step)->increment('step_order');
            } else {
                $step = (int) $doc->comments()->max('step_order') + 1;
            }

            $comment = $doc->comments()->create([
                'user_id' => $userId,
                'step_order' => $step,
                'status' => $submitted ? 'done' : 'waiting',
                'comment' => $text,
                'is_submit' => $submitted,
                'is_esign' => $esign,
                'submitted_at' => $submitted ? now() : null,
            ]);

            if (! $submitted && ! $doc->is_complete) {
                $current = $doc->comments()->where('status', 'current')->first();
                if (! $current || $current->step_order > $step) {
                    $current?->update(['status' => 'waiting']);
                    static::activate($doc, $comment);
                }
            }
            $doc->log('reviewer_added', $comment->user?->name);

            return $comment;
        });
    }

    protected static function activate(Document $doc, Comment $comment, $receivedAt = null, bool $notify = true): void
    {
        $comment->update(['status' => 'current', 'received_at' => $receivedAt ?? now()]);
        $doc->update(['current_step' => $comment->step_order]);

        if ($notify) {
            $code = $doc->document_code ?: $doc->incoming_code ?: '#'.$doc->id;
            $text = $comment->is_final
                ? "ឯកសារ {$code} រង់ចាំការពិនិត្យ និងចុះហត្ថលេខា | Document {$code} is waiting for your approval & signature"
                : "ឯកសារ {$code} រង់ចាំយោបល់របស់អ្នក | Document {$code} is waiting for your comment";
            UserAlert::send($text, "/documents/{$doc->id}", [$comment->user_id]);
        }
    }
}
