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
            $table->string('consumerKey')->nullable();
            $table->string('tokenId')->nullable();
            $table->string('consumerSecret')->nullable();
            $table->string('tokenSecret')->nullable();
            $table->string('staging_consumerKey')->nullable();
            $table->string('staging_tokenId')->nullable();
            $table->string('staging_consumerSecret')->nullable();
            $table->string('staging_tokenSecret')->nullable();
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
