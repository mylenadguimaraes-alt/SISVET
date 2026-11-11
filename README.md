# 🐾 SISVET - Sistema Integrado de Saúde Veterinária

![Status](https://img.shields.io/badge/Status-Em_Desenvolvimento-yellow)
![PHP](https://img.shields.io/badge/PHP-Backend-777BB4?logo=php&logoColor=white)
![Supabase](https://img.shields.io/badge/Supabase-Database-3ECF8E?logo=supabase&logoColor=white)
![HTML/CSS](https://img.shields.io/badge/Frontend-HTML%20%7C%20CSS%20%7C%20JS-E34F26?logo=html5&logoColor=white)

## 📌 Sobre o Projeto

O **SISVET** é uma plataforma web robusta e escalável desenvolvida para revolucionar e otimizar a gestão de clínicas, hospitais veterinários e pet shops. O sistema centraliza e facilita o fluxo de informações, integrando toda a jornada de atendimento: desde a triagem na recepção, passando pelo prontuário nas mãos do veterinário, até a visão gerencial do dono da clínica e o acompanhamento do tutor do animal.

O objetivo central do SISVET é eliminar o uso de papel, evitar a perda de histórico clínico e dar total controle administrativo aos gestores através de um painel inteligente e intuitivo.

---

## 🚀 Funcionalidades Detalhadas

O sistema foi desenhado com arquitetura de **Múltiplos Perfis de Usuário**, cada um com permissões e visões específicas:

### 💼 Perfil Dono de Clínica / Administrador
* **Dashboard Gerencial (`index_4.html`):** Visão completa do faturamento, atendimentos diários e métricas de desempenho.
* **Gestão de Contratos e Clínicas:** Cadastro de filiais, gestão de planos de saúde veterinários e controle de parceiros.
* **Gestão de Equipe:** Adição e controle de permissões para veterinários e recepcionistas.

### 🩺 Perfil Veterinário
* **Prontuário Eletrônico:** Acesso ao histórico completo do pet e registro de novas consultas, exames e vacinas.
* **Agenda Médica:** Controle de consultas marcadas para o dia.

### 👩‍💻 Perfil Recepcionista
* **Triagem e Agendamento:** Controle do fluxo da sala de espera e marcação de novos atendimentos.
* **Cadastro Rápido:** Inserção de novos tutores e pets no sistema de forma ágil.

### 🐶 Perfil Tutor (Cliente)
* **Acompanhamento do Pet:** Visualização de vacinas pendentes, histórico de consultas e dados do(s) animal(is).

---

## 📂 Estrutura Completa de Diretórios

Abaixo está o mapeamento detalhado de toda a arquitetura de pastas e arquivos do sistema:

```text
SISVET/
│
├── bd_conexao/                      # ⚙️ Módulo de Backend e Integração com Banco de Dados
│   └── php/
│       ├── config.php               # (Sugerido) Variáveis globais e configurações
│       ├── conexao_supabase.php     # Configuração da API Rest e chaves de acesso ao Supabase
│       ├── login.php                # Script central de autenticação e controle de sessão (JWT/Tokens)
│       ├── cadastrar_tutor.php      # Lógica de inserção de dados do Tutor no DB
│       ├── cadastrar_recepcionista.php # Lógica de inserção da Recepcionista no DB
│       ├── cadastrar_dono.php       # Lógica de inserção do Gestor/Dono no DB
│       ├── gerenciar_clinicas.php   # Scripts de CRUD (Create, Read, Update, Delete) de clínicas
│       ├── gerenciar_contratos.php  # Scripts de controle de regras de negócio de contratos
│       └── gerenciar_atendimentos.php # Registro de consultas, exames e histórico clínico
│
├── incial/                          # 🌐 Módulo Front-end Institucional e Auxiliar
│   ├── css/                         # Folhas de estilo (Sass/CSS) globais
│   │   └── style.css                # Estilos base para páginas institucionais
│   ├── js/                          # Scripts auxiliares e de interatividade visual
│   │   └── main.js                  # Lógicas de front-end (validações, animações)
│   ├── img/                         # Diretório de Assets (Imagens)
│   │   ├── logo.png                 # Logotipo oficial do sistema
│   │   └── imagemcachorrogato.png   # Ilustrações e recursos visuais
│   ├── como-funciona.html           # Landing page informativa sobre as features do sistema
│   └── ajuda.html                   # Central de ajuda, tutoriais e FAQ para os usuários
│
├── Formulários e Interfaces (Raiz)  # 🖥️ Telas da Aplicação
│   ├── cadastro.html                # Tela principal de roteamento: "Qual seu perfil?"
│   ├── cadastrar_tutor.html         # UI do formulário de registro de cliente
│   ├── cadastrar_recepcionista.html # UI do formulário de registro de recepção
│   ├── cadastrar_dono.html          # UI do formulário de registro administrativo
│   ├── login.html                   # (Sugerido) Tela de login para acesso ao painel
│   └── index_4.html                 # Dashboard Principal do Veterinário / Painel de Controle
│
└── README.md                        # Documentação principal do projeto
```

---

## 🛠️ Stack Tecnológica

O SISVET foi construído focando em uma stack leve e acessível:

* **Frontend:**
  * **HTML5:** Estruturação semântica de todas as páginas e formulários.
  * **CSS3:** Estilização responsiva (Mobile First) para acesso via tablets e smartphones nas clínicas.
  * **JavaScript (Vanilla):** Manipulação da DOM, validações de formulário no lado do cliente e consumo de APIs via `fetch`.

* **Backend:**
  * **PHP:** Processamento de regras de negócio, rotas de API internas e comunicação segura com o banco.
  
* **Banco de Dados / BaaS:**
  * **Supabase (PostgreSQL):** Plataforma Backend-as-a-Service utilizada pelo seu robusto banco de dados relacional (PostgreSQL), garantindo velocidade, segurança e fácil gestão de dados em nuvem via API.

---

## ⚙️ Como Configurar e Executar Localmente

Siga o passo a passo abaixo para rodar o ambiente de desenvolvimento na sua máquina:

### 1. Pré-requisitos
* Um servidor web local com suporte a PHP (recomendado: [XAMPP](https://www.apachefriends.org/pt_br/index.html), WAMP ou Laragon).
* Conta ativa no [Supabase](https://supabase.com/) com o projeto e as tabelas já criadas.
* Navegador moderno atualizado.

### 2. Passo a Passo de Instalação

**Passo 1: Clonar o Repositório**
Abra o terminal na pasta `htdocs` (se usar XAMPP) ou `www` (se usar WAMP) e rode:
```bash
git clone https://github.com/seu-usuario/sisvet.git
```

**Passo 2: Configurar Variáveis do Supabase**
Navegue até a pasta `bd_conexao/php/`. Abra o arquivo de conexão com o banco (ex: `conexao_supabase.php`) e insira suas credenciais do projeto (essas informações ficam nas configurações de API do seu dashboard no Supabase):
```php
// Exemplo de configuração esperada
$supabaseUrl = 'https://SUA_URL_AQUI.supabase.co';
$supabaseAnonKey = 'SUA_CHAVE_ANON_AQUI';
```
*(Aviso: Nunca suba o arquivo com suas senhas reais para repositórios públicos como o GitHub. Utilize variáveis de ambiente sempre que possível).*

**Passo 3: Rodar a Aplicação**
Inicie os serviços do Apache no seu painel de controle do XAMPP/WAMP.
Em seguida, abra o navegador e acesse a URL local, geralmente:
`http://localhost/sisvet/`

---

## 📝 Licença e Autoria
**© 2026 MiguellSouza, mylenadguimaraes, Viniciust98, HyagON11, respectivos autores originais. Todos os direitos reservados.**
