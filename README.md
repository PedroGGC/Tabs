# Tabs - Social Blog Platform

![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?logo=mysql&logoColor=white)
![React](https://img.shields.io/badge/React-18%2B-61DAFB?logo=react&logoColor=black)
![Vite](https://img.shields.io/badge/Vite-5%2B-646CFF?logo=vite&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3%2B-38B2AC?logo=tailwind-css&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?logo=docker&logoColor=white)

Tabs é um projeto de blog social moderno construído com **PHP puro e MySQL** no backend, aliado a uma experiência de usuário aprimorada no frontend com **React, Vite e Tailwind CSS**. O projeto oferece autenticação baseada em sessão, perfis de usuário, postagens com formatação rica (incluindo efeitos textuais dinâmicos em React) e um sistema de comentários aninhados.

## Funcionalidades

- **Autenticação Segura:** Login e registro com hash de senhas (`bcrypt`) e proteção contra CSRF.
- **Gerenciamento de Postagens:** Criação, edição e exclusão de posts com suporte a upload de capas e slugs únicos.
- **Perfis de Usuário:** Páginas de perfil personalizáveis com upload de avatares.
- **Comentários e Engajamento:** Sistema de comentários aninhados e votação em posts.
- **Efeitos de Texto Dinâmicos:** Integração de componentes React (React Bits) para textos com efeitos visuais (Glitch, Shiny, Fuzzy, Gradient).
- **Interface Responsiva:** Estilização moderna utilizando Tailwind CSS integrado ao fluxo do Vite.
- **Docker Ready:** Ambiente de produção otimizado utilizando a imagem oficial do FrankenPHP.

## Tecnologias Utilizadas

### Backend
- **PHP 8.3+** (Sessões nativas, PDO para interações seguras com o banco de dados)
- **MySQL 8.0+**
- **FrankenPHP** (Servidor de aplicação moderno em Caddy - via Docker)

### Frontend
- **React 18** (Renderização de componentes e efeitos interativos injetados no PHP)
- **Vite** (Build tool e servidor de desenvolvimento frontend)
- **Tailwind CSS** (Framework CSS utilitário)
- **Vanilla JavaScript** (Interações DOM modulares, transições e sistema de notificações)

## Estrutura do Projeto

```text
blog-php/
├── auth/                 # Rotas e lógicas de autenticação (login, registro)
├── config/               # Configurações do sistema (ex: database.php)
├── database/             # Scripts SQL para inicialização do banco de dados
├── pages/                # Páginas principais (perfil, visualização e gerenciamento de posts)
├── public/               # Assets públicos (CSS compilado, JS vanilla, uploads de imagens gerados)
├── src/                  # Código-fonte principal (Ações PHP, Core Functions e Componentes React)
│   ├── actions/          # Endpoints de API para ações AJAX (comentários, votos, notificações, logout)
│   ├── components/       # Componentes React (efeitos visuais do react-bits)
│   ├── core/             # Funções utilitárias seguras (CSRF, sanitização) e de layout em PHP
│   └── main.jsx          # Ponto de entrada principal do React e Vite
├── Dockerfile            # Configuração da imagem de produção baseada em FrankenPHP
├── package.json          # Dependências do Node.js (Vite, React, Tailwind)
├── tailwind.config.js    # Configurações do Tailwind CSS
└── vite.config.js        # Configurações de compilação do Vite
```

## Instalação e Configuração

### Pré-requisitos
- PHP 8.3+
- MySQL ou MariaDB
- Node.js e NPM (para compilar o frontend)

### Passo 1: Clonar o Repositório
```bash
git clone <repository-url>
cd blog-php
```

### Passo 2: Configurar o Banco de Dados
1. Crie um banco de dados vazio no seu servidor MySQL.
2. Importe o esquema de tabelas:
   ```bash
   mysql -u root -p < database/blog.sql
   ```
3. Copie o arquivo de configuração de exemplo (se existir) ou crie o seu `config/database.php` baseado nas variáveis de ambiente suportadas.

As seguintes variáveis de ambiente (ou diretamente no arquivo) controlam a conexão:
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET`

### Passo 3: Configurar o Frontend
Instale as dependências do Node.js e construa os assets do Vite para a pasta de distribuição (usualmente `public/dist/` dependendo da configuração no `vite.config.js`):
```bash
npm install
npm run build
```

### Passo 4: Rodar o Servidor Local
Para desenvolvimento ágil, você pode utilizar o servidor embutido do PHP:
```bash
php -S localhost:8000
```
*(Ou utilize XAMPP/WAMP, colocando a pasta no seu `htdocs` ou `www`).*

### Rodando com Docker (Recomendado para Produção)
O projeto inclui um `Dockerfile` robusto baseado no FrankenPHP (Caddy Server com worker PHP embutido) com as permissões de upload ajustadas.
```bash
docker build -t tabs-blog .
docker run -p 80:80 tabs-blog
```
Acesse `http://localhost` no seu navegador.

## Segurança Aplicada

- **Prevenção a SQL Injection:** Uso rigoroso de Prepared Statements via PDO em todas as queries.
- **Prevenção a XSS (Cross-Site Scripting):** Tratamento de saída (Output escaping) usando a função estrita `e()` encapsulando `htmlspecialchars`.
- **Proteção CSRF:** Tokens de validação exigidos e validados através de `csrfToken()` e `verifyCsrfOrFail()` em todas as mutações de dados via POST.
- **Senhas Seguras:** Armazenamento seguro de senhas usando o algoritmo bcrypt robusto (`password_hash`).
- **Validação de Permissões e Rotas:** Controle rigoroso de sessão (`requireLogin()`) e verificações de autorização de ID de usuário antes de operações de modificação/deleção.

## Como Usar os Efeitos de Texto no Editor

A plataforma possui integração avançada através de parse customizado em PHP (`parsePostContent()`) com renderização delegada ao React (`main.jsx`). No editor de postagens, você pode aplicar efeitos visuais de alto impacto envolvendo seus textos:

- `""texto""` renderiza como **Gradient Text**
- `**texto**` renderiza como **Shiny Text**
- `&&texto&&` renderiza como **Fuzzy Text**
- `%%texto%%` renderiza como **Glitch Text**

