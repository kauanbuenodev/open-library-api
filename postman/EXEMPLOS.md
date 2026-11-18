# Exemplos de uso no Postman

Os exemplos salvos em cada requisição são ilustrativos: expanda a seta ao lado dela para ver as respostas de sucesso e erro. Os IDs e JWTs dos exemplos não são credenciais nem registros reais.

## Como excluir um livro na coleção principal

1. Execute **04 - Listar livros** após o login comum. Copie o `id` do livro que quer remover (não é o título nem o ISBN).
2. Nas variáveis da coleção, defina `bookId` com esse ID. O cadastro **05** também preenche `bookId` automaticamente.
3. Configure `adminEmail` e `password` com a conta administradora e execute **16 - Login JWT administrador**. O Postman salva `adminToken`.
4. Abra **17 - Administrador exclui**. Em **Params**, confira `id = {{bookId}}`. A aba Authorization já usa o Bearer `{{adminToken}}`.
5. Envie o DELETE sem body. **204 No Content** significa que o livro foi excluído; o corpo vazio é esperado. Confirme pela listagem.

Exemplo ilustrativo para o livro de ID 123 (substitua pelo ID real):

```http
DELETE http://localhost/open-library-trabalho/api/books.php?id=123
Authorization: Bearer <JWT real do administrador>
```

A chamada **13** é um teste com token comum: ela deve retornar **403**. Use **17** para excluir. **401** pede novo login; **403** indica falta de permissão; **404** indica ID inexistente ou já excluído. O ID fica na URL, não no body.

Na coleção simplificada, faça **01 - Login** com email/senha de admin e use **06 - Excluir livro**, com `bookId` definido.

## Exemplos de todas as requisições

### LP VIII - Livros - JWT e JSON raw

#### 00 - Open Library pública (200)

Consulta pública da Open Library. Não precisa de JWT. Requer internet.

**Requisição:** `GET https://openlibrary.org/search.json?q=dom%20casmurro&fields=key,title,author_name,first_publish_year,isbn,cover_i&limit=1`

**Body:** none (sem corpo).

**Authorization:** No Auth.

**Respostas de exemplo:** 200. Expanda a requisição na lista para abri-las.

#### 01 - Sem login: GET protegido (401)

Teste de proteção: envie sem Authorization e sem cookies. O retorno esperado é 401. Essa requisição limpa as variáveis para iniciar uma nova execução.

**Requisição:** `GET {{baseUrl}}/api/books.php`

**Body:** none (sem corpo).

**Authorization:** No Auth.

**Respostas de exemplo:** 401. Expanda a requisição na lista para abri-las.

#### 02 - Login JWT usuário comum (200)

Configure userEmail e password com uma conta de perfil usuario. Selecione Body > raw > JSON. O login salva userToken, token e userJwtPayload automaticamente.

**Requisição:** `POST {{baseUrl}}/api/login.php`

**Body > raw > JSON:**
```json
{
  "email": "{{userEmail}}",
  "senha": "{{password}}"
}
```

**Authorization:** No Auth.

**Respostas de exemplo:** 200. Expanda a requisição na lista para abri-las.

#### 03 - Buscar na Open Library e salvar (201 ou 200)

Envie query em Body > raw > JSON. O servidor pesquisa na Open Library e salva o resultado. Retorna 201 se criou ou 200 se o livro já existia. Requer userToken e internet.

**Requisição:** `POST {{baseUrl}}/api/books.php`

**Body > raw > JSON:**
```json
{
  "query": "Frankenstein Mary Shelley"
}
```

**Authorization > Bearer Token:** `{{userToken}}`.

**Respostas de exemplo:** 201, 200. Expanda a requisição na lista para abri-las.

#### 04 - Listar livros (200)

Envie GET com userToken. Na resposta, copie data[n].id do livro desejado para a variável bookId da coleção. Esse número será usado em consultar, editar e excluir.

**Requisição:** `GET {{baseUrl}}/api/books.php`

**Body:** none (sem corpo).

**Authorization > Bearer Token:** `{{userToken}}`.

**Respostas de exemplo:** 200. Expanda a requisição na lista para abri-las.

#### 05 - Cadastrar livro manual (201)

Preencha title, authors, first_publish_year e isbn em Body > raw > JSON. O retorno 201 contém data.id. O teste salva esse número em bookId para as próximas chamadas.

