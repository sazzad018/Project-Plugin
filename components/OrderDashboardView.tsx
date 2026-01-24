
import React, { useState, useRef, useEffect, useMemo } from 'react';
import { 
  Search, 
  ChevronDown, 
  Eye, 
  Trash2, 
  X, 
  ShieldCheck,
  Loader2,
  CheckCircle,
  AlertTriangle,
  HelpCircle
} from 'lucide-react';
import { Order, WCStatus } from '../types';
import { CustomDatePicker } from './CustomDatePicker';

interface OrderDashboardViewProps {
  orders: Order[];
  onViewOrder: (order: Order) => void;
  onUpdateStatus: (orderId: string, newStatus: Order['status']) => void;
}

const formatWCStatus = (status: string) => {
  return status.split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

const FraudStatus: React.FC<{ phone: string }> = ({ phone }) => {
  const [data, setData] = useState<{success_rate: number, total_orders: number, delivered: number, cancelled: number} | null>(null);
  const [loading, setLoading] = useState(false);

  const checkFraud = async (e: React.MouseEvent) => {
    e.stopPropagation();
    setLoading(true);
    try {
        const res = await fetch(`api/check_fraud.php?phone=${phone}`);
        const result = await res.json();
        if (!result.error) {
            setData(result);
        }
    } catch (e) {
        console.error(e);
    } finally {
        setLoading(false);
    }
  };

  if (loading) return <Loader2 className="animate-spin text-gray-400 mx-auto" size={16} />;

  if (!data) {
      return (
          <button onClick={checkFraud} className="text-gray-400 hover:text-blue-600 transition-colors p-1" title="Check Delivery History">
              <ShieldCheck size={18} className="mx-auto" />
          </button>
      );
  }

  // Handle case where no data was found (0 orders)
  if (data.total_orders === 0) {
      return (
          <div className="relative group inline-flex items-center gap-1 px-2 py-1 rounded border text-[10px] font-bold text-gray-500 bg-gray-50 border-gray-200 cursor-default">
              <HelpCircle size={10} />
              No Data
              <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-32 bg-gray-800 text-white text-[10px] p-2 rounded shadow-xl hidden group-hover:block z-50 text-center pointer-events-none">
                  <div className="font-bold pb-1 mb-1 text-xs">No History</div>
                  <div className="text-gray-300">No previous orders found for this number.</div>
                  <div className="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-gray-800"></div>
              </div>
          </div>
      );
  }

  const rate = parseFloat(data.success_rate as any);
  let color = 'text-orange-600 bg-orange-50 border-orange-100';
  let Icon = AlertTriangle;
  
  if (rate >= 80) {
      color = 'text-green-600 bg-green-50 border-green-100';
      Icon = CheckCircle;
  } else if (rate < 50) {
      color = 'text-red-600 bg-red-50 border-red-100';
  }

  return (
      <div className={`relative group inline-flex items-center gap-1 px-2 py-1 rounded border text-[10px] font-bold ${color} cursor-default`}>
          <Icon size={10} />
          {rate}%
          <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-32 bg-gray-800 text-white text-[10px] p-2 rounded shadow-xl hidden group-hover:block z-50 text-center pointer-events-none">
              <div className="font-bold border-b border-gray-600 pb-1 mb-1 text-xs">History</div>
              <div className="grid grid-cols-2 gap-x-2 text-left">
                  <span className="text-gray-400">Total:</span> <span className="text-right">{data.total_orders}</span>
                  <span className="text-green-400">Delivered:</span> <span className="text-right">{data.delivered}</span>
                  <span className="text-red-400">Cancel:</span> <span className="text-right">{data.cancelled}</span>
              </div>
              <div className="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-gray-800"></div>
          </div>
      </div>
  );
};

const StatusDropdown: React.FC<{ 
  currentStatus: Order['status']; 
  onSelect: (status: Order['status']) => void 
}> = ({ currentStatus, onSelect }) => {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  const statuses: WCStatus[] = [
    'pending',
    'processing',
    'on-hold',
    'completed',
    'cancelled',
    'refunded',
    'failed'
  ];

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <div className="relative inline-block" ref={dropdownRef}>
      <button 
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center justify-between gap-4 px-3 py-1.5 border border-gray-200 rounded-md text-xs text-gray-500 w-32 bg-white hover:border-orange-200 transition-colors"
      >
        Update <ChevronDown size={14} className={`transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {isOpen && (
        <div className="absolute left-0 mt-1 w-32 bg-white border border-gray-100 rounded-md shadow-lg z-50 py-1 overflow-hidden">
          {statuses.map((status) => (
            <button
              key={status}
              onClick={() => {
                onSelect(status);
                setIsOpen(false);
              }}
              className={`w-full text-left px-3 py-1.5 text-[10px] font-medium transition-colors hover:bg-orange-50 hover:text-orange-600 ${
                currentStatus === status ? 'text-orange-600 bg-orange-50/50' : 'text-gray-600'
              }`}
            >
              {formatWCStatus(status)}
            </button>
          ))}
        </div>
      )}
    </div>
  );
};

export const OrderDashboardView: React.FC<OrderDashboardViewProps> = ({ orders, onViewOrder, onUpdateStatus }) => {
  const [openInfoDropdown, setOpenInfoDropdown] = useState<string | null>(null);
  
  // Filter States
  const [searchTerm, setSearchTerm] = useState('');
  const [sortOrder, setSortOrder] = useState<'Newest' | 'Oldest'>('Newest');
  const [paymentFilter, setPaymentFilter] = useState('All');
  const [dateRange, setDateRange] = useState({ start: '', end: '' });

  // Get unique payment methods for the dropdown
  const paymentMethods = useMemo(() => {
    const methods = new Set(orders.map(o => o.paymentMethod));
    return ['All', ...Array.from(methods)];
  }, [orders]);

  // Derived filtered and sorted orders
  const displayOrders = useMemo(() => {
    let result = [...orders];

    // 1. Search Filter
    if (searchTerm) {
      result = result.filter(o => 
        o.id.toLowerCase().includes(searchTerm.toLowerCase()) ||
        o.customer.name.toLowerCase().includes(searchTerm.toLowerCase())
      );
    }

    // 2. Payment Filter
    if (paymentFilter !== 'All') {
      result = result.filter(o => o.paymentMethod === paymentFilter);
    }

    // 3. Date Range Filter
    if (dateRange.start || dateRange.end) {
      result = result.filter(o => {
        const orderTime = o.timestamp;
        const start = dateRange.start ? new Date(dateRange.start).getTime() : 0;
        const end = dateRange.end ? new Date(dateRange.end).setHours(23, 59, 59, 999) : Infinity;
        return orderTime >= start && orderTime <= end;
      });
    }

    // 4. Sort
    result.sort((a, b) => {
      return sortOrder === 'Newest' 
        ? b.timestamp - a.timestamp 
        : a.timestamp - b.timestamp;
    });

    return result;
  }, [orders, searchTerm, sortOrder, paymentFilter, dateRange]);

  const getStatusStyles = (status: string) => {
    switch (status) {
      case 'processing': return 'bg-blue-50 text-blue-400 border border-blue-100';
      case 'failed': return 'bg-red-50 text-red-400 border border-red-100';
      case 'on-hold': return 'bg-orange-50 text-orange-400 border border-orange-100';
      case 'completed': return 'bg-green-50 text-green-500 border border-green-100';
      case 'refunded': return 'bg-yellow-50 text-yellow-500 border border-yellow-100';
      case 'cancelled': return 'bg-gray-100 text-gray-500 border border-gray-200';
      default: return 'bg-gray-50 text-gray-400 border border-gray-100';
    }
  };

  const clearFilters = () => {
    setSearchTerm('');
    setPaymentFilter('All');
    setDateRange({ start: '', end: '' });
  };

  return (
    <div className="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden animate-in fade-in duration-500 mb-12">
      <div className="p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="flex items-center gap-2">
          <h2 className="text-lg font-semibold text-gray-800">All orders</h2>
          <span className="w-6 h-6 rounded-full border border-orange-500 text-orange-500 flex items-center justify-center text-xs font-bold">{displayOrders.length}</span>
          {(searchTerm || paymentFilter !== 'All' || dateRange.start) && (
            <button onClick={clearFilters} className="text-[10px] text-orange-500 font-bold hover:underline ml-2 uppercase tracking-tight flex items-center gap-1">
              <X size={10} /> Clear Filters
            </button>
          )}
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <div className="relative">
            <select 
              value={sortOrder}
              onChange={(e) => setSortOrder(e.target.value as any)}
              className="appearance-none flex items-center gap-8 px-4 py-2 border border-gray-200 rounded-md text-sm text-gray-600 hover:border-orange-500 focus:outline-none bg-white pr-10 cursor-pointer"
            >
              <option value="Newest">Newest</option>
              <option value="Oldest">Oldest</option>
            </select>
            <ChevronDown size={14} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
          </div>

          <div className="relative">
            <input 
              type="text" 
              placeholder="Search for order id" 
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="pl-4 pr-10 py-2 border border-gray-200 rounded-md text-sm w-48 lg:w-64 focus:outline-none focus:ring-1 focus:ring-orange-500/20 focus:border-orange-500"
            />
            <Search className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
          </div>

          <div className="relative">
            <select 
              value={paymentFilter}
              onChange={(e) => setPaymentFilter(e.target.value)}
              className="appearance-none flex items-center gap-12 px-4 py-2 border border-gray-200 rounded-md text-sm text-gray-600 hover:border-orange-500 focus:outline-none bg-white pr-10 cursor-pointer"
            >
              <option value="All">Payment</option>
              {paymentMethods.filter(m => m !== 'All').map(m => (
                <option key={m} value={m}>{m}</option>
              ))}
            </select>
            <ChevronDown size={14} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
          </div>

          <CustomDatePicker value={dateRange} onChange={setDateRange} />
        </div>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full text-left">
          <thead>
            <tr className="bg-gray-50/50 border-y border-gray-100">
              <th className="px-6 py-4 text-xs font-medium text-gray-400">Order Id</th>
              <th className="px-6 py-4 text-xs font-medium text-gray-400">Customers</th>
              <th className="px-6 py-4 text-xs font-medium text-gray-400">Order Date</th>
              <th className="px-6 py-4 text-xs font-medium text-gray-400">Product</th>
              <th className="px-6 py-4 text-xs font-medium text-gray-400">Payment</th>
              <th className="px-6 py-4 text-xs font-medium text-gray-400">Amount</th>
              <th className="px-6 py-4 text-xs font-medium text-gray-400 text-center">Action</th>
              <th className="px-6 py-4 text-xs font-medium text-gray-400 text-center">History</th>
              <th className="px-6 py-4 text-xs font-medium text-gray-400 text-center">Status</th>
              <th className="px-6 py-4 text-xs font-medium text-gray-400 text-right">More</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-50">
            {displayOrders.length > 0 ? displayOrders.map((order) => (
              <tr key={order.id} className="hover:bg-gray-50/30 transition-colors">
                <td className="px-6 py-5 text-sm font-medium text-gray-800">{order.id}</td>
                <td className="px-6 py-5 text-sm text-gray-700">{order.customer.name}</td>
                <td className="px-6 py-5 text-sm text-gray-600">{order.date}</td>
                <td className="px-6 py-5">
                  <div className="flex items-center gap-1">
                    {order.products.slice(0, 2).map((p, i) => (
                      <div key={i} className="w-8 h-8 rounded bg-gray-100 border border-gray-200 overflow-hidden flex items-center justify-center p-1">
                         <img src={p.img} alt="prod" className="w-full h-full object-cover rounded-sm" />
                      </div>
                    ))}
                    {order.products.length > 2 && (
                      <div className="w-8 h-8 rounded bg-blue-50 border border-blue-100 flex items-center justify-center text-[10px] font-bold text-blue-600">
                        +{order.products.length - 2}
                      </div>
                    )}
                  </div>
                </td>
                <td className="px-6 py-5 text-sm text-gray-700 uppercase">{order.paymentMethod}</td>
                <td className="px-6 py-5 text-sm font-bold text-gray-800">৳{order.total.toLocaleString()}</td>
                <td className="px-6 py-5 text-center">
                  <span className={`inline-block px-8 py-1.5 rounded-md text-xs font-medium min-w-[120px] transition-all ${getStatusStyles(order.status)}`}>
                    {formatWCStatus(order.status)}
                  </span>
                </td>
                <td className="px-6 py-5 text-center">
                  <FraudStatus phone={order.customer.phone} />
                </td>
                <td className="px-6 py-5 text-center">
                  <StatusDropdown 
                    currentStatus={order.status} 
                    onSelect={(status) => onUpdateStatus(order.id, status)} 
                  />
                </td>
                <td className="px-6 py-5 text-right relative">
                  <button 
                    onClick={() => setOpenInfoDropdown(openInfoDropdown === order.id ? null : order.id)}
                    className="inline-flex items-center gap-2 bg-orange-500 text-white px-4 py-1.5 rounded-md text-xs font-semibold hover:bg-orange-600 transition-colors"
                  >
                    Info <ChevronDown size={14} />
                  </button>
                  {openInfoDropdown === order.id && (
                    <div className="absolute right-6 top-16 w-40 bg-white border border-gray-100 rounded-lg shadow-xl z-20 py-2">
                      <button 
                        onClick={() => onViewOrder(order)}
                        className="w-full px-4 py-2 text-left text-sm text-gray-600 hover:bg-gray-50 flex items-center gap-3"
                      >
                        <Eye size={16} className="text-gray-400" /> View
                      </button>
                      <button className="w-full px-4 py-2 text-left text-sm text-gray-600 hover:bg-gray-50 flex items-center gap-3">
                        <Trash2 size={16} className="text-gray-400" /> Delete
                      </button>
                    </div>
                  )}
                </td>
              </tr>
            )) : (
              <tr>
                <td colSpan={10} className="px-6 py-12 text-center text-gray-400 italic">
                  No orders found matching your search criteria
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      <div className="h-4"></div>
    </div>
  );
};
    