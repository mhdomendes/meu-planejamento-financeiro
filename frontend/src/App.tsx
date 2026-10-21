import { useEffect, useState } from "react";
import {
  LayoutDashboard,
  ReceiptText,
  Wallet,
  Settings,
  ChevronLeft,
  ChevronRight,
  Plus,
  ArrowUpRight,
  ArrowDownRight,
  CarFront,
  ShoppingBag,
  TrendingUp,
  CircleDollarSign,
  CalendarDays,
  Bell,
  Search,
  Menu,
  Moon,
  Sun,
} from "lucide-react";
import { ResponsiveContainer, PieChart, Pie, Cell, Tooltip } from "recharts";

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

const categoryColors: Record<string, string> = {
  "Despesas essenciais": "#2563eb",
  "Estilo de vida": "#8b5cf6",
  Investimentos: "#10b981",
};

async function fetchMonthlyPlan(month: string): Promise<MonthlyPlan | null> {
  const response = await fetch(
    `http://localhost:8000/api/monthly-plans/${month}`,
    {
      headers: { Accept: "application/json" },
    },
  );

  if (response.status === 404) {
    return null;
  }

  if (!response.ok) {
    throw new Error("Não foi possível carregar o planejamento mensal.");
  }

  const result = await response.json();
  return result.data as MonthlyPlan;
}


const expenses = [
  { name: "Mercado", category: "Despesas essenciais", value: 450 },
  { name: "Energia elétrica", category: "Despesas essenciais", value: 150 },
  { name: "Academia", category: "Estilo de vida", value: 59.9 },
  { name: "Cinema", category: "Estilo de vida", value: 60 },
];

const goals = [
  {
    name: "Tênis novo",
    fund: "Fundo Compras",
    current: 100,
    target: 350,
    image:
      "https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&auto=format&fit=crop&q=80",
    icon: ShoppingBag,
  },
  {
    name: "Sabão veicular",
    fund: "Fundo Veículo",
    current: 12,
    target: 35,
    image:
      "https://images.unsplash.com/photo-1607860108855-64acf2078ed9?w=400&auto=format&fit=crop&q=80",
    icon: CarFront,
  },
];

const currency = (value: number) =>
  value.toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
  });

