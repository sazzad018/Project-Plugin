
import React, { useState, useEffect } from 'react';
import { 
  Printer, 
  Info, 
  MapPin, 
  Mail, 
  Phone, 
  Search,
  Package,
  Truck,
  Heart,
  Send,
  Loader2,
  CheckCircle,
  ExternalLink,
  ChevronDown,
  X,
  Map,
  ShieldAlert,
  ShieldCheck,
  AlertTriangle
} from 'lucide-react';
import { Order, WCStatus } from '../types';
import { createSteadfastOrder, saveTrackingLocally } from '../services/courierService';
import { getPathaoCities, getPathaoZones, getPathaoAreas, createPathaoOrder } from '../services/pathaoService';

interface OrderDetailViewProps {
  order: Order;
  onBack: () => void;
}

export const OrderDetailView: React.FC<OrderDetailViewProps> = ({ order, onBack }) => {
  const [isShipping, setIsShipping] = useState(false);
  const [shippingResult, setShippingResult] = useState<{tracking: string, courier: string} | null>(
    order.courier_tracking_code ? {tracking: order.courier_tracking_code, courier: order.courier_name || 'Steadfast'} : null
  );

  // Fraud Check State
  const [fraudData, setFraudData] = useState<{success_rate: number, total_orders: number, delivered: number, cancelled: number} | null>(null);
  const [loadingFraud, setLoadingFraud] = useState(false);

  // Pathao Modal State
  const [showPathaoModal, setShowPathaoModal] = useState(false);
  const [cities, setCities] = useState<any[]>([]);
  const [zones, setZones] = useState<any[]>([]);
  const [areas, setAreas] = useState<any[]>([]);
  const [selectedLoc, setSelectedLoc] = useState({ city: 0, zone: 0, area: 0 });
  const [loadingLoc, setLoadingLoc] = useState(false);

  useEffect(() => {
    if (showPathaoModal) {
      loadCities();
    }
  }, [showPathaoModal]);

  const loadCities = async () => {
    setLoadingLoc(true);
    try {
      const data = await getPathaoCities();
      setCities(data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoadingLoc(false);
    }
  };

  const handleCityChange = async (cityId: number) => {
    setSelectedLoc({ city: cityId, zone: 0, area: 0 });
    setZones([]);
    setAreas([]);
    setLoadingLoc(true);
    try {
      const data = await getPathaoZones(cityId);
      setZones(data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoadingLoc(false);
    }
  };

  const handleZoneChange = async (zoneId: number) => {
    setSelectedLoc(prev => ({ ...prev, zone: zoneId, area: 0 }));
    setAreas([]);
    setLoadingLoc(true);
    try {
      const data = await getPathaoAreas(zoneId);
      setAreas(data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoadingLoc(false);
    }
  };

  const isStepCompleted = (step: 'placed' | 'packaging' | 'shipping' | 'delivered') => {
    /* Fixed: Using standard lowercase WCStatus values as keys in Partial<Record<WCStatus, string[]>> */
    const statusMap: Partial<Record<WCStatus, string[]>> = {
      'pending': ['placed'],
      'on-hold': ['placed', 'packaging'],
      'processing': ['placed', 'packaging', 'shipping'],
      'completed': ['placed', 'packaging', 'shipping', 'delivered'],
      'refunded': ['placed', 'packaging', 'shipping'],
      'cancelled': ['placed'],
      'failed': ['placed']
    };
    return statusMap[order.status]?.includes(step) || false;
  };

  const handleSendToSteadfast = async () => {
    if (shippingResult) return;
    setIsShipping(true);
    try {
      const res = await createSteadfastOrder(order);
      if (res.status === 200) {
        setShippingResult({
          tracking: res.consignment.tracking_code,
          courier: 'Steadfast'
        });
        await saveTrackingLocally(order.id, res.consignment.tracking_code, res.consignment.status, 'Steadfast');
        alert("Sent to Steadfast Courier Successfully!");
      } else {
        alert("Error: " + (res.message || "Failed to create consignment"));
      }
    } catch (error: any) {
      alert("Courier Config Error: Please check your API keys in Settings.");
    } finally {
      setIsShipping(false);
    }
  };

  const handlePathaoSubmit = async () => {
    if (!selectedLoc.city || !selectedLoc.zone || !selectedLoc.area) {
      alert("Please select City, Zone and Area.");
      return;
    }
    setIsShipping(true);
    try {
      const res = await createPathaoOrder(order, selectedLoc);
      
      if (res.data && res.data.consignment_id) {
        const tracking = res.data.consignment_id;
        setShippingResult({ tracking, courier: 'Pathao' });
        await saveTrackingLocally(order.id, tracking, 'Pending', 'Pathao');
        alert(`Sent to Pathao Successfully! Tracking: ${tracking}`);
        setShowPathaoModal(false);
      } else {
        const errorMsg = res.message || (res.error && res.error[0] ? res.error[0].message : "Failed to create Pathao order");
        alert("Pathao API Error: " + errorMsg);
      }
    } catch (e: any) {
      alert("Pathao Error: " + e.message);
    } finally {
      setIsShipping(false);
    }
  };

  const checkFraud = async () => {
    setLoadingFraud(true);
    try {
        const res = await fetch(`api/check_fraud.php?phone=${order.customer.phone}`);
        const data = await res.json();
        if (data && !data.error) {
            setFraudData(data);
        } else {
            alert("Could not fetch data or no history found.");
        }
    } catch (e) {
        console.error(e);
        alert("API Error");
    } finally {
        setLoadingFraud(false);
    }
  };

  return (
    <div className="space-y-6 animate-in fade-in slide-in-from-right-4 duration-500 pb-12">
      {/* Header Info */}
      <div className="bg-white p-6 rounded-lg border border-gray-100 shadow-sm flex justify-between items-start">
        <div className="space-y-4 w-full">
          <div className="flex justify-between items-center">
            <div className="flex items-center gap-4">
              <h2 className="text-lg font-medium">
                <span className="text-orange-600 font-bold">Order ID: {order.id}</span>
                <span className="text-gray-400 ml-4">Date: {order.date}</span>
              </h2>
              {shippingResult && (
                <div className="flex items-center gap-2 bg-blue-50 text-blue-600 px-3 py-1 rounded-full text-[10px] font-bold border border-blue-100">
                  <Truck size={12} /> {shippingResult.courier}: {shippingResult.tracking}
                </div>
              )}
            </div>
            <div className="flex gap-2">
              <div className="relative group">
                <button 
                  disabled={!!shippingResult || isShipping}
                  className={`px-6 py-2 rounded-lg text-sm font-bold flex items-center gap-2 shadow-md transition-all ${
                    shippingResult 
                    ? 'bg-green-100 text-green-600 cursor-default' 
                    : 'bg-orange-600 text-white hover:bg-orange-700 disabled:opacity-50'
                  }`}
                >
                  {isShipping ? <Loader2 size={16} className="animate-spin" /> : shippingResult ? <CheckCircle size={16} /> : <Send size={16} />}
                  {shippingResult ? `Sent to ${shippingResult.courier}` : 'Send to Courier'}
                  {!shippingResult && <ChevronDown size={14} />}
                </button>
                
                {!shippingResult && !isShipping && (
                  <div className="absolute right-0 mt-1 w-48 bg-white border border-gray-100 rounded-xl shadow-xl z-50 py-2 hidden group-hover:block animate-in fade-in slide-in-from-top-2 duration-200">
                    <button onClick={handleSendToSteadfast} className="w-full px-4 py-2.5 text-left text-xs font-bold text-gray-700 hover:bg-orange-50 hover:text-orange-600 flex items-center gap-2">
                      <Truck size={14} /> Steadfast Courier
                    </button>
                    <button onClick={() => setShowPathaoModal(true)} className="w-full px-4 py-2.5 text-left text-xs font-bold text-gray-700 hover:bg-orange-50 hover:text-orange-600 flex items-center gap-2">
                      <Truck size={14} /> Pathao Courier
                    </button>
                  </div>
                )}
              </div>
              
              <button className="bg-white border border-gray-200 text-gray-600 px-6 py-2 rounded-lg text-sm font-bold flex items-center gap-2 shadow-sm hover:bg-gray-50 transition-colors">
                <Printer size={16} /> Print Invoice
              </button>
            </div>
          </div>

          <div className="flex items-center gap-2 text-xs text-gray-400 italic">
            <Info size={14} />
            Orders in route to the customer destination.
            <div className="ml-auto flex gap-2">
              {order.status === 'refunded' && <span className="bg-red-50 text-red-500 px-3 py-1 rounded border border-red-100 font-medium uppercase text-[10px]">Returned</span>}
              {order.status === 'completed' && <span className="bg-blue-50 text-blue-500 px-3 py-1 rounded border border-blue-100 font-medium uppercase text-[10px]">Delivered</span>}
              {order.status === 'cancelled' && <span className="bg-red-100 text-red-700 px-3 py-1 rounded border border-red-200 font-medium uppercase text-[10px]">Cancelled</span>}
            </div>
          </div>

          {/* Progress Tracker */}
          <div className="relative pt-8 pb-4 px-4">
            <div className="absolute top-[52px] left-12 right-12 h-1 bg-gray-100"></div>
            <div 
              className="absolute top-[52px] left-12 h-1 bg-orange-500 transition-all duration-1000"
              style={{ width: 
                order.status === 'pending' ? '0%' : 
                order.status === 'on-hold' ? '33%' : 
                order.status === 'processing' ? '66%' : 
                order.status === 'completed' ? '100%' : '0%' 
              }}
            ></div>
            
            <div className="relative flex justify-between">
              {/* Step 1: Placed */}
              <div className="flex flex-col items-center gap-2">
                <div className={`w-10 h-10 rounded-lg flex items-center justify-center z-10 shadow-sm ${isStepCompleted('placed') ? 'bg-orange-600 text-white' : 'bg-white border-2 border-gray-100 text-gray-300'}`}>
                  <Package size={20} />
                </div>
                <div className="text-center">
                  <p className={`text-[10px] font-bold uppercase ${isStepCompleted('placed') ? 'text-orange-600' : 'text-gray-400'}`}>Order placed</p>
                  <p className="text-[10px] text-gray-400">{order.statusHistory.placed}</p>
                </div>
              </div>

              {/* Step 2: Packaging */}
              <div className="flex flex-col items-center gap-2">
                <div className={`w-10 h-10 rounded-lg flex items-center justify-center z-10 shadow-sm ${isStepCompleted('packaging') ? 'bg-orange-600 text-white' : 'bg-white border-2 border-gray-100 text-gray-300'}`}>
                  <Package size={20} />
                </div>
                <div className="text-center">
                  <p className={`text-[10px] font-bold uppercase ${isStepCompleted('packaging') ? 'text-orange-600' : 'text-gray-400'}`}>Packaging</p>
                  {order.statusHistory.packaging && <p className="text-[10px] text-gray-400">{order.statusHistory.packaging}</p>}
                </div>
              </div>

              {/* Step 3: Shipping */}
              <div className="flex flex-col items-center gap-2">
                <div className={`w-10 h-10 rounded-lg flex items-center justify-center z-10 shadow-sm ${isStepCompleted('shipping') ? 'bg-orange-600 text-white' : 'bg-white border-2 border-gray-100 text-gray-300'}`}>
                  <Truck size={20} />
                </div>
                <div className="text-center">
                  <p className={`text-[10px] font-bold uppercase ${isStepCompleted('shipping') ? 'text-orange-600' : 'text-gray-400'}`}>Shipping</p>
                  {order.statusHistory.shipping && <p className="text-[10px] text-gray-400">{order.statusHistory.shipping}</p>}
                </div>
              </div>

              {/* Step 4: Delivered */}
              <div className="flex flex-col items-center gap-2">
                <div className={`w-10 h-10 rounded-full flex items-center justify-center z-10 shadow-sm ${isStepCompleted('delivered') ? 'bg-orange-600 text-white' : 'bg-white border-2 border-dashed border-gray-200 text-gray-300'}`}>
                  <Heart size={20} />
                </div>
                <div className="text-center">
                  <p className={`text-[10px] font-bold uppercase ${isStepCompleted('delivered') ? 'text-orange-600' : 'text-gray-400'}`}>Delivered</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Pathao Modal */}
      {showPathaoModal && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[110] flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in-95 duration-200">
            <div className="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
              <div className="flex items-center gap-2">
                <Map className="text-orange-600" size={20} />
                <div>
                  <h3 className="font-bold text-gray-800">Pathao Delivery Setup</h3>
                  <p className="text-[10px] text-gray-400 font-bold uppercase">Select Area for ID: {order.id}</p>
                </div>
              </div>
              <button onClick={() => setShowPathaoModal(false)} className="p-2 hover:bg-gray-200 rounded-full transition-colors">
                <X size={20} />
              </button>
            </div>
            
            <div className="p-6 space-y-4">
              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase">City</label>
                <select 
                  className="w-full p-2.5 bg-gray-50 border border-gray-100 rounded-lg text-sm outline-none focus:border-orange-500"
                  value={selectedLoc.city}
                  onChange={(e) => handleCityChange(parseInt(e.target.value))}
                >
                  <option value="0">Select City</option>
                  {cities.map(c => <option key={c.city_id} value={c.city_id}>{c.city_name}</option>)}
                </select>
              </div>

              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase">Zone</label>
                <select 
                  className="w-full p-2.5 bg-gray-50 border border-gray-100 rounded-lg text-sm outline-none focus:border-orange-500 disabled:opacity-50"
                  disabled={!selectedLoc.city}
                  value={selectedLoc.zone}
                  onChange={(e) => handleZoneChange(parseInt(e.target.value))}
                >
                  <option value="0">Select Zone</option>
                  {zones.map(z => <option key={z.zone_id} value={z.zone_id}>{z.zone_name}</option>)}
                </select>
              </div>

              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase">Area</label>
                <select 
                  className="w-full p-2.5 bg-gray-50 border border-gray-100 rounded-lg text-sm outline-none focus:border-orange-500 disabled:opacity-50"
                  disabled={!selectedLoc.zone}
                  value={selectedLoc.area}
                  onChange={(e) => setSelectedLoc(prev => ({...prev, area: parseInt(e.target.value)}))}
                >
                  <option value="0">Select Area</option>
                  {areas.map(a => <option key={a.area_id} value={a.area_id}>{a.area_name}</option>)}
                </select>
              </div>

              <div className="pt-4 flex gap-3">
                <button onClick={() => setShowPathaoModal(false)} className="flex-1 py-3 bg-gray-100 text-gray-600 font-bold rounded-xl">Cancel</button>
                <button 
                  onClick={handlePathaoSubmit}
                  disabled={isShipping || !selectedLoc.area}
                  className="flex-1 py-3 bg-orange-600 text-white font-bold rounded-xl shadow-lg hover:bg-orange-700 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                >
                  {isShipping ? <Loader2 size={16} className="animate-spin" /> : <Send size={16} />}
                  Send to Pathao
                </button>
              </div>
            </div>
            {loadingLoc && (
              <div className="absolute inset-0 bg-white/50 flex items-center justify-center">
                <Loader2 size={24} className="animate-spin text-orange-600" />
              </div>
            )}
          </div>
        </div>
      )}

      <div className="grid grid-cols-12 gap-6">
        {/* Left: Order Items */}
        <div className="col-span-12 lg:col-span-9 bg-white rounded-lg border border-gray-100 shadow-sm overflow-hidden">
          <div className="p-4 border-b border-gray-50 bg-gray-50/30 flex justify-between items-center">
            <h3 className="text-sm font-bold text-gray-700">Order Item</h3>
            {shippingResult && (
              <a 
                href={shippingResult.courier === 'Pathao' ? '#' : `https://steadfast.com.bd/tracking/${shippingResult.tracking}`} 
                target="_blank" 
                rel="noreferrer"
                className="text-xs font-bold text-blue-600 hover:underline flex items-center gap-1"
              >
                Track in {shippingResult.courier} <ExternalLink size={12} />
              </a>
            )}
          </div>
          <table className="w-full text-left">
            <thead className="bg-gray-50/50">
              <tr>
                <th className="px-6 py-3 text-xs font-bold text-gray-400 uppercase text-center border-b border-gray-100">Product Name</th>
                <th className="px-6 py-3 text-xs font-bold text-gray-400 uppercase text-center border-b border-l border-gray-100">Unit Price</th>
                <th className="px-6 py-3 text-xs font-bold text-gray-400 uppercase text-center border-b border-l border-gray-100">QTY</th>
                <th className="px-6 py-3 text-xs font-bold text-gray-400 uppercase text-center border-b border-l border-gray-100">Total</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
              {order.products.map((p, i) => (
                <tr key={i}>
                  <td className="px-6 py-4 flex items-center gap-4">
                    <img src={p.img} alt={p.name} className="w-12 h-12 rounded border border-gray-100 p-1 object-cover" />
                    <div className="max-w-xs">
                      <p className="text-xs font-medium text-gray-800 line-clamp-1">{p.name}</p>
                      <p className="text-[10px] font-bold text-green-500 uppercase">{p.brand}</p>
                    </div>
                  </td>
                  <td className="px-6 py-4 text-center text-sm text-gray-600 border-l border-gray-100">৳{p.price.toLocaleString()}</td>
                  <td className="px-6 py-4 text-center text-sm text-gray-600 border-l border-gray-100">{p.qty}</td>
                  <td className="px-6 py-4 text-center text-sm font-bold text-gray-800 border-l border-gray-100">৳{(p.price * p.qty).toLocaleString()}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <div className="p-6 border-t border-gray-100 flex justify-end">
            <div className="w-64 space-y-3">
              <div className="flex justify-between text-sm">
                <span className="text-gray-500">Subtotal</span>
                <span className="font-bold text-gray-800">৳{order.subtotal.toLocaleString()}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-gray-500">Shipping Charge</span>
                <span className="font-bold text-gray-800">৳{order.shippingCharge}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-gray-500">Discount</span>
                <span className="font-bold text-green-500">Saved (৳{order.discount.toLocaleString()})</span>
              </div>
              <div className="flex justify-between border-t border-gray-100 pt-3">
                <span className="font-bold text-gray-800">Total</span>
                <span className="font-bold text-gray-800 text-lg">৳{order.total.toLocaleString()}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Right: Sidebar Info */}
        <div className="col-span-12 lg:col-span-3 space-y-4">
          <div className="bg-white p-5 rounded-lg border border-gray-100 shadow-sm">
            <div className="flex justify-between items-center mb-4">
              <h4 className="text-sm font-bold text-gray-500 italic">Customer Details</h4>
              <button className="text-[10px] bg-orange-500 text-white px-2 py-0.5 rounded font-bold uppercase">View Profile</button>
            </div>
            <div className="flex items-center gap-4 mb-4">
              <img src={order.customer.avatar} alt={order.customer.name} className="w-12 h-12 rounded-lg object-cover" />
              <div>
                <p className="text-sm font-bold text-gray-800">{order.customer.name}</p>
                <p className="text-[10px] text-green-500 font-bold uppercase">User</p>
              </div>
            </div>
            <div className="space-y-2 text-xs text-gray-500">
              <p className="font-bold text-green-500">Orders : {order.customer.orderCount}</p>
              <div className="flex items-center gap-2">
                <Mail size={12} className="text-gray-400" /> {order.customer.email}
              </div>
              <div className="flex items-center gap-2">
                <Phone size={12} className="text-gray-400" /> {order.customer.phone}
              </div>
            </div>
          </div>

          <div className="bg-white p-5 rounded-lg border border-gray-100 shadow-sm">
            <h4 className="text-sm font-bold text-orange-500 italic mb-4">Delivery Addresses</h4>
            <div className="flex gap-3 mb-4">
              <MapPin size={16} className="text-gray-300 shrink-0" />
              <p className="text-xs text-gray-500 leading-relaxed">{order.address}</p>
            </div>
            <div className="flex justify-between items-center text-xs text-gray-500">
              <div className="flex items-center gap-1 font-medium text-gray-700">
                {order.customer.name}
              </div>
              <div className="flex items-center gap-1">
                <Phone size={12} className="text-gray-300" /> {order.customer.phone}
              </div>
            </div>
          </div>

          <div className="bg-white p-5 rounded-lg border border-gray-100 shadow-sm">
            <h4 className="text-sm font-bold text-orange-500 italic mb-4">Payment Details</h4>
            <div className="space-y-3">
              <div className="flex justify-between text-xs">
                <span className="text-gray-500">Payment Method:</span>
                <span className="font-bold text-gray-800 uppercase">{order.paymentMethod}</span>
              </div>
              <div className="flex justify-between text-xs">
                <span className="text-gray-500">Total Amount:</span>
                <span className="font-bold text-gray-800">৳{order.total.toLocaleString()}</span>
              </div>
            </div>
          </div>

          <div className="bg-white p-5 rounded-lg border border-gray-100 shadow-sm">
            <div className="flex items-center gap-2 mb-4">
              <Search size={16} className="text-gray-400" />
              <h4 className="text-sm font-medium text-gray-700">Check Customer Fraud History</h4>
            </div>
            {!fraudData ? (
                <button 
                    onClick={checkFraud}
                    disabled={loadingFraud}
                    className="w-full py-2 border border-red-400 text-red-500 rounded-full text-xs font-bold hover:bg-red-50 transition-colors flex items-center justify-center gap-2"
                >
                    {loadingFraud ? <Loader2 size={12} className="animate-spin" /> : null}
                    Check Now
                </button>
            ) : (
                <div className="space-y-3 animate-in fade-in">
                    <div className="flex flex-col gap-3 p-3 bg-gray-50 rounded-lg">
                        <div className="flex justify-between items-start">
                            <div className="flex items-center gap-2">
                                {fraudData.success_rate > 80 ? (
                                    <ShieldCheck size={24} className="text-green-600" />
                                ) : (
                                    <ShieldAlert size={24} className="text-red-600" />
                                )}
                                <div>
                                    <p className="text-[10px] font-bold text-gray-500 uppercase">Success Rate</p>
                                    <p className={`text-xl font-black ${fraudData.success_rate > 80 ? 'text-green-600' : 'text-red-600'}`}>
                                        {fraudData.success_rate}%
                                    </p>
                                </div>
                            </div>
                            <div className="text-right">
                                <p className="text-xs font-bold text-gray-700">{fraudData.total_orders} Orders</p>
                            </div>
                        </div>
                        
                        <div className="flex gap-2">
                            <div className="flex-1 bg-green-100 p-2 rounded border border-green-200 text-center">
                                <p className="text-[10px] font-bold text-green-700 uppercase">Delivered</p>
                                <p className="text-lg font-bold text-green-800">{fraudData.delivered}</p>
                            </div>
                            <div className="flex-1 bg-red-100 p-2 rounded border border-red-200 text-center">
                                <p className="text-[10px] font-bold text-red-700 uppercase">Cancelled</p>
                                <p className="text-lg font-bold text-red-800">{fraudData.cancelled}</p>
                            </div>
                        </div>
                    </div>
                    <button onClick={() => setFraudData(null)} className="text-[10px] text-gray-400 underline w-full text-center">Reset Check</button>
                </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
