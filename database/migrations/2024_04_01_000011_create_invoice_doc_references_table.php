<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_doc_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('reference_type'); // billing, dispatch, receipt, originator, contract, additional
            $table->string('document_id');
            $table->date('issue_date')->nullable();
            $table->string('document_type_code')->nullable();
            $table->string('document_description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_doc_references');
    }
};
