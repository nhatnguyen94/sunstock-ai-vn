<?php

use App\Support\Mojibake;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs stock_symbols.name values that an old Windows-console sync stored as CP437 mojibake
 * ("C├┤ng ty Cß╗ò phß║ºn" for "Công ty Cổ phần"). They showed up as gibberish in the search autocomplete.
 * Idempotent: rows that already read correctly are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('stock_symbols')->select(['id', 'name'])->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $fixed = Mojibake::repairCp437($row->name);
                if ($fixed !== null) {
                    DB::table('stock_symbols')->where('id', $row->id)->update(['name' => $fixed]);
                }
            }
        });
    }

    public function down(): void
    {
        // Data repair only — the corrupted text is not worth restoring.
    }
};
