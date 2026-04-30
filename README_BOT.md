# WhatsApp AI Bot

Bot WhatsApp com IA (Claude, Gemini, GPT) para estabelecimentos — Dúvidas, Pedidos e Agendamentos.

## Instalação Rápida

### 1. Configurar o banco de dados

Acesse `http://seusite.com/install.php` no navegador e siga o assistente de instalação.

### 2. Instalar dependências do bot (Node.js)

```bash
cd bot
npm install
```

### 3. Configurar variáveis de ambiente

O instalador cria o `.env` automaticamente. Se necessário, edite:

```bash
cp .env.example .env
# Edite o .env com suas configurações
```

### 4. Iniciar o bot

**Com PM2 (recomendado para produção):**
```bash
cd bot
npm install -g pm2
pm2 start pm2.config.js
pm2 save
pm2 startup
```

**Ou direto pelo painel:**
Acesse o Painel Admin → Configurações do Bot → clique em **Iniciar**

### 5. Conectar o WhatsApp

1. No painel, vá em **Configurações do Bot**
2. O código de emparelhamento será gerado automaticamente
3. No seu celular: WhatsApp → Dispositivos Conectados → Conectar dispositivo → Conectar com número de telefone
4. Digite o código exibido no painel

---

## Painéis

| Painel | URL | Para quem |
|--------|-----|-----------|
| Admin | `/admin/login.php` | Você (dono do sistema) |
| Cliente | `/client/login.php` | Seu cliente (dono do negócio) |

### Painel Admin tem acesso completo:
- Configurar bot, IAs e chaves de API
- Ver todas as conversas, pedidos e agendamentos
- Gerenciar clientes
- Ver logs do sistema

### Painel Cliente tem acesso limitado:
- Definir nome do bot
- Configurar base de conhecimento (texto ou URL)
- Cadastrar produtos e fretes
- Cadastrar serviços e horários
- Ver agenda e pedidos

---

## Modos de Operação

Configure em **Configurações do Bot → Funcionalidades Ativas**:

- **❓ Dúvidas** — Bot responde perguntas com base no conhecimento cadastrado
- **🛍️ Pedidos** — Bot recebe pedidos, calcula frete e envia para o dono
- **📅 Agendamentos** — Bot verifica disponibilidade e faz agendamentos

---

## IAs Disponíveis

| IA | Provedor | Modelo padrão |
|----|----------|---------------|
| Claude | Anthropic | claude-3-5-haiku |
| Gemini | Google | gemini-1.5-flash |
| GPT | OpenAI | gpt-4o-mini |

**Modo Sequencial**: Quando os créditos de uma IA acabam, o bot usa automaticamente a próxima.

---

## Arquitetura

```
/
├── bot/                    # Node.js — Bot WhatsApp (Baileys)
│   ├── index.js           # Entrada principal + cron jobs
│   ├── whatsapp.js        # Conexão Baileys + pairing code
│   ├── ai/                # Integrações com IAs
│   ├── handlers/          # Lógica de mensagens, pedidos, agendamentos
│   └── utils/             # DB, helpers
├── admin/                 # Painel Admin (PHP)
├── client/                # Painel Cliente (PHP)
├── api/                   # Endpoints REST (PHP)
├── includes/              # DB, auth, funções PHP
├── assets/                # CSS e JS
├── database.sql           # Schema do banco
└── install.php            # Instalador web
```

---

## Créditos de IA Esgotados

O sistema detecta automaticamente quando uma IA retorna erro de crédito/quota e:
1. Marca a IA como "créditos esgotados"
2. Tenta a próxima na sequência (modo sequencial)
3. A cada 6 horas, reseta os status para tentar novamente

---

## Segurança

- Delete o `install.php` após a instalação
- Use HTTPS em produção
- Configure senhas fortes para admin e cliente
- Restrinja acesso ao arquivo `.env`
