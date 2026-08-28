<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Repository\ReviewJobRepository;
use App\Service\ClaudeReviewRunner;
use App\Service\RepoWorkspace;
use App\Support\Env;

$repository = new ReviewJobRepository();
$workspace = new RepoWorkspace(Env::string('WORKSPACES_ROOT', dirname(__DIR__) . '/storage/workspaces'));
$runner = new ClaudeReviewRunner();

fwrite(STDOUT, "[worker] started\n");

while (true) {
    $job = $repository->claimNext();

    if ($job === null) {
        sleep(2);
        continue;
    }

    fwrite(STDOUT, "[worker] processing job #{$job['id']} ({$job['repo']} PR #{$job['pr_number']})\n");

    try {
        $dir = $workspace->prepare($job);
        $runner->run($dir, (int) $job['pr_number']);
        $repository->complete((int) $job['id']);
        fwrite(STDOUT, "[worker] job #{$job['id']} done\n");
    } catch (Throwable $e) {
        $repository->fail((int) $job['id'], $e->getMessage());
        fwrite(STDERR, "[worker] job #{$job['id']} failed: {$e->getMessage()}\n");
    }
}
