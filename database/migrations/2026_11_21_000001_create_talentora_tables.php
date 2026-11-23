<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $t) {
            $t->id(); $t->string('setting_key', 100)->unique(); $t->text('setting_value'); $t->timestamps();
        });
        Schema::create('job_positions', function (Blueprint $t) {
            $t->id(); $t->string('code', 50)->unique(); $t->string('title', 200); $t->string('department', 150)->index();
            $t->string('employment_type', 20); $t->string('location', 200); $t->unsignedInteger('openings');
            $t->text('description'); $t->text('requirements'); $t->string('status', 10)->default('draft')->index();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->timestamps(); $t->index('created_at');
        });
        Schema::create('candidates', function (Blueprint $t) {
            $t->id(); $t->string('full_name', 150)->index(); $t->string('email')->unique(); $t->string('phone', 30);
            $t->string('location', 200)->nullable()->index(); $t->string('education', 200)->nullable(); $t->unsignedInteger('years_experience')->nullable();
            $t->text('skills')->nullable(); $t->text('experience_summary')->nullable(); $t->string('linkedin_url', 500)->nullable();
            $t->string('portfolio_url', 500)->nullable(); $t->string('cv_path', 500)->nullable(); $t->string('cv_original_name')->nullable();
            $t->string('cv_mime_type', 150)->nullable(); $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->timestamps(); $t->index('created_at');
        });
        Schema::create('applications', function (Blueprint $t) {
            $t->id(); $t->string('application_code', 50)->unique(); $t->foreignId('candidate_id')->constrained()->restrictOnDelete();
            $t->foreignId('job_position_id')->constrained()->restrictOnDelete(); $t->string('source', 20)->default('manual');
            $t->string('stage', 20)->default('applied')->index(); $t->date('applied_at')->index(); $t->dateTime('stage_updated_at');
            $t->dateTime('hired_at')->nullable(); $t->dateTime('rejected_at')->nullable(); $t->text('rejection_reason')->nullable(); $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->timestamps();
            $t->unique(['candidate_id', 'job_position_id']); $t->index(['job_position_id', 'stage']); $t->index('created_at');
        });
        Schema::create('application_stage_histories', function (Blueprint $t) {
            $t->id(); $t->foreignId('application_id')->constrained()->restrictOnDelete(); $t->string('from_stage', 20)->nullable();
            $t->string('to_stage', 20); $t->text('note')->nullable(); $t->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $t->dateTime('changed_at'); $t->index(['application_id', 'changed_at']);
        });
        Schema::create('interviews', function (Blueprint $t) {
            $t->id(); $t->foreignId('application_id')->constrained()->restrictOnDelete(); $t->foreignId('interviewer_id')->constrained('users')->restrictOnDelete();
            $t->dateTime('start_at')->index(); $t->dateTime('end_at'); $t->string('mode', 10); $t->string('meeting_link', 500)->nullable();
            $t->string('location')->nullable(); $t->string('status', 20)->default('scheduled'); $t->unsignedTinyInteger('rating')->nullable();
            $t->string('recommendation', 20)->nullable(); $t->text('feedback')->nullable(); $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->timestamps();
            $t->index(['application_id', 'status']); $t->index(['interviewer_id', 'status', 'start_at', 'end_at']); $t->index(['status', 'start_at']);
        });
    }

    public function down(): void
    {
        foreach (['interviews', 'application_stage_histories', 'applications', 'candidates', 'job_positions', 'app_settings'] as $table) Schema::dropIfExists($table);
    }
};