function App() {
  const [monthOffset, setMonthOffset] = useState(0);
  const [monthlyPlan, setMonthlyPlan] = useState<MonthlyPlan | null>(null);
  const [loadingPlan, setLoadingPlan] = useState(true);
  const [planError, setPlanError] = useState("");
  const [activePage, setActivePage] = useState("Dashboard");
  const [mobileMenu, setMobileMenu] = useState(false);
  const [darkMode, setDarkMode] = useState(() => {
    return localStorage.getItem("financeiro-theme") === "dark";
  });

  function toggleDarkMode() {
    setDarkMode((current) => {
      const next = !current;
      localStorage.setItem("financeiro-theme", next ? "dark" : "light");
      return next;
    });
  }

  const selectedDate = new Date(2026, 9 + monthOffset, 1);
  const monthLabel = selectedDate.toLocaleDateString("pt-BR", {
    month: "long",
    year: "numeric",
  });

  const navItems = [
    { name: "Dashboard", icon: LayoutDashboard },
    { name: "Despesas", icon: ReceiptText },
    { name: "Fundos e metas", icon: Wallet },
    { name: "Configurações", icon: Settings },
  ];

  const totalIncome = 4500;
  const totalExpenses = 719.9;
  const available = totalIncome - totalExpenses;
  const totalFunds = 112;

  return (
    <div
      className={`min-h-screen transition-colors duration-300 ${
        darkMode
          ? "dark bg-slate-950 text-slate-100"
          : "bg-[#f6f7fb] text-slate-900"
      }`}
    >
      {mobileMenu && (
        <button
          aria-label="Fechar menu"
          className="fixed inset-0 z-40 bg-slate-950/30 lg:hidden"
          onClick={() => setMobileMenu(false)}
        />
      )}

      <aside
        className={`fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white px-5 py-6 transition-transform lg:translate-x-0 ${
          mobileMenu ? "translate-x-0" : "-translate-x-full"
        }`}
      >
        <div className="mb-10 flex items-center gap-3 px-2">
          <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-lg shadow-blue-600/20">
            <CircleDollarSign size={25} />
          </div>
          <div>
            <h1 className="text-lg font-bold tracking-tight">Financeiro</h1>
            <p className="text-xs text-slate-500">Sua vida em equilíbrio</p>
          </div>
        </div>

        <p className="mb-3 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">
          Menu principal
        </p>

        <nav className="space-y-1">
          {navItems.map(({ name, icon: Icon }) => (
            <button
              key={name}
              onClick={() => {
                setActivePage(name);
                setMobileMenu(false);
              }}
              className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition ${
                activePage === name
                  ? "bg-blue-50 text-blue-700"
                  : "text-slate-500 hover:bg-slate-50 hover:text-slate-900"
              }`}
            >
              <Icon size={19} />
              {name}
              {activePage === name && (
                <span className="ml-auto h-5 w-1 rounded-full bg-blue-600" />
              )}
            </button>
          ))}
        </nav>

        <div className="mt-auto rounded-2xl bg-slate-900 p-4 text-white">
          <div className="mb-3 flex h-9 w-9 items-center justify-center rounded-xl bg-white/10">
            <TrendingUp size={19} />
          </div>
          <p className="text-sm font-semibold">Um passo de cada vez</p>
          <p className="mt-1 text-xs leading-5 text-slate-300">
            Organize seu dinheiro hoje para conquistar seus objetivos amanhã.
          </p>
        </div>

        <div className="mt-5 flex items-center gap-3 border-t border-slate-100 pt-5">
          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 font-bold text-blue-700">
            F
          </div>
          <div>
            <p className="text-sm font-semibold">Minha carteira</p>
            <p className="text-xs text-slate-500">Planejamento pessoal</p>
          </div>
        </div>
      </aside>

      <div className="min-h-screen lg:pl-64">
        <header className="sticky top-0 z-30 flex h-[76px] items-center justify-between border-b border-slate-200/80 bg-white/90 px-5 backdrop-blur-xl sm:px-8">
          <div className="flex items-center gap-3">
            <button
              aria-label="Abrir menu"
              onClick={() => setMobileMenu(true)}
              className="rounded-xl p-2 hover:bg-slate-100 lg:hidden"
            >
              <Menu size={21} />
            </button>
            <div>
              <p className="text-xs text-slate-500">Visão geral</p>
              <h2 className="text-base font-bold sm:text-lg">{activePage}</h2>
            </div>
          </div>

          <div className="flex items-center gap-2 sm:gap-4">
            <button
              type="button"
              onClick={toggleDarkMode}
              aria-label={darkMode ? "Ativar modo claro" : "Ativar modo escuro"}
              title={darkMode ? "Ativar modo claro" : "Ativar modo escuro"}
              className="rounded-xl border border-slate-200 p-2.5 text-slate-500 transition hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
            >
              {darkMode ? <Sun size={20} /> : <Moon size={20} />}
            </button>

            <button
              aria-label="Pesquisar"
              className="hidden rounded-xl border border-slate-200 p-2.5 text-slate-500 hover:bg-slate-50 sm:block"
            >
              <Search size={18} />
            </button>

            <button
              aria-label="Notificações"
              className="relative rounded-xl border border-slate-200 p-2.5 text-slate-500 hover:bg-slate-50"
            >
              <Bell size={18} />
              <span className="absolute right-2 top-2 h-2 w-2 rounded-full bg-blue-600 ring-2 ring-white" />
            </button>

            <div className="hidden h-9 w-px bg-slate-200 sm:block" />

            <div className="hidden text-right sm:block">
              <p className="text-sm font-semibold">Olá, tudo bem?</p>
              <p className="text-xs text-slate-500">
                Vamos organizar as contas
              </p>
            </div>
          </div>
        </header>

        <main className="mx-auto max-w-[1500px] space-y-7 p-5 sm:p-8">
          <section className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
              <p className="mb-2 text-sm font-medium text-blue-600">
                SEU PLANEJAMENTO FINANCEIRO
              </p>
              <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                Seu dinheiro, sob controle.
              </h1>
              <p className="mt-2 text-sm text-slate-500">
                Acompanhe suas entradas, despesas e objetivos.
              </p>
            </div>

            <div className="flex items-center gap-2 self-start rounded-xl border border-slate-200 bg-white p-1.5 shadow-sm sm:self-auto">
              <CalendarDays size={17} className="ml-2 text-slate-400" />
              <button
                aria-label="Mês anterior"
                onClick={() => setMonthOffset((value) => value - 1)}
                className="rounded-lg p-2 hover:bg-slate-100"
              >
                <ChevronLeft size={17} />
              </button>
              <span className="min-w-28 text-center text-sm font-semibold capitalize">
                {monthLabel}
              </span>
              <button
                aria-label="Próximo mês"
                onClick={() => setMonthOffset((value) => value + 1)}
                className="rounded-lg p-2 hover:bg-slate-100"
              >
                <ChevronRight size={17} />
              </button>
            </div>
          </section>

          <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <SummaryCard
              title="Entradas do mês"
              value={currency(totalIncome)}
              detail="Normal + extra"
              icon={ArrowDownRight}
              color="blue"
            />
            <SummaryCard
              title="Despesas realizadas"
              value={currency(totalExpenses)}
              detail="Lançamentos do mês"
              icon={ReceiptText}
              color="violet"
            />
            <SummaryCard
              title="Saldo após despesas"
              value={currency(available)}
              detail="Antes da transferência aos fundos"
              icon={ArrowUpRight}
              color="emerald"
            />
            <SummaryCard
              title="Saldo dos fundos"
              value={currency(totalFunds)}
              detail="Acumulado em todos os fundos"
              icon={Wallet}
              color="amber"
            />
          </section>

          <section className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
              <div className="flex items-start justify-between">
                <div>
                  <h3 className="font-bold">Distribuição da renda</h3>
                  <p className="mt-1 text-sm text-slate-500">
                    Percentuais planejados por categoria
                  </p>
                </div>
                <span className="rounded-lg bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600">
                  Planejado
                </span>
              </div>

              <div className="grid items-center gap-4 md:grid-cols-2">
                <div className="relative h-64">
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie
                        data={categories}
                        dataKey="value"
                        nameKey="name"
                        innerRadius={72}
                        outerRadius={100}
                        paddingAngle={4}
                        stroke="none"
                      >
                        {categories.map((category) => (
                          <Cell key={category.name} fill={category.color} />
                        ))}
                      </Pie>
                      <Tooltip
                        formatter={(value) => [`${value}%`, "Percentual"]}
                      />
                    </PieChart>
                  </ResponsiveContainer>
                  <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span className="text-xs text-slate-500">Distribuição</span>
                    <span className="mt-1 text-3xl font-bold">100%</span>
                    <span className="text-xs text-slate-400">
                      da renda normal
                    </span>
                  </div>
                </div>

                <div className="space-y-5">
                  {categories.map((category) => (
                    <div key={category.name}>
                      <div className="mb-2 flex items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                          <span
                            className="h-3 w-3 rounded-full"
                            style={{ backgroundColor: category.color }}
                          />
                          <span className="text-sm text-slate-600">
                            {category.name}
                          </span>
                        </div>
                        <span className="text-sm font-bold">
                          {category.value}%
                        </span>
                      </div>
                      <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div
                          className="h-full rounded-full"
                          style={{
                            width: `${category.value}%`,
                            backgroundColor: category.color,
                          }}
                        />
                      </div>
                      <p className="mt-1.5 text-xs text-slate-400">
                        {currency(category.amount)} planejados
                      </p>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
              <div className="flex items-start justify-between">
                <div>
                  <h3 className="font-bold">Resumo das categorias</h3>
                  <p className="mt-1 text-sm text-slate-500">
                    Orçamento versus despesas
                  </p>
                </div>
                <button className="text-sm font-semibold text-blue-600 hover:text-blue-700">
                  Detalhes
                </button>
              </div>

              <div className="mt-6 space-y-6">
                {categories.map((category, index) => {
                  const spent = [600, 119.9, 0][index];
                  const percent = Math.min(
                    100,
                    (spent / category.amount) * 100,
                  );

                  return (
                    <div key={category.name}>
                      <div className="mb-2 flex items-center justify-between">
                        <span className="text-sm font-medium">
                          {category.name}
                        </span>
                        <span className="text-xs text-slate-500">
                          {Math.round(percent)}% utilizado
                        </span>
                      </div>
                      <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div
                          className="h-full rounded-full"
                          style={{
                            width: `${percent}%`,
                            backgroundColor: category.color,
                          }}
                        />
                      </div>
                      <div className="mt-2 flex justify-between text-xs">
                        <span className="text-slate-500">
                          Gasto: {currency(spent)}
                        </span>
                        <span className="font-medium text-slate-700">
                          Limite: {currency(category.amount)}
                        </span>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          </section>

          <section className="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <h3 className="font-bold">Últimas despesas</h3>
                  <p className="mt-1 text-sm text-slate-500">
                    Seus lançamentos recentes
                  </p>
                </div>
                <button
                  onClick={() => setActivePage("Despesas")}
                  className="text-sm font-semibold text-blue-600 hover:text-blue-700"
                >
                  Ver todas
                </button>
              </div>

              <div className="mt-5 divide-y divide-slate-100">
                {expenses.map((expense) => (
                  <div
                    key={expense.name}
                    className="flex items-center gap-3 py-4 first:pt-0 last:pb-0"
                  >
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                      <ReceiptText size={18} />
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-semibold">
                        {expense.name}
                      </p>
                      <p className="mt-1 truncate text-xs text-slate-500">
                        {expense.category}
                      </p>
                    </div>
                    <span className="text-sm font-bold">
                      − {currency(expense.value)}
                    </span>
                  </div>
                ))}
              </div>
            </div>

            <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="font-bold">Metas em andamento</h3>
                  <p className="mt-1 text-sm text-slate-500">
                    Suas próximas conquistas
                  </p>
                </div>
                <button
                  onClick={() => setActivePage("Fundos e metas")}
                  className="text-sm font-semibold text-blue-600 hover:text-blue-700"
                >
                  Ver fundos
                </button>
              </div>

              <div className="mt-5 space-y-4">
                {goals.map((goal) => {
                  const progress = Math.min(
                    100,
                    (goal.current / goal.target) * 100,
                  );

                  return (
                    <div
                      key={goal.name}
                      className="flex gap-4 rounded-xl border border-slate-100 p-3 transition hover:border-slate-200"
                    >
                      <img
                        src={goal.image}
                        alt={goal.name}
                        className="h-24 w-24 shrink-0 rounded-xl bg-slate-100 object-cover"
                      />
                      <div className="min-w-0 flex-1 py-1">
                        <p className="text-xs font-medium text-slate-400">
                          {goal.fund}
                        </p>
                        <h4 className="mt-1 font-bold">{goal.name}</h4>
                        <p className="mt-1 text-sm">
                          <span className="font-bold">
                            {currency(goal.current)}
                          </span>
                          <span className="text-slate-400">
                            {" "}
                            / {currency(goal.target)}
                          </span>
                        </p>
                        <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                          <div
                            className="h-full rounded-full bg-emerald-500"
                            style={{ width: `${progress}%` }}
                          />
                        </div>
                        <p className="mt-1.5 text-xs text-slate-500">
                          Faltam {currency(goal.target - goal.current)}
                        </p>
                      </div>
                    </div>
                  );
                })}
              </div>

              <button className="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 py-3 text-sm font-semibold text-slate-600 transition hover:border-blue-400 hover:bg-blue-50 hover:text-blue-700">
                <Plus size={17} />
                Nova meta
              </button>
            </div>
          </section>

          <footer className="pb-2 pt-1 text-center text-xs text-slate-400">
            Financeiro · Planejamento consciente, um mês de cada vez.
          </footer>
        </main>
      </div>
    </div>
  );
}

function SummaryCard({
  title,
  value,
  detail,
  icon: Icon,
  color,
}: {
  title: string;
  value: string;
  detail: string;
  icon: typeof Wallet;
  color: "blue" | "violet" | "emerald" | "amber";
}) {
  const colors = {
    blue: "bg-blue-50 text-blue-600",
    violet: "bg-violet-50 text-violet-600",
    emerald: "bg-emerald-50 text-emerald-600",
    amber: "bg-amber-50 text-amber-600",
  };

  return (
    <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-slate-500">{title}</p>
          <p className="mt-3 text-2xl font-bold tracking-tight">{value}</p>
        </div>
        <div className={`rounded-xl p-3 ${colors[color]}`}>
          <Icon size={21} />
        </div>
      </div>
      <p className="mt-4 text-xs text-slate-400">{detail}</p>
    </div>
  );
}

export default App;
