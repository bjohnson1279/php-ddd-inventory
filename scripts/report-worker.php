<?php

require_once __DIR__ . '/../vendor/autoload.php';
$capsule = require_once __DIR__ . '/../src/Infrastructure/Persistence/bootstrap_database.php';

use Illuminate\Database\Capsule\Manager as DB;

$once = in_array('--once', $argv);

echo "[ReportWorker] Starting PHP report worker...\n";

do {
    // 1. Process Scheduler
    try {
        $now = date('Y-m-d H:i:s');
        $dueSchedules = DB::table('report_schedules')->where('next_run_at', '<=', $now)->get();
        foreach ($dueSchedules as $schedule) {
            // Update next run at
            // Mock cron parser by simply adding 24 hours
            $nextRun = date('Y-m-d H:i:s', strtotime('+24 hours'));
            DB::table('report_schedules')->where('id', $schedule->id)->update([
                'next_run_at' => $nextRun,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Queue Execution
            $execId = \Ramsey\Uuid\Uuid::uuid4()->toString();
            DB::table('report_executions')->insert([
                'id' => $execId,
                'report_definition_id' => $schedule->report_definition_id,
                'status' => 'PENDING',
                'format' => 'csv',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            echo "[ReportWorker] Scheduled report {$schedule->report_definition_id} queued for execution.\n";
        }
    } catch (\Throwable $e) {
        echo "[ReportWorker] Scheduler error: " . $e->getMessage() . "\n";
    }

    // 2. Process Executions
    try {
        $pendingExecs = DB::table('report_executions')->where('status', 'PENDING')->get();
        foreach ($pendingExecs as $exec) {
            echo "[ReportWorker] Processing report execution {$exec->id}...\n";
            DB::table('report_executions')->where('id', $exec->id)->update(['status' => 'PROCESSING']);
            
            try {
                // Mock report generation logic
                $def = DB::table('report_definitions')->where('id', $exec->report_definition_id)->first();
                if (!$def) throw new \Exception("Report definition not found");
                
                $filename = strtolower($def->type) . "_{$exec->id}.{$exec->format}";
                $fileUrl = "/uploads/reports/{$filename}";
                
                DB::table('report_executions')->where('id', $exec->id)->update([
                    'status' => 'COMPLETED',
                    'file_url' => $fileUrl,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                echo "[ReportWorker] Report {$exec->id} generated at {$fileUrl}\n";
            } catch (\Throwable $e) {
                DB::table('report_executions')->where('id', $exec->id)->update([
                    'status' => 'FAILED',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                echo "[ReportWorker] Error processing report {$exec->id}: " . $e->getMessage() . "\n";
            }
        }
    } catch (\Throwable $e) {
        echo "[ReportWorker] Execution error: " . $e->getMessage() . "\n";
    }

    if ($once) {
        break;
    }
    usleep(5000000); // 5s

} while (!$once);

echo "[ReportWorker] Finished.\n";
