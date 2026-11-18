# Lista de leitura — LP VIII, etapas 1 a 3

Projeto de livros com PHP 8, PDO/MySQL, sessões e interface HTML/JavaScript. A Open Library é uma forma adicional de cadastrar livros. A entidade principal tem título, autores, ano de publicação, ISBN e outros campos.

## Como executar no XAMPP

1. Copie a pasta para `C:\xampp\htdocs\open-library-trabalho` e inicie Apache e MySQL.
2. Importe `database.sql` pelo phpMyAdmin. Ele cria as tabelas `usuarios` e `lista_leitura` e insere dois livros de exemplo. Se já existia uma instalação anterior, reimporte o arquivo para criar `usuarios` sem apagar os livros existentes.
3. Configure banco e contato da Open Library em `api/config.php` (ou use `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` e `OPEN_LIBRARY_USER_AGENT`). Substitua `grupo6@example.com` por um contato real.
4. Abra `http://localhost/open-library-trabalho/`, cadastre duas contas e faça login.
5. Para testar permissão de administrador, promova apenas a conta escolhida no phpMyAdmin: `UPDATE usuarios SET perfil = 'admin' WHERE email = 'admin@exemplo.com';`. Saia e entre novamente nessa conta para atualizar a sessão. Contas novas sempre começam com o perfil `usuario`.

O navegador e a API devem usar a mesma origem (`localhost`, protocolo e porta iguais). Fora do XAMPP local, sirva com HTTPS para proteger senha e cookie de sessão.

Se a busca online apresentar timeout, abra `https://openlibrary.org/search.json?q=percy%20jackson` no navegador da mesma máquina. Se também não abrir, confira a rede, VPN ou firewall: o PHP não conseguirá chegar à API. Enquanto isso, use **Adicionar livro manualmente** no painel; o cadastro e o MySQL continuam funcionando.

## Relação com os PDFs

| Etapa | Implementação |
|---|---|
| Aula 12: login e área protegida | `usuarios`, `password_hash`, `password_verify`, sessão, `session_regenerate_id`, mensagem genérica, `painel.php`, logout e perfil admin |
| Aula 13: API própria | `includes/api.php`, PDO, JSON, GET lista/ID, POST, PUT, DELETE, códigos 200/201/204/400/401/403/404/405/422 e coleção Postman |
| Aula 14: interface | `fetch`, formulário, lista, carregando/vazio/erro, edição, exclusão e redirecionamento ao login no 401 |

Os três PDFs fornecidos são das aulas 12, 13 e 14. Eles citam a aula 15 e o enunciado final, mas esses arquivos não estão nesta pasta. Portanto, os critérios específicos da etapa posterior ainda precisam ser conferidos quando o material estiver disponível.

## API de livros

Todas as rotas exigem sessão iniciada em `login.php` ou JWT enviado como `Authorization: Bearer <token>`. `DELETE` exige perfil `admin`. Respostas de erro têm `success: false` e `error`; erros de validação também trazem `errors`.

## Login JWT no Postman (Body raw)

Importe `postman/Open_Library_JWT.postman_collection.json` e ajuste `baseUrl`, `email` e `senha` nas variáveis da coleção. Use uma conta criada em `cadastro.php`.

Faça **POST** para `http://localhost/open-library-trabalho/api/login.php`. Em **Body → raw → JSON**, envie:

```json
{
  "email": "seu-email@exemplo.com",
  "senha": "sua-senha"
}
```

O cabeçalho deve ser `Content-Type: application/json`. O retorno 200 contém `access_token`, `token_type: "Bearer"`, `expires_in: 3600` e os dados públicos de `usuario`. A requisição de login da nova coleção salva automaticamente `access_token` na variável `token`.

Nas chamadas de livros, selecione **Authorization → Bearer Token** e informe `{{token}}`. O payload de cadastro/edição também vai em **Body → raw → JSON**. Não envie o token dentro do body. O token tem assinatura HS256 e inclui `sub` (ID), `nome`, `email`, `perfil`, `permissoes`, emissor (`iss`), audiência (`aud`), emissão (`iat`), início da validade (`nbf`), expiração (`exp`) e identificador único (`jti`). A senha e seu hash não fazem parte do JWT. O payload é legível, não criptografado.

### Permissões no JWT

| Permissão | Ação | Perfil usuario | Perfil admin |
|---|---|---|---|
| `livros:ler` | GET de livros e capas | Sim | Sim |
| `livros:criar` | POST de livros (manual ou busca) | Sim | Sim |
| `livros:editar` | PUT de livros | Sim | Sim |
| `livros:excluir` | DELETE de livros | Não | Sim |

Trecho dos novos claims de um administrador (o token também inclui os claims de validade):

```json
{
  "sub": "1",
  "nome": "Administrador",
  "email": "admin@exemplo.com",
  "perfil": "admin",
  "permissoes": ["livros:ler", "livros:criar", "livros:editar", "livros:excluir"]
}
```

