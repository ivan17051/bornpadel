<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddFriendlyLeagueSupportTables extends Migration
{
    public function up()
    {
        if (Schema::hasTable('grup')) {
            Schema::table('grup', function (Blueprint $table) {
                if (! Schema::hasColumn('grup', 'poin_didapat')) {
                    $table->integer('poin_didapat')->default(0)->after('is_aktif');
                }
                if (! Schema::hasColumn('grup', 'set_menang')) {
                    $table->integer('set_menang')->default(0)->after('poin_didapat');
                }
                if (! Schema::hasColumn('grup', 'games_menang')) {
                    $table->integer('games_menang')->default(0)->after('set_menang');
                }
                if (! Schema::hasColumn('grup', 'stats_reached_at')) {
                    $table->timestamp('stats_reached_at')->nullable()->after('games_menang');
                }
            });
        }

        if (Schema::hasTable('pertandingan')) {
            Schema::table('pertandingan', function (Blueprint $table) {
                if (! Schema::hasColumn('pertandingan', 'id_grup1')) {
                    $table->unsignedBigInteger('id_grup1')->nullable()->after('id_grup');
                }
                if (! Schema::hasColumn('pertandingan', 'id_grup2')) {
                    $table->unsignedBigInteger('id_grup2')->nullable()->after('id_grup1');
                }
                if (! Schema::hasColumn('pertandingan', 'id_pemain1_partner')) {
                    $table->unsignedBigInteger('id_pemain1_partner')->nullable()->after('id_pemain2');
                }
                if (! Schema::hasColumn('pertandingan', 'id_pemain2_partner')) {
                    $table->unsignedBigInteger('id_pemain2_partner')->nullable()->after('id_pemain1_partner');
                }
            });

            DB::statement("ALTER TABLE pertandingan MODIFY nama_ronde ENUM('Fase Grup', 'Babak 16 Besar', 'Perempatfinal', 'Semifinal', 'Final', 'Perebutan Juara 3', 'Friendly') NOT NULL");
        }
    }

    public function down()
    {
        if (Schema::hasTable('pertandingan')) {
            DB::table('pertandingan')->where('nama_ronde', 'Friendly')->delete();
            DB::statement("ALTER TABLE pertandingan MODIFY nama_ronde ENUM('Fase Grup', 'Babak 16 Besar', 'Perempatfinal', 'Semifinal', 'Final', 'Perebutan Juara 3') NOT NULL");

            Schema::table('pertandingan', function (Blueprint $table) {
                foreach (['id_grup1', 'id_grup2', 'id_pemain1_partner', 'id_pemain2_partner'] as $column) {
                    if (Schema::hasColumn('pertandingan', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('grup')) {
            Schema::table('grup', function (Blueprint $table) {
                foreach (['poin_didapat', 'set_menang', 'games_menang', 'stats_reached_at'] as $column) {
                    if (Schema::hasColumn('grup', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
}
