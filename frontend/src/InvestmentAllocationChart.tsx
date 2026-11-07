import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from "recharts";

type InvestmentAllocation = {
  type: string;
  name: string;
  value: number;
  percentage: number;
  color: string;
};

export default function InvestmentAllocationChart({
  data,
}: {
  data: InvestmentAllocation[];
}) {
  return (
    <div className="relative h-64">
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Pie
            data={data}
            dataKey="value"
            nameKey="name"
            innerRadius={66}
            outerRadius={104}
            paddingAngle={3}
          >
            {data.map((item) => (
              <Cell key={item.type} fill={item.color} />
            ))}
          </Pie>
          <Tooltip
            formatter={(_value, _name, item) => [
              `${item.payload.percentage.toLocaleString("pt-BR", { maximumFractionDigits: 2 })}% (${Number(item.payload.value).toLocaleString("pt-BR", { style: "currency", currency: "BRL" })})`,
              item.payload.name,
            ]}
          />
        </PieChart>
      </ResponsiveContainer>
    </div>
  );
}
