<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakePertandinganPemainNullable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE pertandingan MODIFY id_pemain1 BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE pertandingan MODIFY id_pemain2 BIGINT UNSIGNED NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE pertandingan MODIFY id_pemain1 BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE pertandingan MODIFY id_pemain2 BIGINT UNSIGNED NOT NULL');
    }
}
