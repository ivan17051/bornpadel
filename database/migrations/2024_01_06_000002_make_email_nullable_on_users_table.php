<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeEmailNullableOnUsersTable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');
    }
}
