<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Partition stock_prices table by YEAR(date) for long-term scalability.
 *
 * Benefits:
 *  - Partition pruning: queries for a specific year only scan that partition
 *  - Old partitions can be dropped instantly (vs DELETE which is slow on large tables)
 *  - INSERT performance improves as each partition stays smaller
 *
 * Trade-offs accepted:
 *  - FK to stocks.id is dropped (MySQL disallows FK on partitioned tables)
 *    → Integrity enforced at application level via sync process
 *  - PRIMARY KEY changed to composite (id, date)
 *    → MySQL RANGE partitioning requires partition key in every unique index
 *  - created_at / updated_at dropped (irrelevant for immutable market OHLCV data)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Drop FK — MySQL does not support FK on partitioned tables
        DB::statement('ALTER TABLE stock_prices DROP FOREIGN KEY stock_prices_stock_id_foreign');

        // Step 2: Drop timestamps — market data is immutable; date column is the record timestamp
        DB::statement('ALTER TABLE stock_prices DROP COLUMN created_at, DROP COLUMN updated_at');

        // Step 3: Change PRIMARY KEY to composite (id, date)
        // MySQL RANGE partitioning requires ALL unique indexes to include the partition column.
        // The unique key (stock_id, date) already includes date ✓
        // The primary key (id) does not → must become (id, date)
        DB::statement('ALTER TABLE stock_prices DROP PRIMARY KEY, ADD PRIMARY KEY (id, date)');

        // Step 4: Apply RANGE partitioning by year
        // p_future catches any year not explicitly listed — add a new partition each January
        DB::statement("
            ALTER TABLE stock_prices
            PARTITION BY RANGE (YEAR(date)) (
                PARTITION p2018 VALUES LESS THAN (2019),
                PARTITION p2019 VALUES LESS THAN (2020),
                PARTITION p2020 VALUES LESS THAN (2021),
                PARTITION p2021 VALUES LESS THAN (2022),
                PARTITION p2022 VALUES LESS THAN (2023),
                PARTITION p2023 VALUES LESS THAN (2024),
                PARTITION p2024 VALUES LESS THAN (2025),
                PARTITION p2025 VALUES LESS THAN (2026),
                PARTITION p2026 VALUES LESS THAN (2027),
                PARTITION p2027 VALUES LESS THAN (2028),
                PARTITION p_future VALUES LESS THAN MAXVALUE
            )
        ");
    }

    public function down(): void
    {
        // Reverse: remove partitioning, restore PK, timestamps, and FK
        DB::statement('ALTER TABLE stock_prices REMOVE PARTITIONING');
        DB::statement('ALTER TABLE stock_prices DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
        DB::statement('ALTER TABLE stock_prices ADD COLUMN created_at TIMESTAMP NULL, ADD COLUMN updated_at TIMESTAMP NULL');
        DB::statement('ALTER TABLE stock_prices ADD CONSTRAINT stock_prices_stock_id_foreign FOREIGN KEY (stock_id) REFERENCES stocks(id) ON DELETE CASCADE');
    }
};
