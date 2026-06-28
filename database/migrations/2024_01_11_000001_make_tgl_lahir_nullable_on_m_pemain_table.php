<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeTglLahirNullableOnMPemainTable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE m_pemain MODIFY tgl_lahir DATE NULL');
        DB::statement('ALTER TABLE m_pemain MODIFY usia TINYINT UNSIGNED NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE m_pemain MODIFY tgl_lahir DATE NOT NULL');
        DB::statement('ALTER TABLE m_pemain MODIFY usia TINYINT UNSIGNED NOT NULL');
    }
}
