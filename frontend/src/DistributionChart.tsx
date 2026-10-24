import {
  Cell,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
} from "recharts";

type DistributionBudget = {
  category: string;
  normal_percentage: number;
  color: string;
};

export default function DistributionChart({
  budgets,
}: {
  budgets: DistributionBudget[];
}) {
  return (
    <div className="relative h-56">
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Pie
            data={budgets}
            dataKey="normal_percentage"
            nameKey="category"
            innerRadius={64}
            outerRadius={90}
            paddingAngle={3}
          >
            {budgets.map((budget) => (
              <Cell key={budget.category} fill={budget.color} />
            ))}
          </Pie>
          <Tooltip formatter={(value) => [`${value}%`, "Renda normal"]} />
        </PieChart>
      </ResponsiveContainer>
    </div>
  );
}
