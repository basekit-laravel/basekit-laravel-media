<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('alt')->nullable();
            $table->timestamps();

            // A deleted file is kept, so that putting it back is possible.
            $table->softDeletes();

            // Indexed rather than unique: a soft deleted row still holds its path,
            // and a unique index would refuse the file being uploaded again. The
            // model refuses the duplicate instead, with a message the form can show.
            $table->index(['disk', 'path']);

            // Resolving a stored value to a media item does not know the disk — a form
            // hands over a URL and the disk's base is stripped off — and the composite
            // index above leads with `disk`, so it cannot answer that without reading
            // every entry. The single-column index is what keeps that a lookup.
            $table->index('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('media_folders');
    }
};
