<?php

use App\Models\Pemain;
use App\Services\PemainPhotoService;
use Illuminate\Database\Migrations\Migration;

class MigratePemainFotoToPublic extends Migration
{
    public function up()
    {
        $photoService = app(PemainPhotoService::class);
        $photoService->migrateStoredPhotos();
    }

    public function down()
    {
        // Files remain in public/img/pemain; paths are not reverted.
    }
}
