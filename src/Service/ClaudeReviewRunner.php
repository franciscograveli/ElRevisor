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

            Depois do code review acima, faça também uma checagem separada de completude — isso é tão importante quanto achar bugs, não pule. Em ambas as partes, leia o código ao redor das mudanças quando precisar de contexto — não se limite ao diff isolado; abrir os arquivos tocados pra entender call-sites, telas irmãs, ou o resto de uma fórmula é exigido, não opcional. Não invente achados: só reporte o que você confirmou lendo o código, sempre com arquivo e linha.

            ## 1. Completude da issue

            1. Ache a issue que esta PR fecha (procure "Closes #N"/"Fecha #N" no corpo da PR #%1$d via `gh pr view %1$d --json body`; se não achar nenhuma referência, pule o resto desta seção e diga isso no relatório).
            2. Leia o corpo COMPLETO dessa issue (`gh issue view <N> --json body`) e liste todas as histórias de usuário/critérios de aceite reais que ela descreve — não resuma de memória.
            3. Para CADA história, verifique com evidência concreta no código (grep, não achismo) se ela foi implementada nesta PR. "A PR menciona isso na descrição" não é evidência — evidência é achar o código que faz a coisa acontecer.
            4. Histórias que dependem de outro trabalho ainda não mergeado não contam como gap desta PR, mas precisam ser citadas como tal, não omitidas silenciosamente.
            5. Se a issue já for uma fatia com escopo próprio de um trabalho maior (ex: uma sub-issue tipo "[Parte 1/N]" referenciando uma issue "épico" pai), valide contra o escopo da PRÓPRIA issue fechada — nunca contra o trabalho maior inteiro.

            Termine com um veredito explícito, em negrito: **✅ COMPLETO** (cite qual trecho de código resolve cada história) ou **🟡 PARCIAL** (liste exatamente o que falta). Nunca gere um terceiro veredito ambíguo, e nunca arredonde pra COMPLETO por não ter certeza — se não conseguir confirmar alguma história com evidência real, isso é PARCIAL (ou pelo menos diga explicitamente o que não conseguiu confirmar em vez de assumir que está ok).

            ## 2. Ações — só se o veredito for PARCIAL, ou se o code review achou pelo menos um bug real

            Não pergunte permissão, é o comportamento padrão. NUNCA mergeie, aprove ou feche nada — só comente e, se der, mova o card.

            a) Comente na issue (`gh issue comment <N> --body "..."`) explicando com clareza o que falta ou está quebrado, mencionando a PR #%1$d. Se PARCIAL e a PR usa "Closes #N", recomende trocar para "Parte de #N" (sem a palavra-chave de fechamento do GitHub) — a issue não deveria fechar sozinha enquanto não estiver completa.

            b) Tente mover o card dessa issue pra uma coluna de "encontrado problema" no board do projeto — mas o board de cada repositório é diferente, então DESCUBRA a estrutura antes de agir, nunca assuma nomes/IDs fixos:
               - Rode `gh project list --owner <dono-do-repo> --format json` pra listar os projetos do dono do repositório (pegue `<dono-do-repo>` de `gh repo view --json owner -q .owner.login`).
               - Pra cada projeto candidato, rode a query GraphQL de campos (`fields(first: 20) { nodes { ... on ProjectV2SingleSelectField { id name options { id name } } } }`) e procure um campo cujo nome pareça de status (ex: "Status", "Stage") com uma opção cujo nome pareça indicar problema/correção pendente (ex: contém "waiting", "fix", "blocked", "review" — seja flexível, times nomeiam diferente).
               - Se achar um campo e opção assim, e a issue estiver de fato nesse projeto (`issue(number: <N>) { projectItems(first: 5) { nodes { id project { number } } } }`), mova o item pra essa opção via `updateProjectV2ItemFieldValue`.
               - Se não achar nenhum board, campo ou opção que sirva com confiança, NÃO invente — pule esta parte e diga no relatório final que não foi possível localizar automaticamente, sem erro nem ação incorreta.

            Termine o relatório final confirmando o que foi feito: comentário postado (link) e card movido (ou motivo de não ter movido).
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
