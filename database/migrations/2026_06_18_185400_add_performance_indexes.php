<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Invoices — these compound indexes cover the most common query patterns:
        //   WHERE organization_id = ? AND status = ?  (every list/dashboard page)
        //   WHERE organization_id = ? ORDER BY created_at  (invoice listing)
        //   WHERE issue_date BETWEEN ? AND ?  (analytics date-range filters)
        //   WHERE payment_status = ?  (payment filtering)
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['organization_id', 'status'], 'idx_invoices_org_status');
            $table->index(['organization_id', 'created_at'], 'idx_invoices_org_created');
            $table->index('issue_date', 'idx_invoices_issue_date');
            $table->index('payment_status', 'idx_invoices_payment_status');
            $table->index('status', 'idx_invoices_status');
        });

        // Activity logs — filtered by org + date on every dashboard and platform page
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['organization_id', 'created_at'], 'idx_activity_logs_org_created');
        });

        // NRS API logs — filtered by org + date in analytics, already has status_code index
        Schema::table('nrs_api_logs', function (Blueprint $table) {
            $table->index(['organization_id', 'created_at'], 'idx_nrs_api_logs_org_created');
        });

        // NRS submissions — queried by invoice_id and idempotency_key
        Schema::table('nrs_submissions', function (Blueprint $table) {
            $table->index('idempotency_key', 'idx_nrs_submissions_idempotency');
        });

        // Customers & Products — filtered by organization_id on every org-scoped page
        if (! $this->hasIndex('customers', 'idx_customers_org')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index('organization_id', 'idx_customers_org');
            });
        }

        if (! $this->hasIndex('products', 'idx_products_org')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index('organization_id', 'idx_products_org');
            });
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_org_status');
            $table->dropIndex('idx_invoices_org_created');
            $table->dropIndex('idx_invoices_issue_date');
            $table->dropIndex('idx_invoices_payment_status');
            $table->dropIndex('idx_invoices_status');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('idx_activity_logs_org_created');
        });

        Schema::table('nrs_api_logs', function (Blueprint $table) {
            $table->dropIndex('idx_nrs_api_logs_org_created');
        });

        Schema::table('nrs_submissions', function (Blueprint $table) {
            $table->dropIndex('idx_nrs_submissions_idempotency');
        });

        if ($this->hasIndex('customers', 'idx_customers_org')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex('idx_customers_org');
            });
        }

        if ($this->hasIndex('products', 'idx_products_org')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('idx_products_org');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $indexes = $connection->getSchemaBuilder()->getIndexes($table);

        foreach ($indexes as $index) {
            if (($index['name'] ?? '') === $indexName) {
                return true;
            }
        }

        return false;
    }
};
