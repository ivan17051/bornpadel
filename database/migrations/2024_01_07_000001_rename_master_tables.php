<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class RenameMasterTables extends Migration
{
    public function up()
    {
        if (Schema::hasTable('turnamen') && ! Schema::hasTable('m_turnamen')) {
            Schema::rename('turnamen', 'm_turnamen');
        }

        if (Schema::hasTable('pemain') && ! Schema::hasTable('m_pemain')) {
            Schema::rename('pemain', 'm_pemain');
        }

        if (Schema::hasTable('users') && ! Schema::hasTable('m_users')) {
            Schema::rename('users', 'm_users');
        }
    }

    public function down()
    {
        if (Schema::hasTable('m_users') && ! Schema::hasTable('users')) {
            Schema::rename('m_users', 'users');
        }

        if (Schema::hasTable('m_pemain') && ! Schema::hasTable('pemain')) {
            Schema::rename('m_pemain', 'pemain');
        }

        if (Schema::hasTable('m_turnamen') && ! Schema::hasTable('turnamen')) {
            Schema::rename('m_turnamen', 'turnamen');
        }
    }
}
