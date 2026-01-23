
import React, { useState } from 'react';
import { 
  Search, 
  Loader2, 
  ShieldCheck, 
  ShieldAlert, 
  CheckCircle, 
  XCircle, 
  AlertTriangle, 
  Package,
  History
} from 'lucide-react';

export const PhoneSearchView: React.FC = () => {
  const [phone, setPhone] = useState('');
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState<{
    success_rate: number;
    total_orders: number;
    delivered: number;
    cancelled: number;
    source?: string;
    details?: any[];
  } | null>(null);
  const [searched, setSearched] = useState(false);

  const handleSearch = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!phone || phone.length < 10) {
      alert("Please enter a valid phone number.");
      return;
    }

    setLoading(true);
    setSearched(true);
    setData(null);

    try {
      // Clean phone number
      const cleanPhone = phone.replace(/[^0-9]/g, '');
      const res = await fetch(`api/check_fraud.php?phone=${cleanPhone}&refresh=true`);
      const result = await res.json();
      
      if (!result.error) {
        setData(result);
      } else {
        alert("Error fetching data: " + result.error);
      }
    } catch (err) {
      console.error(err);
      alert("Failed to connect to server.");
    } finally {
      setLoading(false);
    }
  };

  const getRateColor = (rate: number) => {
    if (rate >= 80) return 'text-green-600 bg-green-50 border-green-200';
    if (rate >= 50) return 'text-orange-600 bg-orange-50 border-orange-200';
    return 'text-red-600 bg-red-50 border-red-200';
  };

  return (
    <div className="space-y-6 animate-in fade-in duration-500 max-w-4xl mx-auto pb-20">
      <div className="text-center space-y-2 mb-8">
        <h2 className="text-3xl font-black text-gray-800">Global Courier Check</h2>
        <p className="text-gray-500">Instantly check delivery success rates from Steadfast & Pathao databases.</p>
      </div>

      {/* Search Box */}
      <div className="bg-white p-8 rounded-2xl shadow-lg border border-gray-100 max-w-xl mx-auto">
        <form onSubmit={handleSearch} className="flex gap-3">
          <div className="relative flex-1">
            <input 
              type="text" 
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              placeholder="Enter Phone Number (e.g. 017xxxxxxxx)" 
              className="w-full pl-10 pr-4 py-4 bg-gray-50 border border-gray-200 rounded-xl text-lg font-bold outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition-all shadow-inner"
            />
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" size={20} />
          </div>
          <button 
            type="submit" 
            disabled={loading}
            className="px-8 py-4 bg-gray-900 text-white rounded-xl font-bold shadow-lg hover:bg-black transition-all active:scale-95 disabled:opacity-50 flex items-center justify-center min-w-[120px]"
          >
            {loading ? <Loader2 size={24} className="animate-spin" /> : 'Check'}
          </button>
        </form>
      </div>

      {/* Results */}
      {data && (
        <div className="animate-in slide-in-from-bottom-4 duration-500 space-y-6">
          {/* Main Stats Card */}
          <div className={`bg-white rounded-3xl shadow-xl overflow-hidden border-2 ${getRateColor(data.success_rate).split(' ')[2]}`}>
            <div className="p-8 text-center border-b border-gray-100 relative overflow-hidden">
              <div className={`inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-widest mb-4 border ${getRateColor(data.success_rate)}`}>
                {data.success_rate >= 80 ? <ShieldCheck size={14} /> : <ShieldAlert size={14} />}
                {data.success_rate >= 80 ? 'Trusted Customer' : 'High Risk'}
              </div>
              
              <div className="relative z-10">
                <span className="text-6xl font-black text-gray-800">{data.success_rate}%</span>
                <p className="text-sm font-bold text-gray-400 uppercase tracking-widest mt-2">Delivery Success Rate</p>
              </div>
            </div>

            <div className="grid grid-cols-3 divide-x divide-gray-100 bg-gray-50">
              <div className="p-6 text-center">
                <p className="text-2xl font-black text-gray-800 flex justify-center items-center gap-2">
                  <Package size={20} className="text-blue-500" /> {data.total_orders}
                </p>
                <p className="text-[10px] font-bold text-gray-400 uppercase mt-1">Total Orders</p>
              </div>
              <div className="p-6 text-center bg-green-50/50">
                <p className="text-2xl font-black text-green-600 flex justify-center items-center gap-2">
                  <CheckCircle size={20} /> {data.delivered}
                </p>
                <p className="text-[10px] font-bold text-green-600/60 uppercase mt-1">Delivered</p>
              </div>
              <div className="p-6 text-center bg-red-50/50">
                <p className="text-2xl font-black text-red-600 flex justify-center items-center gap-2">
                  <XCircle size={20} /> {data.cancelled}
                </p>
                <p className="text-[10px] font-bold text-red-600/60 uppercase mt-1">Cancelled</p>
              </div>
            </div>
          </div>

          {/* Detailed Breakdown */}
          {data.details && data.details.length > 0 && (
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
              <div className="p-4 border-b border-gray-50 bg-gray-50/50 flex items-center gap-2">
                <History size={16} className="text-gray-400" />
                <h3 className="font-bold text-gray-700 text-sm">Source Breakdown</h3>
              </div>
              <div className="divide-y divide-gray-50">
                {data.details.map((item: any, idx: number) => (
                  <div key={idx} className="p-4 flex justify-between items-center hover:bg-gray-50 transition-colors">
                    <span className="text-sm font-bold text-gray-800">{item.courier}</span>
                    <span className="text-xs font-mono text-gray-500 bg-gray-100 px-2 py-1 rounded">{item.status}</span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {searched && !loading && !data && (
        <div className="bg-yellow-50 border border-yellow-100 rounded-2xl p-8 text-center">
          <AlertTriangle size={48} className="mx-auto text-yellow-500 mb-4" />
          <h3 className="text-lg font-bold text-yellow-800">No History Found</h3>
          <p className="text-sm text-yellow-600 mt-2">This phone number has no record in the connected courier databases.</p>
        </div>
      )}
    </div>
  );
};
