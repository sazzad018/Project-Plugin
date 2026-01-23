
import React from 'react';
import { TrendingUp, TrendingDown, Eye } from 'lucide-react';

interface StatCardProps {
  title: string;
  value: string;
  change: number;
  icon?: React.ReactNode;
  isCurrency?: boolean;
}

export const StatCard: React.FC<StatCardProps> = ({ 
  title, 
  value, 
  change, 
  icon, 
  isCurrency = true 
}) => {
  const isPositive = change >= 0;
  
  return (
    <div className="bg-white p-5 rounded-xl border border-gray-100 hover:shadow-md transition-shadow relative overflow-hidden group">
      <div className="flex justify-between items-start mb-4">
        <div>
          <h3 className="text-gray-400 text-[11px] font-bold uppercase tracking-wider mb-2">{title}</h3>
          <p className="text-2xl font-bold text-gray-800">
            {isCurrency && <span className="mr-0.5">৳</span>}
            {value}
          </p>
        </div>
        <div className={`flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded ${
          isPositive ? 'text-green-500' : 'text-red-500'
        }`}>
          {isPositive ? <TrendingUp size={12} /> : <TrendingDown size={12} />}
          {isPositive ? '+' : ''}{change.toFixed(2)}%
        </div>
      </div>
      
      <div className="flex justify-between items-end">
        <button className="text-[11px] font-semibold text-gray-400 hover:text-orange-600 flex items-center gap-1 transition-colors">
          <Eye size={12} /> See Reports
        </button>
        <div className="w-10 h-10 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 group-hover:bg-orange-50 group-hover:text-orange-600 transition-colors">
          {icon}
        </div>
      </div>

      {/* Subtle bottom border accent for active/important cards */}
      <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-orange-600 scale-x-0 group-hover:scale-x-100 transition-transform origin-left"></div>
    </div>
  );
};
