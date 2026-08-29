<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class ClaudeReviewRunner
{
    public function run(string $workspaceDir, int $prNumber): string
    {
        $prompt = sprintf(
            <<<'PROMPT'
            /code-review %1$d

            Depois do code review acima, faça também uma checagem separada de completude — isso é tão importante quanto achar bugs, não pule:

            1. Ache a issue que esta PR fecha (procure "Closes #N"/"Fecha #N" no corpo da PR #%1$d; se não achar nenhuma referência, pule esta checagem e diga isso).
            2. Leia o corpo COMPLETO dessa issue e liste todas as histórias de usuário/critérios de aceite reais que ela descreve — não resuma de memória.
            3. Para CADA história, verifique com evidência concreta no código (grep, não achismo) se ela foi implementada nesta PR. "A PR menciona isso na descrição" não é evidência — evidência é achar o código que faz a coisa acontecer.
            4. Histórias que dependem de outro trabalho ainda não mergeado não contam como gap desta PR, mas precisam ser citadas como tal, não omitidas silenciosamente.
            5. Se a issue já for uma fatia com escopo próprio de um trabalho maior (ex: uma sub-issue tipo "[Parte 1/N]" referenciando uma issue "épico" pai), valide contra o escopo da PRÓPRIA issue fechada — nunca contra o trabalho maior inteiro.

            Termine essa parte com um veredito explícito, em negrito:
            - **✅ COMPLETO** — todas as histórias da issue estão implementadas (cite qual trecho de código resolve cada uma).
            - **🟡 PARCIAL** — liste exatamente quais histórias faltam. Isso não é motivo pra bloquear o merge sozinho, mas deixe claro que a issue não deveria fechar ainda; se a PR usa "Closes #N", recomende trocar para "Parte de #N" (sem a palavra-chave de fechamento do GitHub).

            Inclua esse veredito de completude junto com os achados do code review no mesmo comentário final — não são relatórios separados.
            PROMPT,
            $prNumber
        );

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
