<?php

use App\Models\Role;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------- Roles & permissions ----------
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });

        // ---------- Users: username, verification, two-factor ----------
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name_kh');
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_secret');
        });

        // ---------- User information ----------
        Schema::create('user_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('gender', ['Male', 'Female'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('id_card_no')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        // ---------- Audit log (every create / update / delete) ----------
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('description');           // created | updated | deleted
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('properties')->nullable();
            $table->string('host', 45)->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        // ---------- User activity (login, logout, create, edit, delete) ----------
        Schema::create('user_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('log_type', 20)->index();
            $table->string('table_name')->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('data')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        // ---------- Department abbreviations ----------
        Schema::table('departments', function (Blueprint $table) {
            $table->string('abbr')->nullable()->after('name_kh');
            $table->string('abbr_kh')->nullable()->after('abbr');
        });

        // ---------- Move the old single "role" column to the new roles ----------
        (new RbacSeeder)->run();

        if (Schema::hasColumn('users', 'role')) {
            $map = ['admin' => 'Admin', 'secretary' => 'Moderator', 'staff' => 'User', 'boss' => 'Direction'];
            foreach (DB::table('users')->get(['id', 'role']) as $u) {
                $roleId = Role::where('title', $map[$u->role] ?? 'User')->value('id');
                DB::table('role_user')->insertOrIgnore(['user_id' => $u->id, 'role_id' => $roleId]);
            }
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'secretary', 'staff', 'boss'])->default('staff');
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'email_verified_at', 'two_factor_secret', 'two_factor_enabled']);
        });
        Schema::table('departments', fn (Blueprint $table) => $table->dropColumn(['abbr', 'abbr_kh']));
        foreach (['user_logs', 'audit_logs', 'user_infos', 'role_user', 'permission_role', 'roles', 'permissions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
