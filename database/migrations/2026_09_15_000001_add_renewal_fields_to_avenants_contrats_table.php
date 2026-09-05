<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avenants_contrats', function (Blueprint $table) {
            $table->foreignId('type_structure_id')->nullable()->after('motif')->constrained('types_structure')->nullOnDelete();
            $table->string('document_avenant_path')->nullable()->after('type_structure_id');
            $table->foreignId('propose_par_id')->nullable()->after('document_avenant_path')->constrained('users')->nullOnDelete();
            $table->timestamp('propose_le')->nullable()->after('propose_par_id');
            $table->json('metadata')->nullable()->after('decideur_id');
        });
    }

    public function down(): void
    {
        Schema::table('avenants_contrats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('type_structure_id');
            $table->dropConstrainedForeignId('propose_par_id');
            $table->dropColumn(['document_avenant_path', 'propose_le', 'metadata']);
        });
    }
};
