---
name: code-review
description: Revisa um pull request e posta o resultado como UMA única revisão consolidada no GitHub (não múltiplos comentários soltos). Use quando o argumento for um número de PR.
---

# Code review (harness)

Você está revisando o pull request `$1` do repositório no diretório atual. O resultado
final deve ser **uma única submissão** de revisão no GitHub — nunca várias chamadas
separadas de `gh pr comment`/`gh pr review`.

## Passo a passo

1. Rode `gh pr view $1 --json state,isDraft,title,body,baseRefName,headRefOid` e `gh pr diff $1`
   pra entender o PR. Se o PR estiver fechado, for trivial (ex: só formatação, só
   dependências, gerado automaticamente) ou claramente não precisar de revisão, pare
   aqui e não poste nada.
2. Descubra os `CLAUDE.md` relevantes: o da raiz do repo (se existir) e os das pastas
   tocadas pelo diff. Leia-os — servem de guia do que o time considera importante, mas
   nem toda instrução deles é aplicável a uma revisão de PR.
3. Leia o diff (`gh pr diff $1`) linha por linha e procure problemas reais nas linhas
   **modificadas** pelo PR:
   - bugs de lógica, edge cases não tratados, condições de corrida
   - inconsistência entre frontend/backend (contratos de API, validação duplicada e
     divergente, timezone, formatos de data)
   - problemas de performance óbvios (N+1, query sem índice em loop, etc.)
   - violação explícita de alguma regra do CLAUDE.md
   - divergência com o histórico do arquivo (`git log -p`/`git blame` nas linhas
     tocadas), se isso indicar que a mudança contraria uma decisão anterior
4. Para cada candidato a problema, avalie sua própria confiança de 0 a 100 antes de
   incluir:
   - 0-24: possível falso positivo, questão pré-existente, ou nitpick que um revisor
     sênior não apontaria — descarte.
   - 25-49: pode ser real mas não foi possível confirmar — descarte, a menos que o
     impacto seja alto.
   - 50-79: real mas menor/raro na prática — inclua só se sobrarem poucos achados.
   - 80-100: real, verificado, com impacto direto em corretude ou nos padrões do
     CLAUDE.md — inclua.
   - Descarte sempre: o que lint/typecheck/CI pegam (imports, tipos, formatação),
     nitpicks de estilo, problemas em linhas que o PR não tocou, mudanças de
     comportamento claramente intencionais, e questões silenciadas explicitamente no
     código (ex: comentário de lint-ignore).
5. Se não sobrar nenhum achado com confiança >= 80 (ou >= 50 quando o PR for pequeno e
   os achados forem os únicos candidatos), poste uma revisão só de aprovação/observação
   dizendo que nenhum problema relevante foi encontrado — não deixe de postar nada.
6. Monte UMA revisão consolidada e submeta com uma única chamada `gh api`, method POST,
   endpoint `repos/{owner}/{repo}/pulls/$1/reviews`, body JSON contendo:
   - `commit_id`: o `headRefOid` do passo 1
   - `event`: `"COMMENT"`
   - `body`: um resumo curto (2-4 linhas) do que foi revisado e quantos pontos foram
     encontrados — sem emoji, sem citar qual ferramenta gerou a revisão
   - `comments`: um array com um item por achado, cada um `{"path": "...", "line": N,
     "body": "..."}` — comentário objetivo, explicando o quê e o porquê, citando o
     trecho relevante do CLAUDE.md quando aplicável
   Use `gh api -X POST repos/{owner}/{repo}/pulls/$1/reviews -f commit_id=... -f event=COMMENT -f body=... -f 'comments[][path]=...' ...`
   ou grave o JSON num arquivo temporário e use `gh api --input arquivo.json` — o
   importante é que seja **uma chamada só**, nunca um loop de `gh pr comment`/`gh pr
   review` por achado.

## Regras

- Nunca mencione o nome da ferramenta de IA usada para gerar a revisão em nenhum texto
  que vá para o GitHub (nem no resumo, nem nos comentários). Se precisar se referir a
  si mesmo, use algo genérico como "revisão automatizada".
- Não rode build, lint ou testes — isso já roda em CI separadamente.
- Não modifique arquivos, não faça commit, não faça push. Sua única saída é a revisão
  no GitHub.
- Prefira poucos comentários de alto sinal a muitos nitpicks.
- Se `$1` não for um número de PR, ou não houver PR aberto correspondente, pare e não
  poste nada.
