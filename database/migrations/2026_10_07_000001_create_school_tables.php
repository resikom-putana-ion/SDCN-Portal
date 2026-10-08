<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('admin');
        });
        Schema::create('school_records', function (Blueprint $table) {
            $table->id();
            $table->string('kind')->index();
            $table->string('code')->unique();
            $table->json('data');
            $table->timestamps();
        });
        Schema::create('admission_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->unique()->constrained('school_records');
            $table->foreignId('student_id')->unique()->constrained('school_records');
            $table->timestamps();
        });
        Schema::create('school_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('school_records');
            $table->string('reference')->unique();
            $table->unsignedBigInteger('amount');
            $table->string('method');
            $table->string('status')->default('Menunggu');
            $table->date('received_at');
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });
        Schema::create('school_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('school_records');
            $table->string('name');
            $table->string('path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_documents');
        Schema::dropIfExists('school_payments');
        Schema::dropIfExists('admission_activations');
        Schema::dropIfExists('school_records');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
