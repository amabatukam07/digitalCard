# NexoSupri — Plataforma B2B de Abastecimento Empresarial com Créditos Pré-Pagos

> **Sua empresa cuida dos negócios. A NexoSupri cuida das compras.**

Plataforma B2B desenvolvida em **Laravel 13 + PHP 8.4 + Blade + Tailwind CSS + Alpine.js** para abastecimento corporativo com carteira pré-paga de créditos empresariais (**1 crédito = R$ 1,00**, modelo exclusivo para compra no catálogo, sem bônus ou desconto).

---

## Principais Módulos

- **Empresas Clientes (CNPJ) & Multi-usuário**:
  - Cadastro corporativo com razão social, nome fantasia, CNPJ, inscrição estadual e endereço de faturamento.
  - Perfis empresariais separados: **Gestor da Empresa** (`company_manager`) e **Solicitante** (`company_requester`), com limite de gasto por pedido, aprovação de pedidos pelo gestor e alertas de saldo baixo.
- **5 Planos Comerciais & Contratos**:
  - `Essencial` (R$ 500), `Business` (R$ 1.000), `Corporate` (R$ 2.500), `Premium` (R$ 5.000) e `Enterprise` (a partir de R$ 10.000).
- **Carteira de Créditos & Pagamentos**:
  - Extrato auditável (`credit_transactions`) com créditos, débitos, estornos e conferência automática de integridade (`nexosupri:reconcile-ledgers`).
  - Recarga por **PIX simulado** ou transferência/boleto com upload de comprovante e aprovação pelo backoffice.
- **Catálogo B2B & Formação de Preço (Simples Nacional / DAS)**:
  - Categorias corporativas: *Água e bebidas não alcoólicas*, *Escritório*, *Limpeza*, *Higiene, copa e descartáveis*.
  - Precificação transparente: $\text{Preço} = \frac{C \times (1 + m)}{1 - t - v}$, considerando custo de compra, frete de entrada, outros custos, markup e alíquota de DAS, com histórico em `product_prices`.
- **Fornecedores, Ordens de Compra & Estoque**:
  - Cadastro de fornecedores homologados, vínculo com SKUs, emissão e recebimento de ordens de compra (atualizando custo e estoque automaticamente) e histórico de movimentações (`stock_movements`).
- **Backoffice Administrativo com RBAC**:
  - Perfis internos: `admin`, `commercial`, `purchasing`, `finance`, `logistics` e `auditor`.
  - Dashboards operacionais, simulador financeiro de carteira/DAS e exportações CSV.

---

## Requisitos e Instalação Local

1. **Dependências**:
   - PHP 8.4+ (`pdo_sqlite` ou `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`)
   - Composer e Node.js 20+ / npm

2. **Configuração inicial**:
   ```bash
   composer install --no-interaction
   npm install
   cp .env.example .env
   php artisan key:generate
   ```

3. **Banco de dados e Seed inicial**:
   Por padrão o projeto usa SQLite local (`database/database.sqlite`), sendo 100% compatível com MySQL (`DB_CONNECTION=mysql`, `DB_DATABASE=nexosupri`).
   ```bash
   php artisan migrate:fresh --seed
   npm run build
   ```
   > **Observação:** Em ambiente local/development, o seeder exibe no terminal os e-mails e senhas gerados para os perfis de demonstração (Backoffice, Gestor e Solicitante).

4. **Executar servidor e fila/agendador**:
   ```bash
   php artisan serve
   ```
   Comandos de manutenção disponíveis:
   - `php artisan nexosupri:reconcile-ledgers` — verifica consistência entre saldo das carteiras e razão contábil (`credit_transactions`).
   - `php artisan nexosupri:expire-pix-charges` — expira cobranças PIX pendentes vencidas.

5. **Testes e Qualidade de Código**:
   ```bash
   php artisan test --compact
   php vendor/bin/pint --dirty --format agent
   ```