As permissões são definidas pelo servidor a partir do perfil cadastrado. O body de login continua recebendo apenas `email` e `senha`; enviar um perfil ou permissões nesse body não concede acesso. A resposta também inclui `usuario.permissoes` para consulta.

A API exige a permissão no JWT **e** no perfil atual do banco. Uma redução de perfil bloqueia imediatamente as permissões removidas; uma promoção exige novo login para obter um token com as permissões adicionais. Tokens emitidos antes da inclusão desses claims recebem 401: faça login novamente. As coleções Postman validam os claims e salvam o payload decodificado em `userJwtPayload`, `adminJwtPayload` ou `jwtPayload` para inspecioná-lo.

O login JWT não inicia uma sessão autenticada por cookie. A interface web mantém o login por sessão. Se um Bearer for enviado, ele tem prioridade e precisa ser válido, mesmo havendo cookie. O logout da interface encerra apenas a sessão; tokens já emitidos continuam válidos até expirar (ou até trocar a chave/excluir o usuário).

Para uso local, uma chave aleatória é criada automaticamente fora da pasta pública, no diretório temporário do PHP. Se esse arquivo for removido ou o projeto mudar de caminho, faça login novamente. Em uma instalação permanente, configure `JWT_SECRET` no ambiente do servidor com pelo menos 32 bytes aleatórios e mantenha a mesma chave entre os processos. Não publique essa chave. Fora do ambiente local, use HTTPS.

Erros do login: `400` JSON inválido, `401` credenciais incorretas, `405` método diferente de POST, `415` Content-Type diferente de JSON, `422` campos ausentes/inválidos e `500` falha interna. Tokens inválidos ou expirados recebem `401`; um usuário comum tentando excluir recebe `403`.

A implementação usa assinatura e validação de claims conforme [RFC 7519](https://www.rfc-editor.org/rfc/rfc7519) e restringe o algoritmo aceito conforme [RFC 8725](https://www.rfc-editor.org/rfc/rfc8725).

| Método | URL | Corpo | Sucesso | Erros principais |
|---|---|---|---|---|
| GET | `api/books.php` | — | 200, lista | 401 |
| GET | `api/books.php?id=1` | — | 200, livro | 400, 401, 404 |
| POST | `api/books.php` | `{"title":"...","authors":"...","first_publish_year":2020,"isbn":"..."}` | 201, criado | 400, 401, 422 |
| POST | `api/books.php` | `{"query":"Dom Casmurro"}` | 201, criado; 200, existente | 401, 404, 422, 502 |
| PUT | `api/books.php?id=1` | título, autores, ano e ISBN em JSON | 200, atualizado | 400, 401, 404, 422 |
| DELETE | `api/books.php?id=1` | — | 204, sem corpo | 400, 401, 403, 404 |

O `GET api/cover.php?id=...` entrega capas pela Open Library para usuários logados. O JavaScript usa `textContent` para os dados dos livros. O banco é acessado apenas no PHP com consultas preparadas.

## Postman

As duas coleções incluem exemplos salvos de resposta em cada requisição, instruções em **Description** e parâmetros de ID na aba **Params**. Consulte [postman/EXEMPLOS.md](postman/EXEMPLOS.md) para o passo a passo de exclusão e exemplos de todas as chamadas. Na coleção principal, **13** demonstra o 403 para usuário comum; **17** realiza a exclusão usando `adminToken`. O ID vai na URL (`?id={{bookId}}`), sem body. Uma exclusão bem-sucedida retorna **204**, com corpo vazio.

Importe `postman/Open_Library_Trabalho.postman_collection.json`, a coleção principal atualizada para JWT. Cadastre uma conta comum e uma conta administradora com a mesma senha, promova a segunda no banco e ajuste as variáveis `userEmail`, `adminEmail`, `password`, `baseUrl` e `openLibraryUserAgent`. Execute a coleção na ordem: ela cobre chamada pública, login JWT, CRUD, 400, 401, 403, 404, 405, 422 e 204. Os logins recebem **Body → raw → JSON** e salvam automaticamente `userToken`, `adminToken` e `token`. As chamadas protegidas usam o Bearer do perfil correspondente. Os cookies estão desativados nas requisições da coleção; os casos sem token e com token inválido verificam o 401. A consulta externa à Open Library requer acesso à internet. A exclusão remove o livro manual criado na própria execução.

## Entrega e apresentação

- Entregar a pasta compactada ou repositório, `database.sql`, coleção Postman e este README.
- Capturar prints das telas de cadastro, login, painel, erros e operações no Postman após executar localmente; os PDFs exigem imagens no README da Etapa 3.
- Demonstrar no phpMyAdmin que `senha_hash` não guarda a senha original e que os livros são persistidos.
- Demonstrar os status 401, 403, 404, 422, 201 e 204, além de logout e retorno à página protegida.

As capturas de tela e a execução integrada com Apache/MySQL dependem da instalação local e das contas reais usadas na apresentação.
