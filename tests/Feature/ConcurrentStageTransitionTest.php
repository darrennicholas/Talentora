<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentStageTransitionTest extends TestCase
{
    use DatabaseMigrations;

    public function test_simultaneous_stage_transitions_allow_only_one_winner(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $position = JobPosition::factory()->create(['created_by' => $admin->id]);
        $candidate = Candidate::factory()->create(['created_by' => $admin->id]);
        $application = Application::factory()->create([
            'candidate_id' => $candidate->id,
            'job_position_id' => $position->id,
            'created_by' => $admin->id,
            'stage' => 'applied',
        ]);

        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'talentora-race-'.bin2hex(random_bytes(6));
        mkdir($directory);
        $gate = $directory.DIRECTORY_SEPARATOR.'go';
        $worker = base_path('tests/Support/concurrent_stage_transition_worker.php');
        $processes = [];

        try {
            foreach ([['screening', ''], ['rejected', 'Concurrent rejection.']] as $index => [$toStage, $note]) {
                $ready = $directory.DIRECTORY_SEPARATOR."ready-{$index}";
                $process = new Process([
                    PHP_BINARY, $worker, (string) $application->id, (string) $admin->id,
                    $toStage, $note, $gate, $ready,
                ], base_path(), $this->workerEnvironment());
                $process->setTimeout(45);
                $process->start();
                $processes[] = ['process' => $process, 'ready' => $ready];
            }

            $deadline = microtime(true) + 30;
            while (microtime(true) < $deadline) {
                if (collect($processes)->every(fn (array $entry) => file_exists($entry['ready']))) break;
                $failedWorker = collect($processes)->first(fn (array $entry) => ! $entry['process']->isRunning() && ! file_exists($entry['ready']));
                if ($failedWorker) {
                    $process = $failedWorker['process'];
                    $this->fail('A transition worker exited before reaching the synchronization point. '.$process->getErrorOutput().' '.$process->getOutput());
                }
                usleep(10_000);
            }
            $this->assertTrue(collect($processes)->every(fn (array $entry) => file_exists($entry['ready'])), 'Both transition requests must be ready before they are released.');
            file_put_contents($gate, 'go');

            $statuses = [];
            foreach ($processes as $entry) {
                $entry['process']->wait();
                $this->assertSame(0, $entry['process']->getExitCode(), $entry['process']->getErrorOutput());
                $statuses[] = trim($entry['process']->getOutput());
            }

            sort($statuses);
            $this->assertSame(['200', '409'], $statuses);
            $this->assertSame(1, DB::table('application_stage_histories')
                ->where('application_id', $application->id)
                ->whereNotNull('from_stage')
                ->count());
            $this->assertContains($application->fresh()->stage, ['screening', 'rejected']);
        } finally {
            foreach ($processes as $entry) {
                if ($entry['process']->isRunning()) $entry['process']->stop(1);
            }
            foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) unlink($file);
            rmdir($directory);
        }
    }

    private function workerEnvironment(): array
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}");

        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => config('app.key'),
            'DB_CONNECTION' => $connection,
            'DB_HOST' => $database['host'] ?? '',
            'DB_PORT' => (string) ($database['port'] ?? ''),
            'DB_DATABASE' => $database['database'] ?? '',
            'DB_USERNAME' => $database['username'] ?? '',
            'DB_PASSWORD' => $database['password'] ?? '',
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'BCRYPT_ROUNDS' => '4',
        ];
    }
}
