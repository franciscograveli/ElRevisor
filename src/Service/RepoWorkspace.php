<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class RepoWorkspace
{
    public function __construct(private readonly string $workspacesRoot)
    {
        if (getenv('GH_TOKEN') !== false) {
            $this->run(['gh', 'auth', 'setup-git'], sys_get_temp_dir());
        }
    }

    /**
     * @param array{repo: string, clone_url: string, head_sha: string} $job
     */
    public function prepare(array $job): string
    {
        $dir = $this->workspacesRoot . '/' . str_replace('/', '__', $job['repo']);

        if (!is_dir($dir)) {
            $this->run(['git', 'clone', $job['clone_url'], $dir], $this->workspacesRoot);
        } else {
            $this->run(['git', 'fetch', 'origin'], $dir);
        }

        $this->run(['git', 'checkout', '--force', $job['head_sha']], $dir);
        $this->run(['git', 'reset', '--hard', $job['head_sha']], $dir);

        return $dir;
    }

    /**
     * @param list<string> $command
     */
    private function run(array $command, string $cwd): void
    {
        if (!is_dir($cwd)) {
            mkdir($cwd, 0775, true);
        }

        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $cwd);

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start process: ' . implode(' ', $command));
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                'Command failed (' . implode(' ', $command) . "): {$stderr}\n{$stdout}"
            );
        }
    }
}
