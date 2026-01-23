
import React, { useState, useMemo, useEffect, useRef } from 'react';
import { 
  Search, 
  Send, 
  Wand2, 
  Users, 
  CheckCircle2, 
  Loader2,
  MessageSquare,
  Layers,
  ChevronDown,
  Settings,
  X,
  AlertCircle,
  User,
  Info,
  BookmarkPlus,
  Layout,
  History,
  AlertTriangle,
  CheckCircle,
  ClipboardList,
  Zap,
  ToggleRight,
  ToggleLeft,
  Smartphone,
  Hash,
  Package,
  Tags,
  RefreshCcw,
  Wallet,
  Coins
} from 'lucide-react';
import { Customer, Order, InventoryProduct, SMSAutomationConfig, WCStatus } from '../types';
import { 
  generateSMSTemplate, 
  getSMSConfig, 
  saveSMSConfig, 
  SMSConfig, 
  sendActualSMS,
  getCustomTemplates,
  saveCustomTemplates,
  SMSTemplate,
  getSMSAutomationConfig,
  saveSMSAutomationConfig,
  getSMSBalance
} from '../services/smsService';

interface BulkSMSViewProps {
  customers: Customer[];
  orders: Order[];
  products: InventoryProduct[];
  initialTargetPhone?: string | null;
}

interface SendLog {
  phone: string;
  status: 'pending' | 'sent' | 'failed';
  message: string;
  time: string;
}

