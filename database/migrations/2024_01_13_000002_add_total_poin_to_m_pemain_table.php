<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTotalPoinToMpemainTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('m_pemain')) {
            return;
        }

        if (! Schema::hasColumn('m_pemain', 'total_poin')) {
            Schema::table('m_pemain', function (Blueprint $table) {
                $table->unsignedInteger('total_poin')->default(0)->after('rating');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('m_pemain') && Schema::hasColumn('m_pemain', 'total_poin')) {
            Schema::table('m_pemain', function (Blueprint $table) {
                $table->dropColumn('total_poin');
            });
        }
    }
}
