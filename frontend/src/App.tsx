import { lazy, Suspense, useEffect, useMemo, useState } from "react";
import {
  ArrowDownRight,
  ArrowUpRight,
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  CircleDollarSign,
  LayoutDashboard,
  Moon,
  Plus,
  ReceiptText,
  Settings,
  Sun,
  Trash2,
  Wallet,
} from "lucide-react";
import type { FormEvent, InputHTMLAttributes, ReactNode } from "react";

const DistributionChart = lazy(() => import("./DistributionChart"));

type Category = {
  id: number;
  name: string;
  normal_percentage: number;
  extra_percentage: number;
  active: boolean;
};

type MonthlyBudget = {
  category: string;
  normal_percentage: number;
  extra_percentage: number;
  normal_amount: number;
  extra_amount: number;
  total_budget: number;
};

type MonthlyPlan = {
  id: number;
  month: string;
  normal_income: number;
  extra_income: number;
  budgets: MonthlyBudget[];
};

type Expense = {
  id: number;
  description: string;
  amount: number;
  category_id: number;
  category: string;
  month: string;
  date: string;
  created_at: string;
};

type Movement = {
  id: number;
  type: "deposit" | "withdrawal";
  amount: number;
  description: string | null;
  purchase_goal_id: number | null;
  purchase_goal: string | null;
  created_at: string;
};

type Goal = {
  id: number;
  name: string;
  balance_account_id: number;
  target_amount: number;
  priority: number;
  image_url: string | null;
  current_amount: number;
  active: boolean;
};

type Fund = {
  id: number;
  name: string;
  balance: number;
  movements: Movement[];
  goals: Goal[];
};

type ApiResponse<T> = { data: T; message?: string };

const apiBase = import.meta.env.VITE_API_URL ?? "/api";
const categoryColors = ["#2563eb", "#8b5cf6", "#10b981", "#f59e0b", "#ec4899"];
const today = new Date();

function moveMonth(
  current: { year: number; month: number },
  offset: number,
): { year: number; month: number } {
  const totalMonths = current.year * 12 + current.month - 1 + offset;

  return {
    year: Math.floor(totalMonths / 12),
    month: (totalMonths % 12) + 1,
  };
}

class ApiError extends Error {
  readonly status: number;

  constructor(message: string, status: number) {
    super(message);
    this.status = status;
  }
}

async function apiRequest<T>(
  path: string,
  init: RequestInit = {},
): Promise<ApiResponse<T>> {
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  if (
    init.body &&
    !(init.body instanceof FormData) &&
    !headers.has("Content-Type")
  ) {
    headers.set("Content-Type", "application/json");
  }

  const response = await fetch(`${apiBase}${path}`, {
    ...init,
    headers,
  });
  const result = (await response.json().catch(() => null)) as
    | ApiResponse<T> & { errors?: Record<string, string[]> }
    | null;

  if (!response.ok) {
    const validationMessage = result?.errors
      ? Object.values(result.errors).flat().join(" ")
      : undefined;
    throw new ApiError(
      validationMessage ??
        result?.message ??
        `A API respondeu com o erro ${response.status}.`,
      response.status,
    );
  }

  if (!result || !("data" in result)) {
    throw new Error("A API retornou uma resposta inválida.");
  }

  return result;
}

const currency = (value: number) =>
  Number(value).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
  });

