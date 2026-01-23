
import React, { useState, useMemo } from 'react';
import { StatCard } from './StatCard';
import { SellingStatistics } from './Charts';
import { 
  DollarSign, 
  CreditCard, 
  Receipt,
  Calendar,
  Package,
  Truck,
  XCircle,
  RotateCcw,
  BarChart2
} from 'lucide-react';
import { Order, DashboardStats, Expense } from '../types';
import { AreaChart, Area, ResponsiveContainer, XAxis, YAxis, Tooltip, BarChart, Bar, Cell } from 'recharts';

const StatusTrackingCard: React.FC<{ 
  label: string; 
  percentage: string; 
  count: number;
  icon: React.ReactNode; 
  iconBg: string; 
  iconColor: string;
}> = ({ label, percentage, count, icon, iconBg, iconColor }) => (
  <div className="bg-white p-5 rounded-xl border border-gray-100 flex items-center justify-between group hover:shadow-sm transition-all duration-300">
    <div className="flex items-center gap-4">
      <div className={`w-12 h-12 ${iconBg} ${iconColor} rounded-full flex items-center justify-center transition-transform group-hover:scale-110`}>
        {icon}
      </div>
      <div>
        <h4 className="font-bold text-gray-800 text-sm mb-1">{label}</h4>
        <p className="text-xs font-bold text-green-500">{percentage}</p>
      </div>
    </div>
    <div className="w-12 h-12 rounded-full border-2 border-gray-100 flex items-center justify-center relative">
      <span className="text-[10px] font-bold text-gray-800">{count}%</span>
      <svg className="absolute inset-0 w-full h-full -rotate-90">
        <circle
          cx="24"
          cy="24"
          r="22"
          stroke="currentColor"
          strokeWidth="2"
          fill="transparent"
          className="text-gray-100"
        />
        <circle
          cx="24"
          cy="24"
          r="22"
          stroke="currentColor"
          strokeWidth="2"
          fill="transparent"
          strokeDasharray={138.23}
          strokeDashoffset={138.23 - (138.23 * count) / 100}
          className={iconColor}
        />
      </svg>
    </div>
  </div>
);

interface AnalyticsViewProps {
  orders: Order[];
  stats: DashboardStats;
  expenses: Expense[];
}

