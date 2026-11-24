<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use MigratesCompanyDatabases;

    public function up(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if ($connection !== 'Bentaher3') {
                return;
            }

            if (Schema::connection($connection)->hasTable('conversion_review_issues')) {
                return;
            }

            Schema::connection($connection)->create('conversion_review_issues', function (Blueprint $table): void {
                $table->id();
                $table->string('issue_code', 64);
                $table->string('severity', 16)->default('warning');
                $table->string('message');
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('contract_id')->nullable();
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->unsignedBigInteger('journal_id')->nullable();
                $table->string('party_name')->nullable();
                $table->text('details')->nullable();
                $table->string('status', 16)->default('open');
                $table->text('manager_notes')->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'issue_code']);
                $table->index('customer_id');
            });
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if ($connection === 'Bentaher3') {
                Schema::connection($connection)->dropIfExists('conversion_review_issues');
            }
        });
    }
};