**Requisição:** `POST {{baseUrl}}/api/books.php`

**Body > raw > JSON:**
```json
{
  "title": "Livro de teste Postman",
  "authors": "Grupo 6",
  "first_publish_year": 2020,
  "isbn": "1234567890"
}
```

**Authorization > Bearer Token:** `{{userToken}}`.

**Respostas de exemplo:** 201. Expanda a requisição na lista para abri-las.

#### 06 - Buscar livro por ID (200)

Em Params, id usa {{bookId}}. Execute o cadastro antes ou preencha bookId com o id de um livro retornado pela listagem. Exemplo ilustrativo: id=123. Não usa body.

**Requisição:** `GET {{baseUrl}}/api/books.php?id={{bookId}}`

**Body:** none (sem corpo).

**URL ilustrativa:** `http://localhost/open-library-trabalho/api/books.php?id=123`. Substitua 123 pelo ID real; não execute esse ID apenas por estar no exemplo.

**Authorization > Bearer Token:** `{{userToken}}`.

**Respostas de exemplo:** 200. Expanda a requisição na lista para abri-las.

#### 07 - Atualizar livro (200)

Em Params, informe o id do livro. Em Body > raw > JSON, preencha os novos dados. Exemplo: id=123, title="Livro de teste editado". Requer userToken.

**Requisição:** `PUT {{baseUrl}}/api/books.php?id={{bookId}}`

**Body > raw > JSON:**
```json
{
  "title": "Livro de teste editado",
  "authors": "Grupo 6",
  "first_publish_year": 2021,
  "isbn": "1234567890"
}
```

**URL ilustrativa:** `http://localhost/open-library-trabalho/api/books.php?id=123`. Substitua 123 pelo ID real; não execute esse ID apenas por estar no exemplo.

**Authorization > Bearer Token:** `{{userToken}}`.

**Respostas de exemplo:** 200. Expanda a requisição na lista para abri-las.

#### 08 - PUT com dados inválidos (422)

Exemplo intencional de erro: title e authors vazios. Use um bookId válido e userToken. Retorna 422 com os campos inválidos.

**Requisição:** `PUT {{baseUrl}}/api/books.php?id={{bookId}}`

**Body > raw > JSON:**
```json
{
  "title": "",
  "authors": ""
}
```

**URL ilustrativa:** `http://localhost/open-library-trabalho/api/books.php?id=123`. Substitua 123 pelo ID real; não execute esse ID apenas por estar no exemplo.

**Authorization > Bearer Token:** `{{userToken}}`.

**Respostas de exemplo:** 422. Expanda a requisição na lista para abri-las.

#### 09 - PUT com ID inexistente (404)

Exemplo intencional de erro: atualiza id=999999999, assumindo que não exista. O body contém dados válidos. Retorna 404.

**Requisição:** `PUT {{baseUrl}}/api/books.php?id=999999999`

**Body > raw > JSON:**
```json
{
  "title": "Valid",
  "authors": "Author",
  "first_publish_year": 2020,
  "isbn": ""
}
```

**URL ilustrativa:** `http://localhost/open-library-trabalho/api/books.php?id=999999999`. Substitua 123 pelo ID real; não execute esse ID apenas por estar no exemplo.

**Authorization > Bearer Token:** `{{userToken}}`.

**Respostas de exemplo:** 404. Expanda a requisição na lista para abri-las.

#### 10 - Validação incorreta (422)

Exemplo intencional de erro de cadastro: title e authors vazios. O servidor retorna 422 sem cadastrar um livro.

**Requisição:** `POST {{baseUrl}}/api/books.php`

**Body > raw > JSON:**
```json
{
  "title": "",
  "authors": ""
}
```

**Authorization > Bearer Token:** `{{userToken}}`.

**Respostas de exemplo:** 422. Expanda a requisição na lista para abri-las.

#### 11 - JSON malformado (400)

Exemplo intencional de erro: o body raw não é um JSON válido. Não corrija esse body se quiser demonstrar o retorno 400.

**Requisição:** `POST {{baseUrl}}/api/books.php`

**Body > raw > JSON:**
```json
{invalid json
```

**Authorization > Bearer Token:** `{{userToken}}`.

**Respostas de exemplo:** 400. Expanda a requisição na lista para abri-las.

#### 12 - ID inexistente (404)