export const AnalyticsView: React.FC<AnalyticsViewProps> = ({ orders, stats, expenses }) => {
  const today = new Date().toISOString().split('T')[0];
  const lastWeek = new Date();
  lastWeek.setDate(lastWeek.getDate() - 7);
  const lastWeekStr = lastWeek.toISOString().split('T')[0];

  const [dateRange, setDateRange] = useState({
    start: lastWeekStr,
    end: today
  });

  const filteredOrders = useMemo(() => {
    return orders.filter(o => {
      const orderDate = new Date(o.timestamp).toISOString().split('T')[0];
      return orderDate >= dateRange.start && orderDate <= dateRange.end;
    });
  }, [orders, dateRange]);

  const filteredExpenses = useMemo(() => {
    return (expenses || []).filter(e => {
      return e.date >= dateRange.start && e.date <= dateRange.end;
    });
  }, [expenses, dateRange]);

  const analyticsData = useMemo(() => {
    const deliveredOrders = filteredOrders.filter(o => o.status === 'completed');
    const totalDeliveredSale = deliveredOrders.reduce((acc, o) => acc + o.total, 0);
    const totalExpensesInRange = filteredExpenses.reduce((acc, e) => acc + e.amount, 0);
    const netProfit = totalDeliveredSale - totalExpensesInRange;

    const dayMap: Record<string, number> = {
      'Mon': 0, 'Tue': 0, 'Wed': 0, 'Thu': 0, 'Fri': 0, 'Sat': 0, 'Sun': 0
    };
    
    filteredOrders.forEach(o => {
      const dayName = new Date(o.timestamp).toLocaleDateString('en-US', { weekday: 'short' });
      if (dayMap[dayName] !== undefined) {
        dayMap[dayName] += o.total;
      }
    });

    const chartData = Object.entries(dayMap).map(([name, value]) => ({ name, value }));

    const totalCount = filteredOrders.length || 1;
    const getStatusCount = (s: Order['status']) => filteredOrders.filter(o => o.status === s).length;
    
    return {
      netProfit,
      expenses: totalExpensesInRange,
      deliveredSale: totalDeliveredSale,
      chartData,
      statusPercentages: {
        delivered: Math.round((getStatusCount('completed') / totalCount) * 100),
        shipping: Math.round((getStatusCount('processing') / totalCount) * 100),
        cancelled: Math.round((getStatusCount('cancelled') / totalCount) * 100),
        returned: Math.round((getStatusCount('refunded') / totalCount) * 100)
      }
    };
  }, [filteredOrders, filteredExpenses]);

  // Funnel Data (Mocked for now, but logical)
  const funnelData = useMemo(() => {
    // Traffic = Approx orders * 10 (10% conversion assumption for visualization)
    const traffic = filteredOrders.length * 10 || 100;
    const initiateCheckout = Math.round(traffic * 0.4); // 40% of traffic
    const ordersPlaced = filteredOrders.length;
    const completed = filteredOrders.filter(o => o.status === 'completed').length;

    return [
      { name: 'Traffic', value: traffic, fill: '#94a3b8' },
      { name: 'Checkout', value: initiateCheckout, fill: '#60a5fa' },
      { name: 'Orders', value: ordersPlaced, fill: '#f97316' },
      { name: 'Sales', value: completed, fill: '#22c55e' },
    ];
  }, [filteredOrders]);

  const formatDate = (dateStr: string) => {
    if (!dateStr) return 'Select Date';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  };

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <StatCard 
          title="Profit" 
          value={analyticsData.netProfit.toLocaleString()} 
          change={100} 
          icon={<DollarSign size={20} />} 
        />
        <StatCard 
          title="Total Expenses" 
          value={analyticsData.expenses.toLocaleString()} 
          change={0} 
          icon={<CreditCard size={20} />} 
        />
        <StatCard 
          title="Total Sale" 
          value={analyticsData.deliveredSale.toLocaleString()} 
          change={100} 
          icon={<Receipt size={20} />} 
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div className="lg:col-span-8 bg-white p-6 rounded-xl border border-gray-100 flex flex-col">
          <div className="flex justify-between items-center mb-6">
            <h3 className="text-sm font-bold text-gray-800">Selling Statistics</h3>
            
            <div className="flex items-center gap-2 relative group">
              <div className="flex items-center gap-2 px-3 py-1.5 border border-gray-100 rounded text-xs font-medium text-gray-400 bg-gray-50 hover:bg-gray-100 transition-colors cursor-pointer relative overflow-hidden">
                <span>{formatDate(dateRange.start)} - {formatDate(dateRange.end)}</span>
                <input 
                  type="date" 
                  className="absolute inset-0 opacity-0 cursor-pointer" 
                  value={dateRange.start}
                  onChange={(e) => setDateRange(prev => ({ ...prev, start: e.target.value }))}
                />
              </div>
              <div className="p-1.5 border border-gray-100 rounded text-gray-400 bg-gray-50 cursor-pointer hover:bg-gray-100 transition-colors relative overflow-hidden">
                <Calendar size={14} />
                <input 
                  type="date" 
                  className="absolute inset-0 opacity-0 cursor-pointer" 
                  value={dateRange.end}
                  onChange={(e) => setDateRange(prev => ({ ...prev, end: e.target.value }))}
                />
              </div>
            </div>
          </div>
          
          <div className="flex-1 h-[300px]">
            <SellingStatistics data={analyticsData.chartData} />
          </div>
        </div>

        {/* Funnel Chart Section */}
        <div className="lg:col-span-4 flex flex-col gap-4">
           <div className="bg-white p-5 rounded-xl border border-gray-100 h-full flex flex-col">
              <h3 className="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
                <BarChart2 size={16} className="text-orange-500" /> Conversion Funnel
              </h3>
              <div className="flex-1 w-full min-h-[200px]">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={funnelData} layout="vertical" margin={{ top: 5, right: 30, left: 40, bottom: 5 }}>
                    <XAxis type="number" hide />
                    <YAxis dataKey="name" type="category" tick={{fontSize: 10, fill: '#64748b'}} width={50} />
                    <Tooltip cursor={{fill: 'transparent'}} contentStyle={{borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.1)'}} />
                    <Bar dataKey="value" barSize={20} radius={[0, 4, 4, 0]}>
                      {funnelData.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={entry.fill} />
                      ))}
                    </Bar>
                  </BarChart>
                </ResponsiveContainer>
              </div>
              <div className="mt-2 text-center text-xs text-gray-400">
                 Conversion Rate: {funnelData[0].value > 0 ? ((funnelData[3].value / funnelData[0].value) * 100).toFixed(1) : 0}%
              </div>
           </div>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <StatusTrackingCard 
            label="Delivered" 
            percentage={`${analyticsData.statusPercentages.delivered}%`} 
            count={analyticsData.statusPercentages.delivered}
            icon={<Package size={20} />} 
            iconBg="bg-green-50" 
            iconColor="text-green-600" 
          />
          <StatusTrackingCard 
            label="Shipping" 
            percentage={`${analyticsData.statusPercentages.shipping}%`} 
            count={analyticsData.statusPercentages.shipping}
            icon={<Truck size={20} />} 
            iconBg="bg-blue-50" 
            iconColor="text-blue-500" 
          />
          <StatusTrackingCard 
            label="Cancelled" 
            percentage={`${analyticsData.statusPercentages.cancelled}%`} 
            count={analyticsData.statusPercentages.cancelled}
            icon={<XCircle size={20} />} 
            iconBg="bg-red-50" 
            iconColor="text-red-500" 
          />
          <StatusTrackingCard 
            label="Returned" 
            percentage={`${analyticsData.statusPercentages.returned}%`} 
            count={analyticsData.statusPercentages.returned}
            icon={<RotateCcw size={20} />} 
            iconBg="bg-orange-50" 
            iconColor="text-orange-500" 
          />
      </div>
    </div>
  );
};
