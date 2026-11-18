<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class DocumentAttachment extends Model
{
    use Auditable;

    protected $fillable = ['document_id', 'original_name', 'path', 'mime', 'size', 'uploaded_by'];

    protected $hidden = ['path'];

    protected $casts = ['uploaded_by' => 'integer', 'size' => 'integer'];

    public function document() { return $this->belongsTo(Document::class); }
}
