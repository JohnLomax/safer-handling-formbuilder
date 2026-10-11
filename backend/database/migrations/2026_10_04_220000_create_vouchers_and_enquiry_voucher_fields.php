<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->decimal('amount', 10, 2);
                $table->boolean('is_active')->default(true);
                $table->timestamp('valid_from')->nullable();
                $table->timestamp('valid_until')->nullable();
                $table->unsignedInteger('max_redemptions')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('enquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('enquiries', 'voucher_id')) {
                $table->foreignId('voucher_id')->nullable()->after('extra_notes')->constrained('vouchers')->nullOnDelete();
            }
            if (! Schema::hasColumn('enquiries', 'voucher_code')) {
                $table->string('voucher_code')->nullable()->after('voucher_id');
            }
            if (! Schema::hasColumn('enquiries', 'voucher_amount')) {
                $table->decimal('voucher_amount', 10, 2)->nullable()->after('voucher_code');
            }
            if (! Schema::hasColumn('enquiries', 'quote_net_original')) {
                $table->decimal('quote_net_original', 10, 2)->nullable()->after('voucher_amount');
            }
            if (! Schema::hasColumn('enquiries', 'quote_net_final')) {
                $table->decimal('quote_net_final', 10, 2)->nullable()->after('quote_net_original');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            if (Schema::hasColumn('enquiries', 'voucher_id')) {
                $table->dropConstrainedForeignId('voucher_id');
            }
            foreach (['voucher_code', 'voucher_amount', 'quote_net_original', 'quote_net_final'] as $column) {
                if (Schema::hasColumn('enquiries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('vouchers');
    }
};
