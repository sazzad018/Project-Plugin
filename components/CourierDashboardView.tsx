
import React, { useState, useEffect, useMemo } from 'react';
import { 
  Wallet, 
  Truck, 
  RefreshCcw, 
  AlertCircle,
  CheckCircle2,
  PackageSearch,
  Search,
  Calendar,
  Plus,
  Zap,
  Loader2,
  X,
  User,
  Phone,
  MapPin,
  CircleDollarSign,
  AlertTriangle,
  Send,
  Map,
  ChevronDown
} from 'lucide-react';
import { 
  getCourierBalance, 
  getCourierConfig, 
  saveTrackingLocally, 
  getDeliveryStatus,
  identifyCourierByTrackingCode,
  createSteadfastOrder
} from '../services/courierService';
import { getPathaoConfig, checkPathaoConnection, getPathaoCities, getPathaoZones, getPathaoAreas, createPathaoOrder } from '../services/pathaoService';
import { Order } from '../types';

type CourierType = 'Steadfast' | 'Pathao';

export const CourierDashboardView: React.FC<{ orders: Order[]; onRefresh?: () => void }> = ({ orders, onRefresh }) => {
  const [activeCourier, setActiveCourier] = useState<CourierType>('Steadfast');
  const [balance, setBalance] = useState<number>(0);
  const [isApiActive, setIsApiActive] = useState<boolean>(false);
  const [connectionError, setConnectionError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [isConfigured, setIsConfigured] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');

  // Manual Entry States
  const [showManualModal, setShowManualModal] = useState(false);
  const [manualCourier, setManualCourier] = useState<CourierType>('Steadfast');
  const [isProcessingManual, setIsProcessingManual] = useState(false);
  const [manualData, setManualData] = useState({
    name: '',
    phone: '',
    address: '',
    amount: '',
    invoice: `M-${Math.floor(1000 + Math.random() * 9000)}`
  });

  // Pathao Location States for Manual Entry
  const [cities, setCities] = useState<any[]>([]);
  const [zones, setZones] = useState<any[]>([]);
  const [areas, setAreas] = useState<any[]>([]);
  const [selectedLoc, setSelectedLoc] = useState({ city: 0, zone: 0, area: 0 });
  const [loadingLoc, setLoadingLoc] = useState(false);
  
  const loadData = async () => {
    setLoading(true);
    setConnectionError(null);
    
    if (activeCourier === 'Steadfast') {
      const config = await getCourierConfig();
      if (config && config.apiKey) {
        setIsConfigured(true);
        try {
          const bal = await getCourierBalance();
          setBalance(bal);
          setIsApiActive(true);
        } catch (e: any) {
          setIsApiActive(false);
          setConnectionError("Steadfast API key is invalid or unauthorized.");
        }
      } else {
        setIsConfigured(false);
        setIsApiActive(false);
      }
    } else {
      const config = await getPathaoConfig();
      if (config && config.clientId && config.clientSecret) {
        setIsConfigured(true);
        const diagnostic = await checkPathaoConnection();
        setIsApiActive(diagnostic.success);
        if (!diagnostic.success) {
          setConnectionError(diagnostic.message);
        }
        setBalance(0); 
      } else {
        setIsConfigured(false);
        setIsApiActive(false);
      }
    }
    if (onRefresh) onRefresh();
    setLoading(false);
  };

  useEffect(() => {
    loadData();
  }, [activeCourier]);

  // Load Pathao cities when Pathao is selected in manual entry
  useEffect(() => {
    if (showManualModal && manualCourier === 'Pathao' && cities.length === 0) {
      loadPathaoCities();
    }
  }, [showManualModal, manualCourier]);

  const loadPathaoCities = async () => {
    setLoadingLoc(true);
    try {
      const data = await getPathaoCities();
      setCities(data);
    } catch (e) {
      console.error("Failed to load cities", e);
    } finally {
      setLoadingLoc(false);
    }
  };

  const handleManualCityChange = async (cityId: number) => {
    setSelectedLoc({ city: cityId, zone: 0, area: 0 });
    setZones([]);
    setAreas([]);
    if (!cityId) return;
    setLoadingLoc(true);
    try {
      const data = await getPathaoZones(cityId);
      setZones(data);
    } catch (e) { console.error(e); } finally { setLoadingLoc(false); }
  };

  const handleManualZoneChange = async (zoneId: number) => {
    setSelectedLoc(prev => ({ ...prev, zone: zoneId, area: 0 }));
    setAreas([]);
    if (!zoneId) return;
    setLoadingLoc(true);
    try {
      const data = await getPathaoAreas(zoneId);
      setAreas(data);
    } catch (e) { console.error(e); } finally { setLoadingLoc(false); }
  };

  const stats = useMemo(() => {
    const courierOrders = orders.filter(o => {
      if (o.courier_name) return o.courier_name === activeCourier;
      if (o.courier_tracking_code) return identifyCourierByTrackingCode(o.courier_tracking_code) === activeCourier;
      return false;
    });
    
    const activeShipments = courierOrders.filter(o => 
      o.courier_tracking_code && !['Delivered', 'Cancelled', 'Returned'].includes(o.status)
    ).length;
    return { activeShipments };
  }, [orders, activeCourier]);

  const recentConsignments = useMemo(() => {
    let filtered = orders.filter(o => {
      if (!o.courier_tracking_code) return false;
      if (o.courier_name) return o.courier_name === activeCourier;
      const detected = identifyCourierByTrackingCode(o.courier_tracking_code);
      return detected === activeCourier;
    });
    
    if (searchTerm) {
      filtered = filtered.filter(o => 
        o.id.includes(searchTerm) || 
        o.courier_tracking_code?.includes(searchTerm) ||
        o.customer.name.toLowerCase().includes(searchTerm.toLowerCase())
      );
    }
    return filtered.sort((a, b) => b.timestamp - a.timestamp);
  }, [orders, searchTerm, activeCourier]);

  const handleManualDispatch = async () => {
    if (!manualData.name || !manualData.phone || !manualData.address || !manualData.amount) {
      return alert("সবগুলো ঘর পূরণ করা বাধ্যতামূলক!");
    }

    if (manualCourier === 'Pathao') {
      if (manualData.address.length < 10) {
        return alert("পাঠাও-এর জন্য ঠিকানা কমপক্ষে ১০ অক্ষরের হতে হবে।");
      }
      if (!selectedLoc.area) {
        return alert("দয়া করে পাঠাও-এর জন্য সিটি, জোন এবং এরিয়া সিলেক্ট করুন।");
      }
    }

    setIsProcessingManual(true);
    try {
      const mockOrder: any = {
        id: manualData.invoice,
        customer: { name: manualData.name, phone: manualData.phone },
        address: manualData.address,
        total: parseFloat(manualData.amount),
        products: [{ name: "Manual Order", qty: 1 }]
      };

      if (manualCourier === 'Steadfast') {
        const res = await createSteadfastOrder(mockOrder);
        if (res.status === 200) {
          alert(`সফলভাবে স্টিডফাস্টে পাঠানো হয়েছে! ট্র্যাকিং: ${res.consignment.tracking_code}`);
          setShowManualModal(false);
          loadData();
        } else {
          alert("Steadfast Error: " + (res.message || "Failed to create consignment"));
        }
      } else {
        const res = await createPathaoOrder(mockOrder, selectedLoc);
        if (res.data && res.data.consignment_id) {
          await saveTrackingLocally(manualData.invoice, res.data.consignment_id, 'Pending', 'Pathao');
          alert(`সফলভাবে পাঠাও তে পাঠানো হয়েছে! ট্র্যাকিং: ${res.data.consignment_id}`);
          setShowManualModal(false);
          loadData();
        } else {
          const msg = res.message || (res.error?.[0]?.message) || "Pathao failed";
          alert("Pathao Error: " + msg);
        }
      }
    } catch (e: any) {
      alert("Error: " + e.message);
    } finally {
      setIsProcessingManual(false);
    }
  };

  const getStatusStyles = (status?: string) => {
    const s = status?.toLowerCase() || '';
    if (s.includes('delivered')) return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    if (s.includes('transit') || s.includes('shipping') || s.includes('picked')) return 'bg-blue-100 text-blue-700 border-blue-200';
    if (s.includes('cancelled') || s.includes('reject')) return 'bg-red-100 text-red-700 border-red-200';
    if (s.includes('return')) return 'bg-orange-100 text-orange-700 border-orange-200';
    return 'bg-gray-100 text-gray-600 border-gray-200';
  };

  const themeText = activeCourier === 'Steadfast' ? 'text-orange-600' : 'text-red-600';

  return (
    <div className="space-y-6 animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-center">
        <div className="flex p-1.5 bg-white rounded-2xl border border-gray-100 shadow-sm w-fit">
          <button 
            onClick={() => setActiveCourier('Steadfast')}
            className={`px-8 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2 ${activeCourier === 'Steadfast' ? 'bg-orange-600 text-white shadow-lg' : 'text-gray-400 hover:text-gray-600'}`}
          >
            <Truck size={18} /> Steadfast
          </button>
          <button 
            onClick={() => setActiveCourier('Pathao')}
            className={`px-8 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2 ${activeCourier === 'Pathao' ? 'bg-red-600 text-white shadow-lg' : 'text-gray-400 hover:text-gray-600'}`}
          >
            <Truck size={18} /> Pathao
          </button>
        </div>

        <button 
          onClick={() => {
            setManualCourier(activeCourier);
            setShowManualModal(true);
          }}
          className="px-6 py-2.5 bg-gray-900 text-white rounded-xl text-sm font-bold flex items-center gap-2 shadow-lg hover:bg-black transition-all active:scale-95"
        >
          <Plus size={18} /> Manual Entry
        </button>
      </div>

      <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
        <div>
          <h2 className="text-2xl font-bold text-gray-800">{activeCourier} Courier Dashboard</h2>
          <p className="text-sm text-gray-500">Manage your consignments for {activeCourier}.</p>
        </div>
        <div className="flex items-center gap-3 w-full lg:w-auto">
          <button 
            disabled={loading}
            onClick={loadData}
            className="px-5 py-2.5 bg-white border border-gray-200 rounded-xl text-gray-600 hover:text-orange-600 transition-all flex items-center gap-2 text-sm font-bold shadow-sm"
          >
            <RefreshCcw size={18} className={loading ? 'animate-spin' : ''} /> Refresh
          </button>
        </div>
      </div>

      {/* Manual Entry Modal */}
      {showManualModal && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[110] flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-in zoom-in-95 duration-200 border border-gray-100">
            <div className="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
              <div className="flex items-center gap-2 text-orange-600">
                <Zap size={20} />
                <h3 className="font-bold text-gray-800">Manual Courier Entry</h3>
              </div>
              <button onClick={() => setShowManualModal(false)} className="p-2 hover:bg-gray-200 rounded-full transition-colors">
                <X size={20} />
              </button>
            </div>
            
            <div className="p-6 space-y-4 max-h-[85vh] overflow-y-auto custom-scrollbar">
              {/* Courier Toggle in Modal */}
              <div className="flex p-1 bg-gray-100 rounded-xl">
                <button 
                  onClick={() => setManualCourier('Steadfast')}
                  className={`flex-1 py-2 rounded-lg text-xs font-bold transition-all ${manualCourier === 'Steadfast' ? 'bg-white text-orange-600 shadow-sm' : 'text-gray-400'}`}
                >
                  Steadfast
                </button>
                <button 
                  onClick={() => setManualCourier('Pathao')}
                  className={`flex-1 py-2 rounded-lg text-xs font-bold transition-all ${manualCourier === 'Pathao' ? 'bg-white text-red-600 shadow-sm' : 'text-gray-400'}`}
                >
                  Pathao
                </button>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-[10px] font-bold text-gray-400 uppercase flex items-center gap-1"><User size={10} /> Recipient Name</label>
                  <input type="text" value={manualData.name} onChange={e => setManualData({...manualData, name: e.target.value})} placeholder="Full Name" className="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:border-orange-500" />
                </div>
                <div className="space-y-1">
                  <label className="text-[10px] font-bold text-gray-400 uppercase flex items-center gap-1"><Phone size={10} /> Phone Number</label>
                  <input type="text" value={manualData.phone} onChange={e => setManualData({...manualData, phone: e.target.value})} placeholder="01xxxxxxxxx" className="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:border-orange-500" />
                </div>
              </div>

              <div className="space-y-1">
                <div className="flex justify-between">
                  <label className="text-[10px] font-bold text-gray-400 uppercase flex items-center gap-1"><MapPin size={10} /> Detailed Address</label>
                  {manualCourier === 'Pathao' && manualData.address.length < 10 && manualData.address.length > 0 && (
                    <span className="text-[9px] font-bold text-red-500 uppercase">Min 10 chars required</span>
                  )}
                </div>
                <textarea value={manualData.address} onChange={e => setManualData({...manualData, address: e.target.value})} placeholder="House, Road, Area, City..." className={`w-full p-3 bg-gray-50 border rounded-xl text-sm outline-none h-16 resize-none transition-colors ${manualCourier === 'Pathao' && manualData.address.length < 10 && manualData.address.length > 0 ? 'border-red-200' : 'border-gray-200 focus:border-orange-500'}`} />
              </div>

              {/* Pathao Specific Location Fields */}
              {manualCourier === 'Pathao' && (
                <div className="space-y-3 bg-red-50/50 p-4 rounded-xl border border-red-100 animate-in fade-in slide-in-from-top-2">
                  <p className="text-[10px] font-bold text-red-600 uppercase mb-1 flex items-center gap-2"><Map size={14} /> Pathao Delivery Locations</p>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div className="space-y-1">
                      <label className="text-[9px] font-bold text-gray-400 uppercase">City / District</label>
                      <div className="relative">
                        <select value={selectedLoc.city} onChange={e => handleManualCityChange(parseInt(e.target.value))} className="w-full appearance-none p-2 bg-white border border-red-200 rounded-lg text-xs outline-none focus:border-red-500">
                          <option value="0">Select City</option>
                          {cities.map(c => <option key={c.city_id} value={c.city_id}>{c.city_name}</option>)}
                        </select>
                        <ChevronDown size={12} className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                      </div>
                    </div>
                    <div className="space-y-1">
                      <label className="text-[9px] font-bold text-gray-400 uppercase">Zone / Thana</label>
                      <div className="relative">
                        <select disabled={!selectedLoc.city} value={selectedLoc.zone} onChange={e => handleManualZoneChange(parseInt(e.target.value))} className="w-full appearance-none p-2 bg-white border border-red-200 rounded-lg text-xs outline-none focus:border-red-500 disabled:opacity-50">
                          <option value="0">Select Zone</option>
                          {zones.map(z => <option key={z.zone_id} value={z.zone_id}>{z.zone_name}</option>)}
                        </select>
                        <ChevronDown size={12} className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                      </div>
                    </div>
                    <div className="space-y-1">
                      <label className="text-[9px] font-bold text-gray-400 uppercase">Area / Post</label>
                      <div className="relative">
                        <select disabled={!selectedLoc.zone} value={selectedLoc.area} onChange={e => setSelectedLoc(prev => ({...prev, area: parseInt(e.target.value)}))} className="w-full appearance-none p-2 bg-white border border-red-200 rounded-lg text-xs outline-none focus:border-red-500 disabled:opacity-50">
                          <option value="0">Select Area</option>
                          {areas.map(a => <option key={a.area_id} value={a.area_id}>{a.area_name}</option>)}
                        </select>
                        <ChevronDown size={12} className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                      </div>
                    </div>
                  </div>
                  {loadingLoc && <p className="text-[9px] text-red-500 animate-pulse font-bold italic">Loading locations...</p>}
                </div>
              )}

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-[10px] font-bold text-gray-400 uppercase flex items-center gap-1"><CircleDollarSign size={10} /> COD Amount</label>
                  <input type="number" value={manualData.amount} onChange={e => setManualData({...manualData, amount: e.target.value})} placeholder="৳ 0.00" className="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:border-orange-500 font-bold" />
                </div>
                <div className="space-y-1">
                  <label className="text-[10px] font-bold text-gray-400 uppercase">Invoice No.</label>
                  <input type="text" value={manualData.invoice} onChange={e => setManualData({...manualData, invoice: e.target.value})} className="w-full p-2.5 bg-gray-100 border border-gray-200 rounded-xl text-sm outline-none" />
                </div>
              </div>

              <div className="pt-4">
                <button 
                  onClick={handleManualDispatch}
                  disabled={isProcessingManual || (manualCourier === 'Pathao' && !selectedLoc.area)}
                  className={`w-full py-4 text-white font-bold rounded-xl shadow-lg transition-all flex items-center justify-center gap-3 active:scale-[0.98] disabled:opacity-50 ${manualCourier === 'Steadfast' ? 'bg-orange-600 hover:bg-orange-700 shadow-orange-100' : 'bg-red-600 hover:bg-red-700 shadow-red-100'}`}
                >
                  {isProcessingManual ? <Loader2 size={20} className="animate-spin" /> : <Send size={20} />}
                  Send to {manualCourier}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {!isConfigured && (
        <div className="bg-amber-50 border border-amber-200 p-6 rounded-2xl flex items-center gap-4 text-amber-800 shadow-sm">
          <AlertCircle size={24} className="shrink-0 text-amber-500" />
          <div>
            <p className="text-sm font-bold">{activeCourier} Not Configured</p>
            <p className="text-xs opacity-80">Check Settings (Gear Icon) to add your {activeCourier} API credentials.</p>
          </div>
        </div>
      )}

      {connectionError && (
        <div className="bg-red-50 border border-red-100 p-4 rounded-xl flex items-center gap-3 text-red-700 shadow-sm animate-in slide-in-from-top-2">
          <AlertTriangle size={20} className="shrink-0 text-red-500" />
          <div className="flex-1">
            <p className="text-xs font-bold uppercase tracking-tight">Connection Problem Detected</p>
            <p className="text-xs opacity-90">{connectionError}</p>
          </div>
          <button onClick={loadData} className="text-[10px] font-bold underline uppercase">Try Again</button>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-6 group hover:border-orange-200 transition-all">
          <div className={`w-14 h-14 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform ${activeCourier === 'Steadfast' ? 'bg-orange-50 text-orange-600' : 'bg-red-50 text-red-600'}`}>
            <Wallet size={28} />
          </div>
          <div>
            <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">
              {activeCourier === 'Steadfast' ? 'Current Balance' : 'Account Status'}
            </p>
            <p className="text-3xl font-black text-gray-800">
              {activeCourier === 'Steadfast' ? `৳${balance.toLocaleString()}` : (isApiActive ? 'ACTIVE' : 'OFFLINE')}
            </p>
          </div>
        </div>

        <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-6 group hover:border-blue-200 transition-all">
          <div className="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
            <Truck size={28} />
          </div>
          <div>
            <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Active Shipments</p>
            <p className="text-3xl font-black text-gray-800">{stats.activeShipments}</p>
          </div>
        </div>

        <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-6 group hover:border-emerald-200 transition-all">
          <div className={`w-14 h-14 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform ${isApiActive ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'}`}>
            <CheckCircle2 size={28} />
          </div>
          <div>
            <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Connection</p>
            <p className={`text-xl font-black uppercase ${isApiActive ? 'text-emerald-600' : 'text-red-500'}`}>
              {isApiActive ? 'CONNECTED' : 'DISCONNECTED'}
            </p>
          </div>
        </div>
      </div>

      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div className="p-6 border-b border-gray-50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-gray-50/30">
          <div className="flex items-center gap-3">
            <div className={`p-2 rounded-lg ${themeText} ${activeCourier === 'Steadfast' ? 'bg-orange-100' : 'bg-red-100'}`}>
              <PackageSearch size={20} />
            </div>
            <div>
              <h3 className="font-bold text-gray-800">{activeCourier} Consignments</h3>
              <p className="text-[10px] text-gray-400 font-bold uppercase">Showing orders filtered for {activeCourier}</p>
            </div>
          </div>
          <div className="relative w-full md:w-64">
            <input 
              type="text" 
              placeholder="Search..." 
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full pl-9 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-xs outline-none focus:border-orange-500 transition-all"
            />
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
          </div>
        </div>
        
        {recentConsignments.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr className="bg-white text-[10px] uppercase font-bold text-gray-400 tracking-wider">
                  <th className="px-6 py-4 border-b border-gray-50">Order & Date</th>
                  <th className="px-6 py-4 border-b border-gray-50">Customer Details</th>
                  <th className="px-6 py-4 border-b border-gray-50">Tracking Info</th>
                  <th className="px-6 py-4 border-b border-gray-50">Courier Status</th>
                  <th className="px-6 py-4 border-b border-gray-50 text-right">COD Amount</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {recentConsignments.map(order => (
                  <tr key={order.id} className="hover:bg-gray-50/50 transition-colors group">
                    <td className="px-6 py-4">
                      <div className="flex flex-col">
                        <span className="text-sm font-bold text-gray-800">#{order.id.slice(-6)}</span>
                        <div className="flex items-center gap-1 text-[10px] text-gray-400 font-medium">
                          <Calendar size={10} /> {new Date(order.timestamp).toLocaleDateString()}
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex flex-col">
                        <span className="text-xs font-bold text-gray-700">{order.customer.name}</span>
                        <span className="text-[10px] text-gray-400 font-mono">{order.customer.phone}</span>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className={`inline-flex items-center gap-2 text-xs font-mono font-bold px-3 py-1.5 rounded-lg border ${activeCourier === 'Steadfast' ? 'text-blue-600 bg-blue-50 border-blue-100' : 'text-red-600 bg-red-50 border-red-100'}`}>
                        {order.courier_tracking_code}
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`text-[10px] font-bold uppercase px-3 py-1.5 rounded-full border ${getStatusStyles(order.courier_status)}`}>
                        {order.courier_status || 'Processing'}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <span className="text-sm font-black text-gray-800">৳{order.total.toLocaleString()}</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="p-20 text-center">
            <p className="text-gray-400 text-sm">No {activeCourier} consignments found.</p>
          </div>
        )}
      </div>
    </div>
  );
};
