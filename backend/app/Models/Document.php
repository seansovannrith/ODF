<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Html;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'letter_no', 'incoming_code', 'document_code', 'from_source', 'description',
        'document_type_id', 'category_id', 'organisation_id',
        'dateline', 'date_complete', 'status', 'current_step',
        'is_complete', 'is_closed', 'created_by',
    ];

    protected $casts = [
        'created_by' => 'integer',
        'dateline' => 'datetime',
        'date_complete' => 'datetime',
        'is_complete' => 'boolean',
        'is_closed' => 'boolean',
    ];

    public function getDescriptionAttribute(?string $value): ?string
    {
        return Html::clean($value);
    }

    public function documentType() { return $this->belongsTo(DocumentType::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function organisation() { return $this->belongsTo(Organisation::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function attachments() { return $this->hasMany(DocumentAttachment::class); }
    public function histories() { return $this->hasMany(DocumentHistory::class)->latest(); }

    /** The flow: every reviewer in order (the Boss is the last, is_final = true). */
    public function comments() { return $this->hasMany(Comment::class)->orderBy('step_order'); }

    public function currentComment() { return $this->hasOne(Comment::class)->where('status', 'current'); }

    public function log(string $action, ?string $note = null, ?int $userId = null): void
    {
        $this->histories()->create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'note' => $note,
        ]);
    }

    /** A user may see a document if they registered it, are in its flow, or have document_grand_access. */
    public function visibleTo(User $user): bool
    {
        if ($user->seesAllDocuments() || $this->created_by === $user->id) {
            return true;
        }

        return $this->comments()->where('user_id', $user->id)->exists();
    }
}