Consulta um ID de exemplo inexistente: 999999999. Retorna 404 se não houver um livro com esse ID. Não usa body.

**Requisição:** `GET {{baseUrl}}/api/books.php?id=999999999`

**Body:** none (sem corpo).

**URL ilustrativa:** `http://localhost/open-library-trabalho/api/books.php?id=999999999`. Substitua 123 pelo ID real; não execute esse ID apenas por estar no exemplo.

**Authorization > Bearer Token:** `{{userToken}}`.

**Respostas de exemplo:** 404. Expanda a requisição na lista para abri-las.

#### 13 - Usuário comum não pode excluir (403)

Demonstração de permissão: esta chamada usa userToken, portanto o usuário comum recebe 403 e o livro NÃO é excluído. Para realmente excluir, use a requisição 17 com adminToken.

**Requisição:** `DELETE {{baseUrl}}/api/books.php?id={{bookId}}`

**Body:** none (sem corpo).

**URL ilustrativa:** `http://localhost/open-library-trabalho/api/books.php?id=123`. Substitua 123 pelo ID real; não execute esse ID apenas por estar no exemplo.

**Authorization > Bearer Token:** `{{userToken}}`.

**Respostas de exemplo:** 403. Expanda a requisição na lista para abri-las.

#### 14 - JWT inválido (401)

Demonstração de JWT inválido: Authorization contém Bearer jwt-invalido. A API retorna 401 e não acessa os livros.

**Requisição:** `GET {{baseUrl}}/api/books.php`

**Body:** none (sem corpo).

**Authorization > Bearer Token:** `jwt-invalido`.

**Respostas de exemplo:** 401. Expanda a requisição na lista para abri-las.

#### 15 - Requisição sem JWT (401)

Demonstração sem autenticação: Authorization está como No Auth e cookies estão desativados. A resposta esperada é 401.

**Requisição:** `GET {{baseUrl}}/api/books.php`

**Body:** none (sem corpo).

**Authorization:** No Auth.

**Respostas de exemplo:** 401. Expanda a requisição na lista para abri-las.

#### 16 - Login JWT administrador (200)

Configure adminEmail e password com uma conta já cadastrada e com perfil admin no banco. Faça login novamente após promover a conta. O teste salva adminToken, token e adminJwtPayload.

**Requisição:** `POST {{baseUrl}}/api/login.php`

**Body > raw > JSON:**
```json
{
  "email": "{{adminEmail}}",
  "senha": "{{password}}"
}
```

**Authorization:** No Auth.

**Respostas de exemplo:** 200. Expanda a requisição na lista para abri-las.

#### 17 - Administrador exclui (204)

EXCLUSÃO REAL: 1. Execute 04 para listar e escolha o id, ou use o bookId salvo por 05. 2. Defina bookId nas variáveis da coleção. 3. Execute 16 para obter adminToken. 4. Nesta chamada DELETE, confira Params > id = {{bookId}}. 5. Clique Send. Não envie body. Sucesso: 204 com corpo vazio. Repetir a exclusão do mesmo ID retorna 404.

**Requisição:** `DELETE {{baseUrl}}/api/books.php?id={{bookId}}`

**Body:** none (sem corpo).

**URL ilustrativa:** `http://localhost/open-library-trabalho/api/books.php?id=123`. Substitua 123 pelo ID real; não execute esse ID apenas por estar no exemplo.

**Authorization > Bearer Token:** `{{adminToken}}`.

**Respostas de exemplo:** 204, 403, 404. Expanda a requisição na lista para abri-las.

#### 18 - DELETE com ID inexistente (404)

Exemplo de exclusão de registro inexistente. Usa adminToken e id=999999999, assumindo que não exista. Retorna 404. Não usa body.

**Requisição:** `DELETE {{baseUrl}}/api/books.php?id=999999999`

**Body:** none (sem corpo).

**URL ilustrativa:** `http://localhost/open-library-trabalho/api/books.php?id=999999999`. Substitua 123 pelo ID real; não execute esse ID apenas por estar no exemplo.

**Authorization > Bearer Token:** `{{adminToken}}`.

**Respostas de exemplo:** 404. Expanda a requisição na lista para abri-las.

#### 19 - Método não permitido (405)

Exemplo intencional de método não permitido: PATCH. A API aceita GET, POST, PUT e DELETE. Use adminToken válido para receber 405.

