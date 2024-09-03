<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFirebaseTopicsTable extends Migration
{
    public function up()
    {
        Schema::create('firebase_topics', function (Blueprint $table) {
            $table->id();
            $table->string('topic_name')->unique();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('firebase_topics');
    }
}
