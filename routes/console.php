<?php

use App\Services\BudgetRoleService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ebudget:clean-department-id {--dry-run : Only show records to be cleaned without updating database}', function () {
    $isDryRun = (bool) $this->option('dry-run');
    $this->info($isDryRun ? '--- DRY RUN: Scanning invalid department_id records ---' : '--- Starting scan & clean of invalid department_id records ---');

    $totalUpdated = 0;

    // 1. VET Manpower: All rows must have department_id = NULL
    $vetRecords = DB::connection('pgsql')
        ->table('ebudget_vet_manpower')
        ->whereNotNull('department_id')
        ->where('department_id', '<>', '')
        ->select('id', 'document_no', 'base_branch_id', 'create_eid', 'department_id')
        ->get();

    if ($vetRecords->isNotEmpty()) {
        $this->warn("Found {$vetRecords->count()} rows in ebudget_vet_manpower with department_id set:");
        foreach ($vetRecords->groupBy('document_no') as $docNo => $group) {
            $first = $group->first();
            $this->line("  - Doc: {$docNo}, Branch: {$first->base_branch_id}, Creator: {$first->create_eid}, Old Dept: {$first->department_id}, Rows: {$group->count()}");
        }

        if (! $isDryRun) {
            $vetUpdated = DB::connection('pgsql')
                ->table('ebudget_vet_manpower')
                ->whereNotNull('department_id')
                ->update(['department_id' => null]);
            $totalUpdated += $vetUpdated;
            $this->info("  => Updated ebudget_vet_manpower: {$vetUpdated} rows set to department_id = NULL.");
        }
    } else {
        $this->info('ebudget_vet_manpower: all rows already have department_id = NULL.');
    }

    // 2. Scan all other budget tables where department_id is set but creator is not BACK-
    $tables = [
        'ebudget_gl_entry',
        'ebudget_non_vet_manpower',
        'ebudget_position_adjustment',
        'ebudget_medical_equipment',
        'ebudget_computer_equipment',
        'ebudget_furniture',
        'ebudget_tools_equipment',
        'ebudget_car',
        'ebudget_renovate',
        'ebudget_software',
        'ebudget_revenue',
        'ebudget_budget_plan_item',
    ];

    $creatorsCache = [];
    foreach ($tables as $table) {
        $records = DB::connection('pgsql')
            ->table($table)
            ->whereNotNull('department_id')
            ->where('department_id', '<>', '')
            ->select('id', 'document_no', 'create_eid', 'base_branch_id', 'department_id')
            ->get();

        $invalidRecords = [];
        foreach ($records as $r) {
            $eid = strtolower(trim((string) $r->create_eid));
            if (! array_key_exists($eid, $creatorsCache)) {
                $creatorsCache[$eid] = BudgetRoleService::resolveDepartmentForCreator($eid);
            }
            $validDept = $creatorsCache[$eid];
            if ($validDept === null) {
                $invalidRecords[] = $r;
            }
        }

        if (! empty($invalidRecords)) {
            $grouped = collect($invalidRecords)->groupBy('document_no');
            $this->warn("Found {$grouped->count()} documents (" . count($invalidRecords) . " rows) in {$table} with invalid department_id (creator not BACK-):");
            foreach ($grouped as $docNo => $group) {
                $first = $group->first();
                $this->line("  - Doc: {$docNo}, Branch: {$first->base_branch_id}, Creator: {$first->create_eid}, Old Dept: {$first->department_id}, Rows: {$group->count()}");
            }

            if (! $isDryRun) {
                $ids = array_map(fn($r) => $r->id, $invalidRecords);
                $updated = DB::connection('pgsql')
                    ->table($table)
                    ->whereIn('id', $ids)
                    ->update(['department_id' => null]);
                $totalUpdated += $updated;
                $this->info("  => Updated {$table}: {$updated} rows set to department_id = NULL.");
            }
        } else {
            $this->info("{$table}: no invalid department_id records found.");
        }
    }

    if ($isDryRun) {
        $this->comment('DRY RUN complete. Run without --dry-run to apply changes.');
    } else {
        $this->info("SUCCESS: Clean department_id complete. Total rows updated to NULL: {$totalUpdated}");
    }
})->purpose('Clean invalid department_id on branch documents created by non-BACK employees');
