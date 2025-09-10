<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kyc_submissions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            // store only hashed Aadhaar; keep last4 for display
            $t->string('aadhaar_hash', 64);
            $t->string('aadhaar_last4', 4);
            $t->string('document_path'); // storage/app/public/...
            $t->string('selfie_path');   // storage/app/public/...
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->text('review_notes')->nullable();
            $t->timestamps();

            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_submissions');
    }
};
