<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('letter_no')->nullable();        // លេខលិខិត
            $table->string('incoming_code')->nullable();    // លេខកូដចូល
            $table->string('document_code')->nullable();    // លេខកូដឯកសារ
            $table->string('from_source')->nullable();      // មកពី
            $table->longText('description')->nullable();    // បរិយាយ (rich text)
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->dateTime('dateline')->nullable();
            $table->dateTime('date_complete')->nullable();
            // draft -> in_review -> completed ; returned when sent back
            $table->enum('status', ['draft', 'in_review', 'returned', 'completed'])->default('in_review');
            $table->unsignedInteger('current_step')->default(1);
            $table->boolean('is_complete')->default(false);
            $table->boolean('is_closed')->default(false);   // បិទឯកសារ
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_closed', 'is_complete']);
        });

        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One row per person in the flow. step_order defines who comments next.
        // The row with is_final = true is the Boss who completes and signs.
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->boolean('is_final')->default(false);
            // waiting = not their turn yet, current = their turn, done = submitted
            $table->enum('status', ['waiting', 'current', 'done'])->default('waiting');
            $table->longText('comment')->nullable();
            $table->boolean('is_submit')->default(false);
            $table->boolean('is_esign')->default(false);
            $table->dateTime('received_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('document_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');   // created, commented, returned, completed, closed, reopened ...
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('user_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_text');
            $table->string('alert_link')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('user_alert_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('read_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['user_alert_user', 'user_alerts', 'document_histories', 'comments', 'document_attachments', 'documents'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
