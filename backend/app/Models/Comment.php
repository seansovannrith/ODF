<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Html;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use Auditable;

    protected $fillable = [
        'document_id', 'user_id', 'step_order', 'is_final', 'status',
        'comment', 'is_submit', 'is_esign', 'received_at', 'submitted_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'document_id' => 'integer',
        'step_order' => 'integer',
        'is_final' => 'boolean',
        'is_submit' => 'boolean',
        'is_esign' => 'boolean',
        'received_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function getCommentAttribute(?string $value): ?string
    {
        return Html::clean($value);
    }

    public function document() { return $this->belongsTo(Document::class); }
    public function user() { return $this->belongsTo(User::class); }
}