const formatWCStatus = (status: string) => {
  return status.split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

export const BulkSMSView: React.FC<BulkSMSViewProps> = ({ customers, orders, products, initialTargetPhone }) => {
  const [activeTab, setActiveTab] = useState<'database' | 'manual' | 'automation'>('database');
  const [searchTerm, setSearchTerm] = useState<string>('');
  const [selectedOrderCount, setSelectedOrderCount] = useState<string>('All');
  
  // New Filter States
  const [selectedCategory, setSelectedCategory] = useState<string>('All');
  const [selectedProductId, setSelectedProductId] = useState<string>('All');

  const [selectedPhones, setSelectedPhones] = useState<Set<string>>(new Set<string>());
  const [manualInput, setManualInput] = useState('');
  const [manualParsedNumbers, setManualParsedNumbers] = useState<string[]>([]);
  const [templates, setTemplates] = useState<SMSTemplate[]>([]);
  const [showTemplateModal, setShowTemplateModal] = useState(false);
  const [newTemplate, setNewTemplate] = useState({ name: '', content: '' });
  const [message, setMessage] = useState('');
  const [isGenerating, setIsGenerating] = useState(false);
  const [isSending, setIsSending] = useState(false);
  const [sendLogs, setSendLogs] = useState<SendLog[]>([]);
  const [smsConfig, setSmsConfig] = useState<SMSConfig>({ endpoint: 'https://sms.mram.com.bd/smsapi', apiKey: '', senderId: '' });
  
  // Balance State
  const [smsBalance, setSmsBalance] = useState(0);

  // Automation States
  const [automationConfig, setAutomationConfig] = useState<SMSAutomationConfig | null>(null);
  const [isSavingAutomation, setIsSavingAutomation] = useState(false);

  const messageAreaRef = useRef<HTMLTextAreaElement>(null);
  const logsEndRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const loadData = async () => {
      const [savedConfig, savedTemplates, autoConfig, bal] = await Promise.all([
        getSMSConfig(),
        getCustomTemplates(),
        getSMSAutomationConfig(),
        getSMSBalance()
      ]);
      if (savedConfig) setSmsConfig(savedConfig);
      if (savedTemplates) setTemplates(savedTemplates);
      setAutomationConfig(autoConfig);
      setSmsBalance(bal);
    };
    loadData();

    if (initialTargetPhone) {
      setSelectedPhones(new Set([initialTargetPhone]));
    }
  }, [initialTargetPhone]);

  const refreshBalance = async () => {
    const bal = await getSMSBalance();
    setSmsBalance(bal);
  };

  const handleSaveAutomation = async () => {
    if (!automationConfig) return;
    setIsSavingAutomation(true);
    await saveSMSAutomationConfig(automationConfig);
    setIsSavingAutomation(false);
    alert("Automation settings saved successfully!");
  };

  const updateAutomationStatus = (status: keyof SMSAutomationConfig, enabled: boolean) => {
    if (!automationConfig) return;
    setAutomationConfig({
      ...automationConfig,
      [status]: { ...automationConfig[status], enabled }
    });
  };

  const updateAutomationTemplate = (status: keyof SMSAutomationConfig, template: string) => {
    if (!automationConfig) return;
    setAutomationConfig({
      ...automationConfig,
      [status]: { ...automationConfig[status], template }
    });
  };

  const smsStats = useMemo(() => {
    if (!message) return { count: 0, segments: 1, isUnicode: false };
    const gsmRegex = /^[\u0000-\u007F]*$/;
    const isUnicode = !gsmRegex.test(message);
    const count = message.length;
    let segments = 1;
    if (isUnicode) {
      segments = count <= 70 ? 1 : Math.ceil(count / 67);
    } else {
      segments = count <= 160 ? 1 : Math.ceil(count / 153);
    }
    return { count, segments, isUnicode };
  }, [message]);

  const totalCost = useMemo(() => {
    return selectedPhones.size * smsStats.segments;
  }, [selectedPhones.size, smsStats.segments]);

  useEffect(() => {
    const numbers = manualInput
      .split(/[\n,]+/)
      .map(n => n.trim().replace(/[^\d]/g, ''))
      .filter(n => n.length >= 10); 
    setManualParsedNumbers(Array.from(new Set(numbers)));
  }, [manualInput]);

  // Derive unique categories from products
  const uniqueCategories = useMemo(() => {
    const cats = new Set(products.map(p => p.category));
    return ['All', ...Array.from(cats)];
  }, [products]);

  // Filter available products based on selected category
  const availableProducts = useMemo(() => {
    if (selectedCategory === 'All') return products;
    return products.filter(p => p.category === selectedCategory);
  }, [products, selectedCategory]);

  const filteredCustomers = useMemo(() => {
    const searchVal = String(searchTerm || '').toLowerCase();
    const phoneSearch = String(searchTerm || '');
    const orderCountFilter = String(selectedOrderCount || 'All');

    return (customers || []).filter((customer: Customer) => {
      // 1. Basic Search
      const matchesSearch = customer.name.toLowerCase().includes(searchVal) || customer.phone.includes(phoneSearch);
      if (!matchesSearch) return false;
      
      // 2. Order Count Filter
      if (orderCountFilter !== 'All') {
        if (orderCountFilter === '4+') {
          if (customer.orderCount < 4) return false;
        } else {
          const targetCount = parseInt(orderCountFilter, 10);
          if (customer.orderCount !== targetCount) return false;
        }
      }

      // 3. Category & Product Purchase History Filter
      if (selectedCategory !== 'All' || selectedProductId !== 'All') {
        // Find if this customer has any order containing the target product/category
        const hasPurchasedTarget = orders.some(order => {
          // Check if order belongs to customer (by phone matching)
          if (order.customer.phone !== customer.phone) return false;

          // Check products inside order
          return order.products.some(orderProd => {
            const inventoryProd = products.find(p => p.id === orderProd.id);
            if (!inventoryProd) return false; // Product metadata missing

            const matchesCategory = selectedCategory === 'All' || inventoryProd.category === selectedCategory;
            const matchesProduct = selectedProductId === 'All' || inventoryProd.id === selectedProductId;
            
            return matchesCategory && matchesProduct;
          });
        });

        if (!hasPurchasedTarget) return false;
      }

      return true;
    });
  }, [customers, searchTerm, selectedOrderCount, selectedCategory, selectedProductId, orders, products]);

  const toggleSelectAllDatabase = () => {
    if (selectedPhones.size === filteredCustomers.length && filteredCustomers.length > 0) {
      setSelectedPhones(new Set());
    } else {
      setSelectedPhones(new Set(filteredCustomers.map(c => c.phone)));
    }
  };

  const toggleSelectManualAll = () => {
    if (selectedPhones.size === manualParsedNumbers.length && manualParsedNumbers.length > 0) {
      setSelectedPhones(new Set());
    } else {
      setSelectedPhones(new Set(manualParsedNumbers));
    }
  };

  const toggleSelectPhone = (phone: string) => {
    const newSet = new Set(selectedPhones);
    if (newSet.has(phone)) newSet.delete(phone);
    else newSet.add(phone);
    setSelectedPhones(newSet);
  };

  const handleAISuggest = async () => {
    setIsGenerating(true);
    try {
      const text = await generateSMSTemplate("Promotion and Discount", "BdCommerce");
      setMessage(text);
    } catch (e) {
      console.error(e);
    } finally {
      setIsGenerating(false);
    }
  };

  const handleAISuggestAutomation = async (status: string) => {
    setIsGenerating(true);
    try {
      const text = await generateSMSTemplate(`Order status updated to ${status}`, "BdCommerce");
      updateAutomationTemplate(status as any, text);
    } catch (e) {
      console.error(e);
    } finally {
      setIsGenerating(false);
    }
  };

  const handleSaveTemplate = async () => {
    if (!newTemplate.name || !newTemplate.content) return;
    const updated = [...templates, { ...newTemplate, id: Date.now().toString() }];
    setTemplates(updated);
    await saveCustomTemplates(updated);
    setNewTemplate({ name: '', content: '' });
    setShowTemplateModal(false);
  };

  const insertTag = (tag: string, currentText: string, setter: (val: string) => void) => {
    setter(currentText + tag);
  };

  const handleSendSMS = async () => {
    if (selectedPhones.size === 0 || !message.trim()) return;
    
    // Check balance against total cost (recipients * segments)
    if (smsBalance < totalCost) {
      alert(`Insufficient SMS Balance! You need ${totalCost} credits, but have ${smsBalance}.`);
      return;
    }

    if (!window.confirm(`Send SMS to ${selectedPhones.size} recipients?\n\nEstimated Cost: ${totalCost} Credits\n(${selectedPhones.size} contacts × ${smsStats.segments} parts)`)) return;

    setIsSending(true);
    setSendLogs([]);
    const phones = Array.from(selectedPhones) as string[];
    let successCount = 0;
    
    // IMPORTANT: Deduct per message sent based on its parts
    const costPerMessage = smsStats.segments;

    for (const phone of phones) {
      const customer = customers.find(c => c.phone === phone);
      const customerName = customer ? customer.name.split(' ')[0] : 'Customer';
      const personalizedMessage = message.replace(/\[name\]/g, customerName);
      
      const res = await sendActualSMS(smsConfig, phone, personalizedMessage, costPerMessage);
      
      const logEntry: SendLog = {
        phone,
        status: res.success ? 'sent' : 'failed',
        message: res.message || 'Unknown error occurred',
        time: new Date().toLocaleTimeString()
      };
      
      setSendLogs(prev => [...prev, logEntry]);
      if (res.success) successCount++;
      // Small delay to prevent API flooding if necessary
      await new Promise(r => setTimeout(r, 100));
    }
    
    // Refresh balance after bulk operation
    await refreshBalance();

    setIsSending(false);
    alert(`Process completed. ${successCount}/${selectedPhones.size} successful.`);
  };

  return (
    <div className="space-y-6 animate-in fade-in duration-500 pb-20">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-2xl font-bold text-gray-800">Bulk SMS System</h2>
          <p className="text-sm text-gray-500">Reach your customers instantly with personalized SMS.</p>
        </div>
        <div className="flex items-center gap-3">
          {/* Balance Card */}
          <div className="bg-gray-900 text-white px-5 py-2.5 rounded-xl shadow-lg flex items-center gap-3 border border-gray-700">
            <Wallet size={18} className="text-orange-400" />
            <div>
              <p className="text-[10px] font-bold text-gray-400 uppercase leading-tight">SMS Balance</p>
              <p className="text-sm font-black">{smsBalance.toLocaleString()}</p>
            </div>
            <button onClick={refreshBalance} className="p-1 hover:bg-gray-700 rounded-full transition-colors ml-1">
              <RefreshCcw size={12} className={isSending ? 'animate-spin' : ''} />
            </button>
          </div>
          
          {activeTab !== 'automation' && (
            <div className="px-4 py-2 bg-orange-50 rounded-lg border border-orange-100 flex items-center gap-2">
              <Users size={16} className="text-orange-600" />
              <span className="text-sm font-bold text-orange-600">{selectedPhones.size} Selected</span>
            </div>
          )}
        </div>
      </div>

      {/* Warning if balance is low */}
      {smsBalance < 10 && smsBalance > 0 && (
         <div className="bg-red-50 border border-red-100 p-3 rounded-xl flex items-center gap-3 text-red-700 animate-pulse">
            <AlertTriangle size={18} />
            <span className="text-xs font-bold">Your SMS Balance is low ({smsBalance} left). Please recharge to avoid interruption.</span>
         </div>
      )}
      {smsBalance === 0 && (
         <div className="bg-red-50 border border-red-100 p-3 rounded-xl flex items-center gap-3 text-red-700">
            <AlertTriangle size={18} />
            <span className="text-xs font-bold">You have 0 SMS credits. You cannot send messages until you recharge.</span>
         </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div className="lg:col-span-7 bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden flex flex-col h-[750px]">
          <div className="flex border-b border-gray-100 bg-gray-50/50">
            <button onClick={() => { setActiveTab('database'); setSelectedPhones(new Set()); }} className={`flex-1 py-4 text-xs font-bold uppercase transition-all ${activeTab === 'database' ? 'text-orange-600 bg-white border-b-2 border-orange-600' : 'text-gray-400'}`}>Database</button>
            <button onClick={() => { setActiveTab('manual'); setSelectedPhones(new Set()); }} className={`flex-1 py-4 text-xs font-bold uppercase transition-all ${activeTab === 'manual' ? 'text-orange-600 bg-white border-b-2 border-orange-600' : 'text-gray-400'}`}>Manual Input</button>
            <button onClick={() => setActiveTab('automation')} className={`flex-1 py-4 text-xs font-bold uppercase transition-all ${activeTab === 'automation' ? 'text-orange-600 bg-white border-b-2 border-orange-600' : 'text-gray-400'}`}>Automation</button>
          </div>

          {activeTab === 'database' && (
            <div className="flex-1 overflow-hidden flex flex-col">
              <div className="p-4 bg-gray-50/30 border-b border-gray-100 grid grid-cols-2 md:grid-cols-4 gap-3">
                {/* Search */}
                <div className="relative">
                  <input type="text" placeholder="Search..." value={searchTerm} onChange={e => setSearchTerm(e.target.value)} className="w-full pl-8 pr-4 py-2 bg-white border border-gray-200 rounded-lg text-xs font-medium outline-none" />
                  <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
                </div>

                {/* Order Count Filter */}
                <div className="relative">
                  <select value={selectedOrderCount} onChange={e => setSelectedOrderCount(e.target.value)} className="w-full pl-8 pr-4 py-2 bg-white border border-gray-200 rounded-lg text-xs font-medium outline-none appearance-none cursor-pointer">
                    <option value="All">All Orders</option>
                    <option value="0">0 Orders</option>
                    <option value="1">1 Order</option>
                    <option value="2">2 Orders</option>
                    <option value="3">3 Orders</option>
                    <option value="4+">4+ Orders</option>
                  </select>
                  <ClipboardList size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
                  <ChevronDown size={12} className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
                </div>

                {/* Category Filter */}
                <div className="relative">
                  <select value={selectedCategory} onChange={e => { setSelectedCategory(e.target.value); setSelectedProductId('All'); }} className="w-full pl-8 pr-4 py-2 bg-white border border-gray-200 rounded-lg text-xs font-medium outline-none appearance-none cursor-pointer">
                    <option value="All">All Categories</option>
                    {uniqueCategories.filter(c => c !== 'All').map(c => <option key={c} value={c}>{c}</option>)}
                  </select>
                  <Tags size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
                  <ChevronDown size={12} className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
                </div>

                {/* Product Filter */}
                <div className="relative">
                  <select value={selectedProductId} onChange={e => setSelectedProductId(e.target.value)} className="w-full pl-8 pr-4 py-2 bg-white border border-gray-200 rounded-lg text-xs font-medium outline-none appearance-none cursor-pointer">
                    <option value="All">All Products</option>
                    {availableProducts.map(p => <option key={p.id} value={p.id}>{p.name.substring(0, 20)}...</option>)}
                  </select>
                  <Package size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
                  <ChevronDown size={12} className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
                </div>
              </div>

              <div className="p-3 border-b border-gray-100 flex justify-between items-center bg-white px-6">
                 <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">{filteredCustomers.length} Total Customers</span>
                 <button onClick={toggleSelectAllDatabase} className="text-[10px] font-bold text-orange-600 hover:underline flex items-center gap-1 uppercase">
                   <CheckCircle2 size={12} /> {selectedPhones.size === filteredCustomers.length && filteredCustomers.length > 0 ? 'Deselect All' : 'Select All'}
                 </button>
              </div>

              <div className="flex-1 overflow-y-auto custom-scrollbar">
                <table className="w-full text-left text-sm">
                  <thead className="sticky top-0 bg-gray-50 z-10 shadow-sm">
                    <tr className="text-gray-400 font-bold uppercase text-[10px] border-b border-gray-100">
                      <th className="px-6 py-3">Select</th>
                      <th className="px-6 py-3">Customer</th>
                      <th className="px-6 py-3">Phone</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-50">
                    {filteredCustomers.map(c => (
                      <tr key={c.phone} className="hover:bg-orange-50/30 cursor-pointer group transition-colors" onClick={() => toggleSelectPhone(c.phone)}>
                        <td className="px-6 py-3">
                          <div className={`w-5 h-5 rounded border-2 transition-all flex items-center justify-center ${selectedPhones.has(c.phone) ? 'bg-orange-600 border-orange-600 shadow-sm' : 'border-gray-200 group-hover:border-orange-300'}`}>
                            {selectedPhones.has(c.phone) && <CheckCircle2 size={12} className="text-white" />}
                          </div>
                        </td>
                        <td className="px-6 py-3">
                          <div className="flex items-center gap-3">
                            <div className="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 font-bold text-xs">
                              {c.name.charAt(0)}
                            </div>
                            <span className="font-medium text-gray-700">{c.name}</span>
                          </div>
                        </td>
                        <td className="px-6 py-3 text-gray-500 font-mono text-xs">{c.phone}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {activeTab === 'manual' && (
            <div className="p-6 flex flex-col h-full space-y-4">
              <div className="flex items-center justify-between">
                <h4 className="text-xs font-bold text-gray-400 uppercase">Input numbers</h4>
                <span className="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">{manualParsedNumbers.length} Valid Numbers</span>
              </div>
              <textarea value={manualInput} onChange={e => setManualInput(e.target.value)} placeholder="01712345678, 01812345678..." className="flex-1 p-4 bg-gray-50 border border-gray-200 rounded-xl text-sm resize-none focus:ring-1 focus:ring-orange-500 outline-none transition-all shadow-inner" />
              <button onClick={toggleSelectManualAll} className="w-full py-4 bg-gray-800 text-white rounded-xl font-bold shadow-lg hover:bg-gray-900 transition-all active:scale-[0.98]">
                {selectedPhones.size === manualParsedNumbers.length && manualParsedNumbers.length > 0 ? 'Deselect All' : `Select All ${manualParsedNumbers.length} Numbers`}
              </button>
            </div>
          )}

          {activeTab === 'automation' && (
            <div className="flex-1 flex flex-col overflow-hidden">
               <div className="p-4 border-b border-gray-100 bg-orange-50/30 flex justify-between items-center">
                  <div className="flex items-center gap-2">
                    <Zap size={18} className="text-orange-600" />
                    <h4 className="text-sm font-bold text-gray-800">WordPress Status Automation</h4>
                  </div>
                  <button onClick={handleSaveAutomation} disabled={isSavingAutomation} className="px-4 py-2 bg-orange-600 text-white rounded-lg text-xs font-bold shadow-md hover:bg-orange-700 disabled:opacity-50">
                    {isSavingAutomation ? 'Saving...' : 'Save Settings'}
                  </button>
               </div>
               <div className="flex-1 overflow-y-auto p-4 space-y-4 custom-scrollbar">
                  {automationConfig && (Object.keys(automationConfig) as Array<keyof SMSAutomationConfig>).map((status) => (
                    <div key={status} className={`p-4 rounded-2xl border transition-all ${automationConfig[status].enabled ? 'bg-white border-orange-200 shadow-md' : 'bg-gray-50 border-gray-100 opacity-60'}`}>
                      <div className="flex justify-between items-center mb-4">
                        <div className="flex items-center gap-3">
                           <div className={`p-2 rounded-lg ${automationConfig[status].enabled ? 'bg-orange-100 text-orange-600' : 'bg-gray-200 text-gray-400'}`}>
                              <Layers size={16} />
                           </div>
                           <span className="font-bold text-gray-800">{formatWCStatus(status)} Status Message</span>
                        </div>
                        <button onClick={() => updateAutomationStatus(status, !automationConfig[status].enabled)} className={`transition-colors ${automationConfig[status].enabled ? 'text-orange-600' : 'text-gray-400'}`}>
                          {automationConfig[status].enabled ? <ToggleRight size={32} /> : <ToggleLeft size={32} />}
                        </button>
                      </div>
                      
                      {automationConfig[status].enabled && (
                        <div className="space-y-3 animate-in slide-in-from-top-2">
                          <textarea 
                            value={automationConfig[status].template}
                            onChange={(e) => updateAutomationTemplate(status, e.target.value)}
                            className="w-full h-24 p-3 bg-gray-50 border border-gray-200 rounded-xl text-xs outline-none focus:border-orange-500 resize-none"
                            placeholder="Message content..."
                          />
                          <div className="flex flex-wrap gap-2">
                            <button onClick={() => updateAutomationTemplate(status, automationConfig[status].template + "[name]")} className="text-[9px] font-bold px-2 py-1 bg-white border border-gray-200 rounded hover:bg-orange-50 transition-all">[name]</button>
                            <button onClick={() => updateAutomationTemplate(status, automationConfig[status].template + "[order_id]")} className="text-[9px] font-bold px-2 py-1 bg-white border border-gray-200 rounded hover:bg-orange-50 transition-all">[order_id]</button>
                            <button onClick={() => updateAutomationTemplate(status, automationConfig[status].template + "[tracking_code]")} className="text-[9px] font-bold px-2 py-1 bg-white border border-gray-200 rounded hover:bg-orange-50 transition-all">[tracking_code]</button>
                            <button onClick={() => handleAISuggestAutomation(status)} className="ml-auto flex items-center gap-1 text-[9px] font-bold text-orange-600 hover:underline">
                               <Wand2 size={10} /> AI Suggest
                            </button>
                          </div>
                        </div>
                      )}
                    </div>
                  ))}
               </div>
            </div>
          )}
        </div>

        <div className="lg:col-span-5 space-y-6">
          <div className="bg-white p-6 rounded-xl border border-gray-100 shadow-sm space-y-6">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-bold text-gray-800 flex items-center gap-2"><MessageSquare size={16} className="text-orange-500" /> Composition</h3>
              <div className="flex items-center gap-2">
                <div className={`px-2 py-1 rounded text-[10px] font-bold border ${smsStats.segments > 1 ? 'bg-orange-500 text-white border-orange-600' : 'bg-orange-50 text-orange-600 border-orange-200'}`}>
                  {smsStats.segments} SMS Part(s)
                </div>
                {/* Cost Display Badge */}
                {selectedPhones.size > 0 && (
                   <div className="px-2 py-1 rounded text-[10px] font-bold border bg-blue-50 text-blue-600 border-blue-200 flex items-center gap-1">
                      <Coins size={10} />
                      Total Cost: {totalCost} Credits
                   </div>
                )}
                <span className="text-[10px] font-bold text-gray-400 tracking-wider uppercase">{smsStats.count} CHARS</span>
              </div>
            </div>

            <div className="space-y-3">
              <div className="flex items-center justify-between gap-2">
                <div className="flex-1 relative">
                  <select className="w-full pl-8 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium outline-none appearance-none cursor-pointer hover:bg-gray-100" onChange={(e) => {
                      const t = templates.find(temp => temp.id === e.target.value);
                      if (t) setMessage(t.content);
                    }}>
                    <option value="">Choose Saved Template...</option>
                    {templates.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                  </select>
                  <Layout size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
                  <ChevronDown size={12} className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
                </div>
                <button onClick={() => { setNewTemplate({ name: '', content: message }); setShowTemplateModal(true); }} className="p-2.5 border border-gray-200 rounded-lg text-gray-400 hover:text-orange-600 hover:bg-orange-50 transition-all" title="Save current message as template">
                  <BookmarkPlus size={18} />
                </button>
              </div>

              <div className="relative">
                <textarea ref={messageAreaRef} value={message} onChange={e => setMessage(e.target.value)} placeholder="Type your message here..." className="w-full h-44 p-4 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none resize-none pb-14 focus:ring-1 focus:ring-orange-500 focus:bg-white transition-all shadow-inner" />
                <div className="absolute bottom-3 left-3 flex gap-2">
                  <button onClick={() => insertTag("[name]", message, setMessage)} className="px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-[10px] font-bold text-orange-600 shadow-sm flex items-center gap-1.5 hover:bg-orange-50 transition-all">
                    <User size={12} /> Add [name]
                  </button>
                  <button onClick={handleAISuggest} disabled={isGenerating} className="px-3 py-1.5 bg-orange-600 text-white rounded-lg text-[10px] font-bold shadow-sm flex items-center gap-1.5 hover:bg-orange-700 disabled:opacity-50 transition-all">
                    {isGenerating ? <Loader2 size={12} className="animate-spin" /> : <Wand2 size={12} />} AI Suggest
                  </button>
                </div>
              </div>
            </div>
            
            <div className="bg-gray-50 p-3 rounded-lg border border-gray-100 flex gap-2.5">
              <Info size={14} className="text-orange-500 shrink-0 mt-0.5" />
              <div className="space-y-1">
                 <p className="text-[10px] text-gray-500 leading-relaxed font-medium">
                   {smsStats.isUnicode ? "Unicode detected: 70/67 chars per SMS." : "GSM detected: 160/153 chars per SMS."}
                 </p>
                 <p className="text-[10px] text-gray-500 font-bold">
                   1 SMS = {smsStats.segments} Credits | Total Deduction: {totalCost} Credits
                 </p>
              </div>
            </div>

            <button disabled={isSending || selectedPhones.size === 0 || !message.trim() || smsBalance < totalCost} onClick={handleSendSMS} className="w-full py-4 bg-orange-600 text-white rounded-xl font-bold shadow-lg shadow-orange-100 hover:bg-orange-700 flex items-center justify-center gap-3 disabled:opacity-50 transition-all active:scale-[0.98]">
              {isSending ? <Loader2 size={20} className="animate-spin" /> : <Send size={20} />}
              Send SMS to {selectedPhones.size} Recipients
            </button>
          </div>

          <div className="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden flex flex-col max-h-[250px]">
            <div className="p-4 border-b border-gray-50 bg-gray-50/50 flex justify-between items-center">
              <h3 className="text-xs font-bold text-gray-600 flex items-center gap-2"><History size={14} /> Send Log (Status Report)</h3>
              <button onClick={() => setSendLogs([])} className="text-[10px] text-gray-400 hover:text-red-500 font-bold uppercase">Clear</button>
            </div>
            <div className="flex-1 overflow-y-auto p-4 space-y-2 custom-scrollbar">
              {sendLogs.length === 0 ? (
                <p className="text-[10px] text-gray-400 italic text-center py-4">Logs will appear here.</p>
              ) : (
                sendLogs.map((log, idx) => (
                  <div key={idx} className={`p-2.5 rounded-lg border text-[10px] flex items-start gap-3 ${log.status === 'sent' ? 'bg-green-50 border-green-100' : 'bg-red-50 border-red-100'}`}>
                    {log.status === 'sent' ? <CheckCircle size={14} className="text-green-600 shrink-0" /> : <AlertTriangle size={14} className="text-red-600 shrink-0" />}
                    <div className="flex-1">
                      <div className="flex justify-between items-center mb-0.5">
                        <span className="font-bold text-gray-800">{log.phone}</span>
                        <span className="text-[9px] text-gray-400">{log.time}</span>
                      </div>
                      <p className={log.status === 'sent' ? 'text-green-700' : 'text-red-700 font-medium'}>{log.message}</p>
                    </div>
                  </div>
                ))
              )}
              <div ref={logsEndRef} />
            </div>
          </div>
        </div>
      </div>

      {showTemplateModal && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[110] flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in-95 duration-200">
            <div className="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
              <h3 className="font-bold text-gray-800">Save Template</h3>
              <X className="cursor-pointer text-gray-400 hover:text-red-500 transition-colors" onClick={() => setShowTemplateModal(false)} />
            </div>
            <div className="p-6 space-y-4">
              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Template Name</label>
                <input type="text" value={newTemplate.name} onChange={e => setNewTemplate({...newTemplate, name: e.target.value})} placeholder="e.g. Promotion" className="w-full p-2.5 border border-gray-200 rounded-lg text-sm outline-none focus:border-orange-500 transition-all" />
              </div>
              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Content</label>
                <textarea value={newTemplate.content} onChange={e => setNewTemplate({...newTemplate, content: e.target.value})} className="w-full h-32 p-3 border border-gray-200 rounded-lg text-sm resize-none outline-none focus:border-orange-500 transition-all" />
              </div>
              <div className="flex gap-3">
                <button onClick={() => setShowTemplateModal(false)} className="flex-1 py-3 bg-gray-100 text-gray-600 font-bold rounded-xl">Cancel</button>
                <button onClick={handleSaveTemplate} className="flex-1 py-3 bg-orange-600 text-white font-bold rounded-xl shadow-md">Save</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
