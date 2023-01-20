<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompanyMastersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('company_master', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('company_email')->unique();
            $table->string('account_number');
            $table->string('consumerKey');
            $table->string('tokenId');
            $table->string('consumerSecret');
            $table->string('tokenSecret');
            $table->string('staging_consumerKey');
            $table->string('staging_tokenId');
            $table->string('staging_consumerSecret');
            $table->string('staging_tokenSecret');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('company_master');
    }
}
