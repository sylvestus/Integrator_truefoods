<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateOauthTablesToUseUuids extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Clear existing data first
        DB::table('oauth_personal_access_clients')->truncate();
        DB::table('oauth_access_tokens')->truncate();
        DB::table('oauth_refresh_tokens')->truncate();
        DB::table('oauth_auth_codes')->truncate();
        DB::table('oauth_clients')->truncate();

        // Update oauth_clients table - need to remove auto_increment first
        DB::statement('ALTER TABLE oauth_clients MODIFY id VARCHAR(36) NOT NULL');

        // Update oauth_access_tokens table
        DB::statement('ALTER TABLE oauth_access_tokens MODIFY client_id VARCHAR(36) NOT NULL');

        // Update oauth_auth_codes table
        DB::statement('ALTER TABLE oauth_auth_codes MODIFY client_id VARCHAR(36) NOT NULL');

        // Update oauth_personal_access_clients table
        DB::statement('ALTER TABLE oauth_personal_access_clients MODIFY client_id VARCHAR(36) NOT NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // This migration is not easily reversible
    }
}