function App() {
  const [darkMode, setDarkMode] = useState(
    () => localStorage.getItem("financeiro-theme") === "dark",
  );
  const [selectedMonth, setSelectedMonth] = useState({
    year: today.getFullYear(),
    month: today.getMonth() + 1,
  });
  const [activePage, setActivePage] = useState("Visão geral");
  const [plan, setPlan] = useState<MonthlyPlan | null>(null);
  const [expenses, setExpenses] = useState<Expense[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [funds, setFunds] = useState<Fund[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  useEffect(() => {
    document.documentElement.classList.toggle("dark", darkMode);
    localStorage.setItem("financeiro-theme", darkMode ? "dark" : "light");
  }, [darkMode]);

  const month = `${selectedMonth.year}-${String(selectedMonth.month).padStart(2, "0")}-01`;
  const monthLabel = new Intl.DateTimeFormat("pt-BR", {
    month: "long",
    year: "numeric",
    timeZone: "UTC",
  }).format(Date.UTC(selectedMonth.year, selectedMonth.month - 1, 1));

  function changeMonth(offset: number) {
    setSelectedMonth((current) => moveMonth(current, offset));
    setLoading(true);
    setError("");
    setNotice("");
    setPlan(null);
    setExpenses([]);
  }

  async function loadFunds() {
    const result = await apiRequest<Fund[]>("/funds");
    setFunds(result.data);
  }

  useEffect(() => {
    let active = true;

    apiRequest<Category[]>("/categories")
      .then((result) => {
        if (active) setCategories(result.data);
      })
      .catch((cause: Error) => {
        if (active) setError(cause.message);
      });

    apiRequest<Fund[]>("/funds")
      .then((result) => {
        if (active) setFunds(result.data);
      })
      .catch((cause: Error) => {
        if (active) setError(cause.message);
      });

    return () => {
      active = false;
    };
  }, []);

  useEffect(() => {
    let active = true;

    Promise.all([
      apiRequest<MonthlyPlan | null>(`/monthly-plans/${month}`).then(
        (result) => result.data,
      ),
      apiRequest<Expense[]>(`/expenses?month=${month}`).then(
        (result) => result.data,
      ),
    ])
      .then(([loadedPlan, loadedExpenses]) => {
        if (!active) return;
        setPlan(loadedPlan);
        setExpenses(loadedExpenses);
      })
      .catch((cause: Error) => {
        if (active) setError(cause.message);
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [month]);

  const totalIncome = plan
    ? Number(plan.normal_income) + Number(plan.extra_income)
    : 0;
  const totalExpenses = expenses.reduce(
    (total, expense) => total + Number(expense.amount),
    0,
  );
  const totalFunds = funds.reduce((total, fund) => total + fund.balance, 0);
  const budgets = useMemo(
    () =>
      (plan?.budgets ?? []).map((budget, index) => ({
        ...budget,
        normal_percentage: Number(budget.normal_percentage),
        extra_percentage: Number(budget.extra_percentage),
        normal_amount: Number(budget.normal_amount),
        extra_amount: Number(budget.extra_amount),
        total_budget: Number(budget.total_budget),
        color: categoryColors[index % categoryColors.length],
        spent: expenses
          .filter((expense) => expense.category === budget.category)
          .reduce((total, expense) => total + Number(expense.amount), 0),
      })),
    [expenses, plan],
  );

  async function handleCreatePlan(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setNotice("");
    const values = new FormData(event.currentTarget);

    try {
      const result = await apiRequest<MonthlyPlan>("/monthly-plans", {
        method: "POST",
        body: JSON.stringify({
          month,
          normal_income: Number(values.get("normal_income")),
          extra_income: Number(values.get("extra_income")),
        }),
      });
      setPlan(result.data);
      setNotice("Planejamento mensal criado com sucesso.");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Não foi possível criar o planejamento.");
    }
  }

  async function handleCreateExpense(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setNotice("");
    const form = event.currentTarget;
    const values = new FormData(form);

    try {
      const result = await apiRequest<Expense>("/expenses", {
        method: "POST",
        body: JSON.stringify({
          month,
          category_id: Number(values.get("category_id")),
          description: values.get("description"),
          amount: Number(values.get("amount")),
        }),
      });
      setExpenses((current) => [result.data, ...current]);
      form.reset();
      setNotice("Despesa cadastrada.");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Não foi possível cadastrar a despesa.");
    }
  }

  async function handleDeleteExpense(expense: Expense) {
    setError("");
    setNotice("");

    try {
      await apiRequest<null>(`/expenses/${expense.id}`, { method: "DELETE" });
      setExpenses((current) => current.filter((item) => item.id !== expense.id));
      setNotice("Despesa removida.");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Não foi possível remover a despesa.");
    }
  }

  async function handleCreateFund(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setNotice("");
    const form = event.currentTarget;
    const values = new FormData(form);

    try {
      await apiRequest<Fund>("/funds", {
        method: "POST",
        body: JSON.stringify({
          name: values.get("name"),
          initial_balance: Number(values.get("initial_balance") || 0),
        }),
      });
      await loadFunds();
      form.reset();
      setNotice("Fundo criado.");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Não foi possível criar o fundo.");
    }
  }

  async function handleMovement(
    event: FormEvent<HTMLFormElement>,
    fund: Fund,
  ) {
    event.preventDefault();
    setError("");
    setNotice("");
    const form = event.currentTarget;
    const values = new FormData(form);

    try {
      await apiRequest<Movement>(`/funds/${fund.id}/movements`, {
        method: "POST",
        body: JSON.stringify({
          type: values.get("type"),
          amount: Number(values.get("amount")),
          description: values.get("description"),
          purchase_goal_id: values.get("purchase_goal_id") || null,
        }),
      });
      await loadFunds();
      form.reset();
      setNotice("Movimentação registrada.");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Não foi possível registrar a movimentação.");
    }
  }

  async function handleCreateGoal(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setNotice("");
    const form = event.currentTarget;
    const values = new FormData(form);
    const image = values.get("image");
    if (image instanceof File && image.size === 0) {
      values.delete("image");
    }

    try {
      await apiRequest<Goal>("/goals", {
        method: "POST",
        body: values,
      });
      await loadFunds();
      form.reset();
      setNotice("Meta criada.");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Não foi possível criar a meta.");
    }
  }

  async function handleChangeGoalPriority(goal: Goal, priority: number) {
    setError("");
    setNotice("");

    try {
      await apiRequest<Goal>(`/goals/${goal.id}`, {
        method: "PATCH",
        body: JSON.stringify({ priority }),
      });
      await loadFunds();
      setNotice("Prioridade da meta atualizada.");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Não foi possível atualizar a prioridade.");
    }
  }

  async function handleToggleGoal(goal: Goal) {
    setError("");
    setNotice("");

    try {
      await apiRequest<Goal>(`/goals/${goal.id}`, {
        method: "PATCH",
        body: JSON.stringify({ active: !goal.active }),
      });
      await loadFunds();
      setNotice(goal.active ? "Meta arquivada." : "Meta reativada.");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Não foi possível atualizar a meta.");
    }
  }

  async function handleSaveDistribution(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setNotice("");
    const values = new FormData(event.currentTarget);
    const activeCategories = categories.filter((category) => category.active);

    try {
      const result = await apiRequest<Category[]>("/categories/distribution", {
        method: "PUT",
        body: JSON.stringify({
          categories: activeCategories.map((category) => ({
            id: category.id,
            normal_percentage: Number(values.get(`normal-${category.id}`)),
            extra_percentage: Number(values.get(`extra-${category.id}`)),
          })),
        }),
      });
      setCategories(result.data);
      setNotice("Distribuição atualizada.");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Não foi possível salvar as categorias.");
    }
  }

  const navItems = [
    { name: "Visão geral", icon: LayoutDashboard },
    { name: "Despesas", icon: ReceiptText },
    { name: "Fundos e metas", icon: Wallet },
    { name: "Configurações", icon: Settings },
  ];

  return (
    <div className="min-h-screen bg-[#f6f7fb] text-slate-900">
      <aside className="fixed inset-y-0 left-0 hidden w-64 flex-col border-r border-slate-200 bg-white px-5 py-6 lg:flex">
        <div className="mb-10 flex items-center gap-3 px-2">
          <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-600 text-white">
            <CircleDollarSign size={25} />
          </div>
          <div>
            <h1 className="text-lg font-bold">Financeiro</h1>
            <p className="text-xs text-slate-500">Sua vida em equilíbrio</p>
          </div>
        </div>
        <nav className="space-y-1">
          {navItems.map(({ name, icon: Icon }) => (
            <button
              key={name}
              type="button"
              onClick={() => setActivePage(name)}
              className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium ${
                activePage === name
                  ? "bg-blue-50 text-blue-700"
                  : "text-slate-500 hover:bg-slate-50"
              }`}
            >
              <Icon size={19} />
              {name}
            </button>
          ))}
        </nav>
      </aside>

      <div className="lg:pl-64">
        <header className="sticky top-0 z-10 flex min-h-[76px] flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 bg-white/95 px-5 py-3 backdrop-blur sm:px-8">
          <div>
            <p className="text-xs text-slate-500">Planejamento financeiro</p>
            <h2 className="text-lg font-bold">{activePage}</h2>
          </div>
          <nav className="flex gap-1 overflow-x-auto lg:hidden">
            {navItems.map(({ name, icon: Icon }) => (
              <button
                key={name}
                type="button"
                aria-label={name}
                title={name}
                onClick={() => setActivePage(name)}
                className={`rounded-xl p-2 ${
                  activePage === name ? "bg-blue-50 text-blue-700" : "text-slate-500"
                }`}
              >
                <Icon size={20} />
              </button>
            ))}
          </nav>
          <div className="flex items-center gap-2 rounded-xl border border-slate-200 bg-white p-1">
            <CalendarDays size={17} className="ml-2 text-slate-400" />
            <button
              type="button"
              aria-label="Mês anterior"
              onClick={() => changeMonth(-1)}
              className="rounded-lg p-2 hover:bg-slate-100"
            >
              <ChevronLeft size={17} />
            </button>
            <span className="min-w-28 text-center text-sm font-semibold capitalize">
              {monthLabel}
            </span>
            <button
              type="button"
              aria-label="Próximo mês"
              onClick={() => changeMonth(1)}
              className="rounded-lg p-2 hover:bg-slate-100"
            >
              <ChevronRight size={17} />
            </button>
          </div>
          <button
            type="button"
            aria-label={darkMode ? "Ativar modo claro" : "Ativar modo escuro"}
            aria-pressed={darkMode}
            title={darkMode ? "Ativar modo claro" : "Ativar modo escuro"}
            onClick={() => setDarkMode((current) => !current)}
            className="rounded-xl border border-slate-200 bg-white p-2 text-slate-600 hover:bg-slate-50"
          >
            {darkMode ? <Sun size={19} /> : <Moon size={19} />}
          </button>
        </header>

        <main className="mx-auto max-w-[1500px] space-y-6 p-5 sm:p-8">
          {error && <Notice tone="error" message={error} onDismiss={() => setError("")} />}
          {notice && <Notice tone="success" message={notice} onDismiss={() => setNotice("")} />}

          {activePage === "Visão geral" && (
            <Dashboard
              monthLabel={monthLabel}
              loading={loading}
              plan={plan}
              expenses={expenses}
              budgets={budgets}
              funds={funds}
              totalIncome={totalIncome}
              totalExpenses={totalExpenses}
              totalFunds={totalFunds}
              onCreatePlan={handleCreatePlan}
              onNavigate={setActivePage}
            />
          )}

          {activePage === "Despesas" && (
            <ExpensesPage
              monthLabel={monthLabel}
              loading={loading}
              plan={plan}
              expenses={expenses}
              categories={categories}
              onCreate={handleCreateExpense}
              onDelete={handleDeleteExpense}
            />
          )}

          {activePage === "Fundos e metas" && (
            <FundsPage
              funds={funds}
              onCreateFund={handleCreateFund}
              onMovement={handleMovement}
              onCreateGoal={handleCreateGoal}
              onChangeGoalPriority={handleChangeGoalPriority}
              onToggleGoal={handleToggleGoal}
            />
          )}

          {activePage === "Configurações" && (
            <SettingsPage categories={categories} onSave={handleSaveDistribution} />
          )}
        </main>
      </div>
    </div>
  );
}

function Dashboard({
  monthLabel,
  loading,
  plan,
  expenses,
  budgets,
  funds,
  totalIncome,
  totalExpenses,
  totalFunds,
  onCreatePlan,
  onNavigate,
}: {
  monthLabel: string;
  loading: boolean;
  plan: MonthlyPlan | null;
  expenses: Expense[];
  budgets: (MonthlyBudget & { color: string; spent: number })[];
  funds: Fund[];
  totalIncome: number;
  totalExpenses: number;
  totalFunds: number;
  onCreatePlan: (event: FormEvent<HTMLFormElement>) => void;
  onNavigate: (page: string) => void;
}) {
  return (
    <>
      <section className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <p className="mb-2 text-sm font-medium text-blue-600">
            SEU PLANEJAMENTO FINANCEIRO
          </p>
          <h1 className="text-2xl font-bold sm:text-3xl">Seu dinheiro, sob controle.</h1>
          <p className="mt-2 text-sm text-slate-500">
            Acompanhe as entradas, despesas e objetivos de {monthLabel}.
          </p>
        </div>
      </section>

      {loading && <p className="text-sm text-slate-500">Carregando dados do mês...</p>}
      {!loading && !plan && (
        <section className="rounded-2xl border border-blue-100 bg-white p-5 shadow-sm sm:p-6">
          <h3 className="font-bold">Comece pelo planejamento do mês</h3>
          <p className="mt-1 text-sm text-slate-500">
            Informe suas entradas para distribuir a renda entre as categorias.
          </p>
          <form onSubmit={onCreatePlan} className="mt-5 grid gap-4 sm:grid-cols-3">
            <Field label="Renda normal" name="normal_income" type="number" min="0" step="0.01" required />
            <Field label="Renda extra" name="extra_income" type="number" min="0" step="0.01" defaultValue="0" required />
            <button className="self-end rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">
              Criar planejamento
            </button>
          </form>
        </section>
      )}

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard title="Entradas do mês" value={currency(totalIncome)} detail="Renda normal + extra" icon={ArrowDownRight} color="blue" valueTone="positive" />
        <SummaryCard title="Despesas realizadas" value={currency(totalExpenses)} detail={`${expenses.length} lançamento(s)`} icon={ReceiptText} color="violet" valueTone="negative" />
        <SummaryCard title="Saldo após despesas" value={currency(totalIncome - totalExpenses)} detail="Entradas menos despesas" icon={ArrowUpRight} color="emerald" />
        <SummaryCard title="Saldo dos fundos" value={currency(totalFunds)} detail="Saldo acumulado" icon={Wallet} color="amber" />
      </section>

      <section className="grid gap-6 xl:grid-cols-2">
        <Card title="Distribuição da renda" subtitle="Orçamento mensal por categoria">
          {budgets.length === 0 ? (
            <EmptyState message="Crie o planejamento mensal para visualizar a distribuição." />
          ) : (
            <div className="grid items-center gap-3 md:grid-cols-2">
              <Suspense fallback={<div className="h-56 animate-pulse rounded-xl bg-slate-100" />}>
                <DistributionChart budgets={budgets} />
              </Suspense>
              <div className="space-y-4">
                {budgets.map((budget) => (
                  <div key={budget.category}>
                    <div className="flex justify-between gap-2 text-sm">
                      <span>{budget.category}</span>
                      <strong>{budget.normal_percentage}%</strong>
                    </div>
                    <p className="mt-1 text-xs text-slate-500">
                      Normal {currency(Number(budget.normal_amount))} · Extra {currency(Number(budget.extra_amount))}
                    </p>
                  </div>
                ))}
              </div>
            </div>
          )}
        </Card>

        <Card title="Orçamento por categoria" subtitle="Gasto real comparado ao valor planejado">
          {budgets.length === 0 ? (
            <EmptyState message="As categorias aparecerão aqui após criar o planejamento." />
          ) : (
            <div className="space-y-5">
              {budgets.map((budget) => {
                const limit = Number(budget.total_budget);
                const percentage = limit > 0 ? Math.min(100, (budget.spent / limit) * 100) : 0;

                return (
                  <div key={budget.category}>
                    <div className="mb-2 flex justify-between gap-2 text-sm">
                      <span>{budget.category}</span>
                      <span className="text-slate-500">{Math.round(percentage)}%</span>
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                      <div className="h-full rounded-full" style={{ width: `${percentage}%`, backgroundColor: budget.color }} />
                    </div>
                    <div className="mt-1 flex justify-between text-xs text-slate-500">
                      <span>Gasto: {currency(budget.spent)}</span>
                      <span>Limite: {currency(limit)}</span>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </Card>
      </section>

      <section className="grid gap-6 xl:grid-cols-2">
        <Card title="Últimas despesas" subtitle="Lançamentos reais deste mês">
          {expenses.length === 0 ? (
            <EmptyState message="Nenhuma despesa lançada neste mês." action="Despesas" onAction={onNavigate} />
          ) : (
            <div className="divide-y divide-slate-100">
              {expenses.slice(0, 5).map((expense) => (
                <div key={expense.id} className="flex items-center justify-between gap-4 py-3">
                  <div>
                    <p className="text-sm font-semibold">{expense.description}</p>
                    <p className="text-xs text-slate-500">{expense.category}</p>
                  </div>
                  <span className="text-sm font-bold text-red-600">− {currency(Number(expense.amount))}</span>
                </div>
              ))}
            </div>
          )}
        </Card>
        <Card title="Fundos e metas" subtitle="Saldos e objetivos cadastrados">
          <p className="mb-3 text-sm text-slate-600">
            Saldo disponível nos fundos: <strong>{currency(totalFunds)}</strong>
          </p>
          {funds.length === 0 ? (
            <EmptyState message="Nenhum fundo cadastrado." />
          ) : (
            <div className="mb-5 space-y-4">
              {funds.map((fund) => {
                const priorityGoal = fund.goals
                  .filter((goal) => goal.active)
                  .sort((first, second) => first.priority - second.priority)[0];

                if (!priorityGoal) {
                  return (
                    <div key={fund.id} className="rounded-xl border border-slate-200 p-4">
                      <p className="font-semibold">{fund.name}</p>
                      <p className="mt-1 text-sm text-slate-500">Nenhuma meta ativa neste fundo.</p>
                    </div>
                  );
                }

                const progress = Math.min(
                  100,
                  (priorityGoal.current_amount / priorityGoal.target_amount) * 100,
                );

                return (
                  <div key={fund.id} className="flex gap-4 rounded-xl border border-slate-200 p-4">
                    {priorityGoal.image_url ? (
                      <img
                        src={priorityGoal.image_url}
                        alt={`Imagem da meta ${priorityGoal.name}`}
                        className="h-24 w-24 shrink-0 rounded-xl bg-slate-100 object-contain p-1"
                      />
                    ) : (
                      <div className="flex h-24 w-24 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-center text-sm text-slate-500">
                        Sem foto
                      </div>
                    )}
                    <div className="min-w-0 flex-1">
                      <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                          <p className="truncate text-sm text-slate-500">{fund.name} · 1ª prioridade</p>
                          <p className="truncate text-base font-semibold">{priorityGoal.name}</p>
                        </div>
                        <span className="shrink-0 text-sm font-semibold text-emerald-600">
                          {Math.round(progress)}%
                        </span>
                      </div>
                      <div className="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-100">
                        <div
                          className="h-full rounded-full bg-emerald-500"
                          style={{ width: `${progress}%` }}
                        />
                      </div>
                      <p className="mt-2 text-sm text-slate-500">
                        {currency(priorityGoal.current_amount)} de {currency(priorityGoal.target_amount)}
                      </p>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
          <button type="button" onClick={() => onNavigate("Fundos e metas")} className="text-sm font-semibold text-blue-700 hover:underline">
            Gerenciar fundos e metas
          </button>
        </Card>
      </section>
    </>
  );
}

function ExpensesPage({
  monthLabel,
  loading,
  plan,
  expenses,
  categories,
  onCreate,
  onDelete,
}: {
  monthLabel: string;
  loading: boolean;
  plan: MonthlyPlan | null;
  expenses: Expense[];
  categories: Category[];
  onCreate: (event: FormEvent<HTMLFormElement>) => void;
  onDelete: (expense: Expense) => void;
}) {
  const availableCategories = plan
    ? categories.filter((category) => plan.budgets.some((budget) => budget.category === category.name))
    : [];

  return (
    <>
      <PageIntro title="Despesas" subtitle={`Lançamentos de ${monthLabel}`} />
      {!plan ? (
        <EmptyState message="Crie o planejamento mensal antes de lançar despesas." />
      ) : (
        <Card title="Nova despesa" subtitle="O lançamento será vinculado ao orçamento da categoria">
          <form onSubmit={onCreate} className="grid gap-4 md:grid-cols-4">
            <Field label="Descrição" name="description" required maxLength={255} />
            <label className="grid gap-1.5 text-sm font-medium text-slate-700">
              Categoria
              <select name="category_id" required className={inputClass}>
                {availableCategories.map((category) => (
                  <option key={category.id} value={category.id}>{category.name}</option>
                ))}
              </select>
            </label>
            <Field label="Valor" name="amount" type="number" min="0.01" step="0.01" required />
            <button className="self-end rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">
              <Plus size={16} className="mr-1 inline" /> Adicionar despesa
            </button>
          </form>
        </Card>
      )}

      <Card title="Despesas do mês" subtitle={`${expenses.length} lançamento(s)`}>
        {loading ? <EmptyState message="Carregando despesas..." /> : expenses.length === 0 ? (
          <EmptyState message="Nenhuma despesa cadastrada para este mês." />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[520px] text-left text-sm">
              <thead className="text-xs uppercase text-slate-500">
                <tr><th className="pb-3">Descrição</th><th className="pb-3">Categoria</th><th className="pb-3">Data</th><th className="pb-3 text-right">Valor</th><th /></tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {expenses.map((expense) => (
                  <tr key={expense.id}>
                    <td className="py-3 font-medium">{expense.description}</td>
                    <td className="py-3">{expense.category}</td>
                    <td className="py-3 text-slate-500">{new Date(`${expense.date}T12:00:00`).toLocaleDateString("pt-BR")}</td>
                    <td className="py-3 text-right font-semibold">{currency(Number(expense.amount))}</td>
                    <td className="py-3 text-right">
                      <button type="button" onClick={() => onDelete(expense)} aria-label={`Excluir ${expense.description}`} className="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600">
                        <Trash2 size={16} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </>
  );
}

function FundsPage({
  funds,
  onCreateFund,
  onMovement,
  onCreateGoal,
  onChangeGoalPriority,
  onToggleGoal,
}: {
  funds: Fund[];
  onCreateFund: (event: FormEvent<HTMLFormElement>) => void;
  onMovement: (event: FormEvent<HTMLFormElement>, fund: Fund) => void;
  onCreateGoal: (event: FormEvent<HTMLFormElement>) => void;
  onChangeGoalPriority: (goal: Goal, priority: number) => void;
  onToggleGoal: (goal: Goal) => void;
}) {
  const [goalFundId, setGoalFundId] = useState<number | null>(null);
  const selectedGoalFund = funds.find((fund) => fund.id === goalFundId) ?? funds[0];
  const activeGoals = funds.flatMap((fund) => fund.goals.filter((goal) => goal.active));

  return (
    <>
      <PageIntro title="Fundos e metas" subtitle="Organize saldos, depósitos e objetivos de compra." />
      <section className="grid gap-6 xl:grid-cols-2">
        <Card title="Criar fundo" subtitle="Ex.: reserva, viagem ou despesas do mês">
          <form onSubmit={onCreateFund} className="grid gap-4 sm:grid-cols-[1fr_1fr_auto]">
            <Field label="Nome do fundo" name="name" required maxLength={255} />
            <Field label="Saldo inicial" name="initial_balance" type="number" min="0" step="0.01" defaultValue="0" />
            <button className="self-end rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">Criar fundo</button>
          </form>
        </Card>
        <Card title="Criar meta" subtitle="Cada meta acompanha depósitos e resgates próprios">
          {funds.length === 0 ? <EmptyState message="Crie um fundo antes de cadastrar uma meta." /> : (
            <form onSubmit={onCreateGoal} encType="multipart/form-data" className="grid gap-4 sm:grid-cols-2">
              <Field label="Nome da meta" name="name" required maxLength={255} />
              <label className="grid gap-1.5 text-sm font-medium text-slate-700">
                Fundo
                <select
                  name="balance_account_id"
                  required
                  className={inputClass}
                  value={selectedGoalFund?.id ?? ""}
                  onChange={(event) => setGoalFundId(Number(event.target.value))}
                >
                  {funds.map((fund) => <option key={fund.id} value={fund.id}>{fund.name}</option>)}
                </select>
              </label>
              <Field label="Valor desejado" name="target_amount" type="number" min="0.01" step="0.01" required />
              <label className="grid gap-1.5 text-sm font-medium text-slate-700">
                Prioridade no fundo
                <select
                  key={selectedGoalFund?.id ?? "no-fund"}
                  name="priority"
                  required
                  className={inputClass}
                  defaultValue={selectedGoalFund ? selectedGoalFund.goals.length + 1 : ""}
                >
                  {Array.from({ length: (selectedGoalFund?.goals.length ?? 0) + 1 }, (_, index) => (
                    <option key={index + 1} value={index + 1}>{index + 1}ª prioridade</option>
                  ))}
                </select>
              </label>
              <label className="grid gap-1.5 text-sm font-medium text-slate-700">
                Imagem da meta
                <input name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" className={inputClass} />
                <span className="text-xs font-normal text-slate-500">JPEG, PNG, WebP ou GIF; até 5 MB.</span>
              </label>
              <button className="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 sm:col-span-2">Criar meta</button>
            </form>
          )}
        </Card>
      </section>

      {funds.length === 0 ? <EmptyState message="Nenhum fundo cadastrado. Crie seu primeiro fundo acima." /> : (
        <section className="grid gap-5 xl:grid-cols-2">
          {funds.map((fund) => (
            <Card key={fund.id} title={fund.name} subtitle={`Saldo atual: ${currency(fund.balance)}`}>
              <form onSubmit={(event) => onMovement(event, fund)} className="grid gap-3 sm:grid-cols-2">
                <label className="grid gap-1.5 text-sm font-medium text-slate-700">
                  Tipo
                  <select name="type" className={inputClass}>
                    <option value="deposit">Depósito</option>
                    <option value="withdrawal">Resgate</option>
                  </select>
                </label>
                <Field label="Valor" name="amount" type="number" min="0.01" step="0.01" required />
                <Field label="Descrição" name="description" maxLength={255} />
                <label className="grid gap-1.5 text-sm font-medium text-slate-700">
                  Vincular à meta
                  <select name="purchase_goal_id" className={inputClass} defaultValue="">
                    <option value="">Sem meta</option>
                    {fund.goals.filter((goal) => goal.active).map((goal) => <option key={goal.id} value={goal.id}>{goal.name}</option>)}
                  </select>
                </label>
                <button className="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:col-span-2">Registrar movimentação</button>
              </form>

              {fund.goals.length > 0 && (
                <div className="mt-6 space-y-3">
                  <h4 className="text-sm font-bold">Metas</h4>
                  {fund.goals.map((goal) => {
                    const progress = Math.min(100, (goal.current_amount / goal.target_amount) * 100);
                    return (
                      <div key={goal.id} className="rounded-xl border border-slate-100 p-3">
                        <div className="flex items-start justify-between gap-3">
                          <div className="flex min-w-0 items-start gap-3">
                            {goal.image_url && (
                              <img
                                src={goal.image_url}
                                alt={`Imagem da meta ${goal.name}`}
                                className="h-16 w-16 shrink-0 rounded-lg object-cover"
                              />
                            )}
                            <div>
                              <p className="font-semibold">{goal.name} {!goal.active && <span className="text-xs font-normal text-slate-400">(arquivada)</span>}</p>
                              <p className="text-xs text-slate-500">{currency(goal.current_amount)} de {currency(goal.target_amount)}</p>
                              <label className="mt-2 flex items-center gap-2 text-xs text-slate-500">
                                Prioridade
                                <select
                                  aria-label={`Prioridade de ${goal.name}`}
                                  className="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700"
                                  value={goal.priority}
                                  onChange={(event) => onChangeGoalPriority(goal, Number(event.target.value))}
                                >
                                  {fund.goals.map((_, index) => (
                                    <option key={index + 1} value={index + 1}>{index + 1}ª</option>
                                  ))}
                                </select>
                              </label>
                            </div>
                          </div>
                          <button type="button" onClick={() => onToggleGoal(goal)} className="text-xs font-semibold text-blue-700 hover:underline">
                            {goal.active ? "Arquivar" : "Reativar"}
                          </button>
                        </div>
                        <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                          <div className="h-full rounded-full bg-emerald-500" style={{ width: `${progress}%` }} />
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}

              <div className="mt-6">
                <h4 className="mb-2 text-sm font-bold">Movimentações recentes</h4>
                {fund.movements.length === 0 ? <p className="text-sm text-slate-500">Nenhuma movimentação.</p> : (
                  <div className="divide-y divide-slate-100">
                    {fund.movements.map((movement) => (
                      <div key={movement.id} className="flex justify-between gap-3 py-2 text-sm">
                        <span>{movement.description || movement.purchase_goal || (movement.type === "deposit" ? "Depósito" : "Resgate")}</span>
                        <strong className={movement.type === "deposit" ? "text-emerald-700" : "text-red-600"}>
                          {movement.type === "deposit" ? "+" : "−"} {currency(Number(movement.amount))}
                        </strong>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </Card>
          ))}
        </section>
      )}
      <p className="text-sm text-slate-500">{activeGoals.length} meta(s) ativa(s).</p>
    </>
  );
}

function SettingsPage({
  categories,
  onSave,
}: {
  categories: Category[];
  onSave: (event: FormEvent<HTMLFormElement>) => void;
}) {
  const activeCategories = categories.filter((category) => category.active);

  return (
    <>
      <PageIntro title="Configurações" subtitle="Defina como as entradas mensais serão distribuídas." />
      <Card title="Distribuição por categoria" subtitle="A soma de cada coluna deve ser exatamente 100%.">
        {activeCategories.length === 0 ? <EmptyState message="Nenhuma categoria ativa foi cadastrada." /> : (
          <form onSubmit={onSave}>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[560px] text-left text-sm">
                <thead className="text-xs uppercase text-slate-500">
                  <tr><th className="pb-3">Categoria</th><th className="pb-3">Renda normal (%)</th><th className="pb-3">Renda extra (%)</th></tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {activeCategories.map((category) => (
                    <tr key={category.id}>
                      <td className="py-3 font-medium">{category.name}</td>
                      <td className="py-3"><input className={inputClass} name={`normal-${category.id}`} type="number" min="0" max="100" step="0.01" defaultValue={category.normal_percentage} required /></td>
                      <td className="py-3"><input className={inputClass} name={`extra-${category.id}`} type="number" min="0" max="100" step="0.01" defaultValue={category.extra_percentage} required /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <button className="mt-5 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">Salvar distribuição</button>
          </form>
        )}
      </Card>
    </>
  );
}

const inputClass =
  "w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100";

function Field({
  label,
  name,
  type = "text",
  ...props
}: {
  label: string;
  name: string;
  type?: string;
} & InputHTMLAttributes<HTMLInputElement>) {
  return (
    <label className="grid gap-1.5 text-sm font-medium text-slate-700">
      {label}
      <input name={name} type={type} className={inputClass} {...props} />
    </label>
  );
}

function PageIntro({ title, subtitle }: { title: string; subtitle: string }) {
  return (
    <section>
      <h1 className="text-2xl font-bold">{title}</h1>
      <p className="mt-1 text-sm text-slate-500">{subtitle}</p>
    </section>
  );
}

function Card({
  title,
  subtitle,
  children,
}: {
  title: string;
  subtitle?: string;
  children: ReactNode;
}) {
  return (
    <section className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
      <div className="mb-5">
        <h3 className="font-bold">{title}</h3>
        {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
      </div>
      {children}
    </section>
  );
}

function SummaryCard({
  title,
  value,
  detail,
  icon: Icon,
  color,
  valueTone,
}: {
  title: string;
  value: string;
  detail: string;
  icon: typeof Wallet;
  color: "blue" | "violet" | "emerald" | "amber";
  valueTone?: "positive" | "negative";
}) {
  const colors = {
    blue: "bg-blue-50 text-blue-600",
    violet: "bg-violet-50 text-violet-600",
    emerald: "bg-emerald-50 text-emerald-600",
    amber: "bg-amber-50 text-amber-600",
  };

  return (
    <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-slate-500">{title}</p>
          <p className={`mt-3 text-2xl font-bold ${valueTone === "positive" ? "text-emerald-600" : valueTone === "negative" ? "text-red-600" : ""}`}>{value}</p>
        </div>
        <div className={`rounded-xl p-3 ${colors[color]}`}><Icon size={21} /></div>
      </div>
      <p className="mt-4 text-xs text-slate-400">{detail}</p>
    </div>
  );
}

function EmptyState({
  message,
  action,
  onAction,
}: {
  message: string;
  action?: string;
  onAction?: (page: string) => void;
}) {
  return (
    <div className="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
      {message}
      {action && onAction && (
        <button type="button" onClick={() => onAction(action)} className="ml-1 font-semibold text-blue-700 hover:underline">
          Abrir {action.toLowerCase()}
        </button>
      )}
    </div>
  );
}

function Notice({
  tone,
  message,
  onDismiss,
}: {
  tone: "error" | "success";
  message: string;
  onDismiss: () => void;
}) {
  return (
    <div role={tone === "error" ? "alert" : "status"} className={`flex items-start justify-between gap-4 rounded-xl px-4 py-3 text-sm ${tone === "error" ? "bg-red-50 text-red-700" : "bg-emerald-50 text-emerald-800"}`}>
      <span>{message}</span>
      <button type="button" aria-label="Fechar aviso" onClick={onDismiss} className="font-bold">×</button>
    </div>
  );
}

export default App;