**Requisição:** `PATCH {{baseUrl}}/api/books.php`

**Body:** none (sem corpo).

**Authorization > Bearer Token:** `{{adminToken}}`.

**Respostas de exemplo:** 405. Expanda a requisição na lista para abri-las.

### Open Library - JWT e Body raw

#### 01 - Login JWT (200)

Configure email e senha nas variáveis da coleção. Envie o login com Body > raw > JSON. O teste salva token e jwtPayload. Para excluir, faça esse login com uma conta admin.

**Requisição:** `POST {{baseUrl}}/api/login.php`

**Body > raw > JSON:**
```json
{
  "email": "{{email}}",
  "senha": "{{senha}}"
}
```

**Authorization:** No Auth.

**Respostas de exemplo:** 200, 200. Expanda a requisição na lista para abri-las.

#### 02 - Listar livros com Bearer (200)

Lista os livros com Bearer {{token}}. Copie o id do livro desejado para a variável bookId para consultá-lo, editá-lo ou excluí-lo.

**Requisição:** `GET {{baseUrl}}/api/books.php`

**Body:** none (sem corpo).

**Authorization > Bearer Token:** `{{token}}`.

**Respostas de exemplo:** 200. Expanda a requisição na lista para abri-las.

#### 03 - Cadastrar livro com raw JSON (201)

Cadastre um livro com Body > raw > JSON. O teste salva data.id em bookId. Esse ID identifica o livro nas próximas requisições.

**Requisição:** `POST {{baseUrl}}/api/books.php`

**Body > raw > JSON:**
```json
{
  "title": "Exemplo JWT {{$timestamp}}",
  "authors": "Autor de exemplo",
  "first_publish_year": 2024,
  "isbn": "9781234567897"
}
```

**Authorization > Bearer Token:** `{{token}}`.

**Respostas de exemplo:** 201. Expanda a requisição na lista para abri-las.

#### 04 - Consultar livro (200)

Em Params, id usa {{bookId}}. Preencha essa variável com um id retornado pela listagem ou execute o cadastro primeiro. Exemplo ilustrativo: 123. Não usa body.

**Requisição:** `GET {{baseUrl}}/api/books.php?id={{bookId}}`

**Body:** none (sem corpo).

**URL ilustrativa:** `http://localhost/open-library-trabalho/api/books.php?id=123`. Substitua 123 pelo ID real; não execute esse ID apenas por estar no exemplo.

**Authorization > Bearer Token:** `{{token}}`.

**Respostas de exemplo:** 200. Expanda a requisição na lista para abri-las.

#### 05 - Editar livro com raw JSON (200)

Use bookId em Params > id e envie os novos dados em Body > raw > JSON. Os campos title e authors são obrigatórios. O retorno 200 contém o livro atualizado.

**Requisição:** `PUT {{baseUrl}}/api/books.php?id={{bookId}}`

**Body > raw > JSON:**
```json
{
  "title": "Exemplo JWT editado",
  "authors": "Autor de exemplo",
  "first_publish_year": 2025,
  "isbn": "9781234567897"
}
```

**URL ilustrativa:** `http://localhost/open-library-trabalho/api/books.php?id=123`. Substitua 123 pelo ID real; não execute esse ID apenas por estar no exemplo.

**Authorization > Bearer Token:** `{{token}}`.

**Respostas de exemplo:** 200. Expanda a requisição na lista para abri-las.

#### 06 - Excluir livro (204 admin / 403 usuário)

EXCLUSÃO REAL: 1. Faça login 01 com uma conta admin (ajuste email e senha nas variáveis). 2. Liste os livros em 02 e copie o id desejado para bookId. 3. Confira Params > id = {{bookId}} nesta chamada. 4. Clique Send. Não envie body. Retorna 204 vazio se excluiu, 403 se seu token não tem permissão ou 404 se o ID não existe.

**Requisição:** `DELETE {{baseUrl}}/api/books.php?id={{bookId}}`

**Body:** none (sem corpo).

**URL ilustrativa:** `http://localhost/open-library-trabalho/api/books.php?id=123`. Substitua 123 pelo ID real; não execute esse ID apenas por estar no exemplo.

**Authorization > Bearer Token:** `{{token}}`.

**Respostas de exemplo:** 204, 403, 404. Expanda a requisição na lista para abri-las.
