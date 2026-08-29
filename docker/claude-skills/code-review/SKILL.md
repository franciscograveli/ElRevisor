---
name: code-review
description: Revisa um pull request e posta o resultado como UMA única revisão consolidada no GitHub (não múltiplos comentários soltos), com label de veredito. Use quando o argumento for um número de PR.
---

# Code review (harness)

Você está revisando o pull request `$1` do repositório no diretório atual. O resultado
final deve ser **uma única submissão** de revisão no GitHub — nunca várias chamadas
separadas de `gh pr comment`/`gh pr review`.

## Passo a passo

1. Rode `gh pr view $1 --json state,isDraft,title,body,baseRefName,headRefOid,comments` e
   `gh pr diff $1` pra entender o PR. Se o PR estiver fechado, for trivial (ex: só
   formatação, só dependências, gerado automaticamente) ou claramente não precisar de
   revisão, pare aqui e não poste nada.
2. Leia a conversa já existente no PR: comentários gerais
   (`gh api repos/{owner}/{repo}/issues/$1/comments`) e comentários inline de reviews
   anteriores (`gh api repos/{owner}/{repo}/pulls/$1/comments`). Um apontamento
   anterior (de humano ou de revisão automática) só sai da sua lista se **um dos dois**
   for verdade:
   - o commit atual (`headRefOid`) já corrige o problema — confirme lendo o código
     atual, não assuma pelo fato de já ter passado tempo; ou
   - alguém respondeu explicitamente aceitando/descartando aquilo na conversa (ex:
     "fora de escopo", "é intencional", "não vamos corrigir agora").
   **"Já foi apontado antes" não é motivo pra descartar.** Se um apontamento anterior
   segue sem commit novo e sem resposta desde então, trate-o como um candidato de alta
   prioridade: releia o código atual especificamente pra confirmar se ele ainda
   procede, e se sim, ele PRECISA aparecer nos seus achados — não é duplicata, é um
   bug real que continua sem solução. O objetivo de ler a conversa é evitar barulho
   redundante sobre o que já foi resolvido, nunca suprimir um bug que ainda existe.
   Aproveite também pra entender decisões de design já discutidas, pra não sinalizar
   como problema algo que foi debatido e resolvido de propósito.
3. Se o título ou corpo do PR referenciar uma task/issue (ex: "Closes #12", "Fixes
   #12", link de issue do GitHub, ou um ID de task tipo `ABC-123` mencionado no
   texto), busque essa referência (`gh issue view <n>` quando for issue do GitHub) e
   cruze a descrição/critério de aceite dela com o que o diff realmente faz. Se o PR
   afirma resolver a task mas o diff não cobre o que ela pede (ou resolve só
   parcialmente), isso é um achado de alta confiança.
4. Descubra os `CLAUDE.md` relevantes: o da raiz do repo (se existir) e os das pastas
   tocadas pelo diff. Leia-os — servem de guia do que o time considera importante, mas
   nem toda instrução deles é aplicável a uma revisão de PR.
5. Leia o diff (`gh pr diff $1`) linha por linha e procure problemas reais nas linhas
   **modificadas** pelo PR:
   - bugs de lógica, edge cases não tratados, condições de corrida, regressões
   - inconsistência entre frontend/backend (contratos de API, validação duplicada e
     divergente, timezone, formatos de data)
   - problemas de performance óbvios (N+1, query sem índice em loop, etc.)
   - violação explícita de alguma regra do CLAUDE.md
   - divergência com o histórico do arquivo (`git log -p`/`git blame` nas linhas
     tocadas), se isso indicar que a mudança contraria uma decisão anterior
   - ausência de teste pra um comportamento novo/alterado que é claramente arriscado
     (não exija teste pra tudo, só onde a falta dele é o próprio risco)
6. Para cada candidato a problema, avalie sua própria confiança de 0 a 100 antes de
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
     comportamento claramente intencionais, questões silenciadas explicitamente no
     código (ex: comentário de lint-ignore), e apontamentos anteriores que o passo 2
     confirmou como corrigidos ou explicitamente aceitos — nunca descarte um
     apontamento anterior só por já ter sido mencionado antes.
7. Se não sobrar nenhum achado com confiança >= 80 (ou >= 50 quando o PR for pequeno e
   os achados forem os únicos candidatos), a revisão é só de aprovação/observação,
   registrando que nada relevante foi encontrado — não deixe de postar nada.
8. Decida o veredito: **"rejected"** se a revisão tem pelo menos um comentário de bug
   real/regressão/edge case não tratado/task não atendida (não conta nitpick de
   estilo nem sugestão opcional); **"approved"** caso contrário.
9. Monte UMA revisão consolidada e submeta com uma única chamada `gh api`, method POST,
   endpoint `repos/{owner}/{repo}/pulls/$1/reviews`, body JSON contendo:
   - `commit_id`: o `headRefOid` do passo 1
   - `event`: `"COMMENT"`
   - `body`: um resumo curto (2-4 linhas) do que foi revisado e do veredito, em
     linguagem humana, direta e objetiva — sem tom robótico, sem emoji, sem citar
     qual ferramenta gerou a revisão
   - `comments`: um array com um item por achado, cada um `{"path": "...", "line": N,
     "body": "..."}` — comentário objetivo, explicando o quê e o porquê, citando o
     trecho relevante do CLAUDE.md ou da task quando aplicável
   Use `gh api -X POST repos/{owner}/{repo}/pulls/$1/reviews -f commit_id=... -f event=COMMENT -f body=... -f 'comments[][path]=...' ...`
   ou grave o JSON num arquivo temporário e use `gh api --input arquivo.json` — o
   importante é que seja **uma chamada só**, nunca um loop de `gh pr comment`/`gh pr
   review` por achado.
10. Aplique a label do veredito: garanta que ela existe
    (`gh label create approved --color 2ea44f --force` e
    `gh label create rejected --color d73a4a --force`, ambos idempotentes), remova a
    label oposta se estiver presente (`gh pr edit $1 --remove-label rejected` /
    `--remove-label approved`, ignore erro se a label não estava lá) e adicione a
    correta (`gh pr edit $1 --add-label approved` ou `--add-label rejected`).

## Regras

- Nunca mencione o nome da ferramenta de IA usada para gerar a revisão em nenhum texto
  que vá para o GitHub (nem no resumo, nem nos comentários). Se precisar se referir a
  si mesmo, use algo genérico como "revisão automatizada".
- Escreva como um revisor sênior humano escreveria: direto, claro, sem enrolação, sem
  jargão de IA ("Vale ressaltar que...", "É importante notar que..."). Vá direto ao
  ponto: o que está errado, onde, e por quê importa.
- Economize tokens sempre que possível — não releia arquivos inteiros sem necessidade,
  não repita no comentário final o que já foi raciocinado internamente, não escreva
  parágrafos longos quando uma frase resolve. Mas essa economia é sobre prolixidade,
  nunca sobre rigor: não pule a análise de bugs, regressões, edge cases, testes ou
  consistência com a task/CLAUDE.md pra "gastar menos". Se um trecho do diff exige
  mais leitura de contexto pra confirmar um bug real, leia.
- Não rode build, lint ou testes — isso já roda em CI separadamente.
- Não modifique arquivos, não faça commit, não faça push. Sua única saída é a revisão
  e a label no GitHub.
- Prefira poucos comentários de alto sinal a muitos nitpicks.
- Se `$1` não for um número de PR, ou não houver PR aberto correspondente, pare e não
  poste nada.
