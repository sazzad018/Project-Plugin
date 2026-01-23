
import React, { useState, useEffect } from 'react';
import { 
  Phone, 
  MessageSquare, 
  CheckCircle, 
  Trash2, 
  Clock, 
  ShoppingCart, 
  MapPin,
  RefreshCcw,
  Loader2,
  Search
} from 'lucide-react';
import { Lead } from '../types';

export const LeadsView: React.FC = () => {
  const [leads, setLeads] = useState<Lead[]>([]);
  const [loading, setLoading] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');

  const fetchLeads = async () => {
    setLoading(true);
    try {
      const res = await fetch('api/live_capture.php');
      const data = await res.json();
      if (Array.isArray(data)) setLeads(data);
    } catch (e) {
      console.error("Fetch leads failed", e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLeads();
    // Auto refresh every 30 seconds for live feel
    const interval = setInterval(fetchLeads, 30000);
    return () => clearInterval(interval);
  }, []);

  const filteredLeads = leads.filter(l => 
    l.phone.includes(searchTerm) || 
    l.customer_name.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const handleCall = (phone: string) => {
    window.open(`tel:${phone}`, '_self');
  };

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      <div className="flex justify-between items-center">
        <div>
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 bg-red-500 rounded-full animate-pulse"></div>
            <h2 className="text-2xl font-bold text-gray-800">Live Incomplete Orders</h2>
          </div>
          <p className="text-sm text-gray-500">Real-time data capture from checkout forms before submission.</p>
        </div>
        <button 
          onClick={fetchLeads}
          className="p-2 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 text-gray-600 transition-all shadow-sm"
        >
          <RefreshCcw size={20} className={loading ? 'animate-spin' : ''} />
        </button>
      </div>

      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div className="p-4 border-b border-gray-50 bg-gray-50/50 flex justify-between items-center">
          <div className="relative w-72">
            <input 
              type="text" 
              placeholder="Search leads..." 
              value={searchTerm}
              onChange={e => setSearchTerm(e.target.value)}
              className="w-full pl-9 pr-4 py-2 bg-white border border-gray-200 rounded-lg text-xs outline-none focus:border-orange-500"
            />
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
          </div>
          <span className="text-xs font-bold text-orange-600 uppercase bg-orange-50 px-3 py-1 rounded-full">
            {filteredLeads.length} Potential Sales
          </span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left">
            <thead>
              <tr className="bg-white text-[10px] uppercase font-bold text-gray-400 tracking-wider">
                <th className="px-6 py-4 border-b border-gray-50">Customer</th>
                <th className="px-6 py-4 border-b border-gray-50">Cart Info</th>
                <th className="px-6 py-4 border-b border-gray-50">Location</th>
                <th className="px-6 py-4 border-b border-gray-50">Captured At</th>
                <th className="px-6 py-4 border-b border-gray-50 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50 text-sm">
              {filteredLeads.map(lead => (
                <tr key={lead.id} className="hover:bg-orange-50/20 transition-colors group">
                  <td className="px-6 py-4">
                    <div className="font-bold text-gray-800">{lead.customer_name || 'Guest User'}</div>
                    <div className="text-xs text-gray-500 font-mono mt-1">{lead.phone}</div>
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-2 mb-1">
                      <ShoppingCart size={14} className="text-orange-500" />
                      <span className="font-bold text-gray-800">৳{lead.cart_total}</span>
                    </div>
                    <div className="text-[10px] text-gray-400 max-w-[200px] truncate">
                      {lead.cart_items.length > 0 ? lead.cart_items.map((i: any) => `${i.product_id} (x${i.quantity})`).join(', ') : 'Empty Cart'}
                    </div>
                  </td>
                  <td className="px-6 py-4 text-xs text-gray-600">
                    <div className="flex items-start gap-1">
                      <MapPin size={12} className="mt-0.5 text-gray-400" />
                      <span className="max-w-[150px] truncate block">{lead.address || 'Unknown'}</span>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-1 text-xs text-gray-500">
                      <Clock size={12} />
                      {new Date(lead.updated_at).toLocaleString()}
                    </div>
                    <div className="text-[9px] font-bold text-green-600 mt-1 uppercase">Live Capture</div>
                  </td>
                  <td className="px-6 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button onClick={() => handleCall(lead.phone)} className="p-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors" title="Call Now">
                        <Phone size={16} />
                      </button>
                      <button className="p-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors" title="Send SMS">
                        <MessageSquare size={16} />
                      </button>
                      <button className="p-2 bg-gray-50 text-gray-400 rounded-lg hover:bg-red-50 hover:text-red-500 transition-colors">
                        <Trash2 size={16} />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {filteredLeads.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-6 py-12 text-center text-gray-400 italic">
                    No leads captured yet. Wait for customers to type in checkout.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};
