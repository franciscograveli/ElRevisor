<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class ClaudeReviewRunner
{
    public function run(string $workspaceDir, int $prNumber): string
    {
        $prompt = sprintf('/code-review %d', $prNumber);

        $process = proc_open(
            ['claude', '-p', $prompt, '--dangerously-skip-permissions'],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $workspaceDir
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start claude CLI');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException("claude CLI exited with {$exitCode}: {$stderr}\n{$stdout}");
        }

        return $stdout;
    }
}
