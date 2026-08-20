<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Handle the case where the rename already happened but migration didn't complete
        if (Schema::hasTable('class_subject')) {
            Schema::rename('class_subject', 'class_subjects');
        }

        Schema::table('class_subjects', function (Blueprint $table) {
            if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                // Drop foreign keys first (they use the original constraint names from the class_subject table)
                $table->dropForeign('class_subject_class_id_foreign');
                $table->dropForeign('class_subject_subject_id_foreign');

                // Now drop the unique index
                $table->dropUnique('class_subject_class_id_subject_id_unique');
            }

            // Add section_id column if it doesn't exist
            if (! Schema::hasColumn('class_subjects', 'section_id')) {
                $table->foreignId('section_id')->nullable()->after('class_id')->constrained('sections')->cascadeOnDelete();
            }

            if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                // Re-add foreign keys with new names
                $table->foreign('class_id')->references('id')->on('classes')->cascadeOnDelete();
                $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();

                // Add new unique constraint
                $table->unique(['class_id', 'section_id', 'subject_id'], 'class_subjects_class_section_subject_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_subjects', function (Blueprint $table) {
            $table->dropUnique('class_subjects_class_section_subject_unique');
            $table->dropConstrainedForeignId('section_id');
            $table->dropForeign(['class_id']);
            $table->dropForeign(['subject_id']);
            $table->unique(['class_id', 'subject_id'], 'class_subject_class_id_subject_id_unique');
            $table->foreign('class_id')->references('id')->on('classes')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
        });

        Schema::rename('class_subjects', 'class_subject');
    }
};