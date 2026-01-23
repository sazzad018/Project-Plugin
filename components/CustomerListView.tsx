
import React, { useState, useMemo } from 'react';
import { 
  Search, 
  Users, 
  ShoppingBag, 
  Phone, 
  Mail, 
  MoreVertical, 
  MessageSquare,
  ChevronRight,
  User,
  Filter,
  Star,
  Zap,
  Award,
  DollarSign,
  ChevronDown,
  Package,
  Tags,
  ClipboardList,
  X,
  UserPlus,
  Loader2
} from 'lucide-react';
import { Customer, Order, InventoryProduct } from '../types';
import { syncCustomerWithDB } from '../services/customerService';

interface CustomerListViewProps {
  customers: Customer[];
  orders: Order[];
  products: InventoryProduct[];
  onNavigateToSMS: (phone: string) => void;
  onRefresh: () => void;
}

const LoyaltyBadge: React.FC<{ count: number }> = ({ count }) => {
  if (count >= 5) return (
    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-orange-100 text-orange-600 border border-orange-200 shadow-sm animate-pulse">
      <Award size={10} /> VIP Member
    </span>
  );
  if (count > 1) return (
    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-blue-50 text-blue-600 border border-blue-100">
      <Star size={10} /> Returning
    </span>
  );
  return (
    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-gray-50 text-gray-400 border border-gray-100">
      New Client
    </span>
  );
};

export const CustomerListView: React.FC<CustomerListViewProps> = ({ customers, orders, products, onNavigateToSMS, onRefresh }) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [showFilters, setShowFilters] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState('All');
  const [selectedProductId, setSelectedProductId] = useState('All');
  const [orderCountFilter, setOrderCountFilter] = useState('All');
  
  // Manual Import State
  const [showImportModal, setShowImportModal] = useState(false);
  const [importInput, setImportInput] = useState('');
  const [isImporting, setIsImporting] = useState(false);

  // Derive unique categories
  const uniqueCategories = useMemo(() => {
    const cats = new Set(products.map(p => p.category));
    return ['All', ...Array.from(cats)];
  }, [products]);

  // Derive products for selected category
  const availableProducts = useMemo(() => {
    if (selectedCategory === 'All') return products;
    return products.filter(p => p.category === selectedCategory);
  }, [products, selectedCategory]);

  const filteredCustomers = useMemo(() => {
    return (customers || []).filter(c => {
      // 1. Text Search
      const matchesSearch = c.name.toLowerCase().includes(searchTerm.toLowerCase()) || 
                            c.phone.includes(searchTerm) ||
                            (c.email && c.email.toLowerCase().includes(searchTerm.toLowerCase()));
      if (!matchesSearch) return false;

      // 2. Order Count Filter
      if (orderCountFilter !== 'All') {
        if (orderCountFilter === '5+') {
          if (c.orderCount < 5) return false;
        } else {
          const target = parseInt(orderCountFilter);
          if (c.orderCount !== target) return false;
        }
      }

      // 3. Purchase History Filter (Category & Product)
      if (selectedCategory !== 'All' || selectedProductId !== 'All') {
         // Find orders for this customer
         const hasPurchased = orders.some(o => {
            // Check if order belongs to customer
            if (o.customer.phone !== c.phone) return false;

            // Check if order contains matching products
            return o.products.some(op => {
               // Find inventory details to check category
               const invP = products.find(p => p.id === op.id);
               if (!invP) return false;

               const catMatch = selectedCategory === 'All' || invP.category === selectedCategory;
               const prodMatch = selectedProductId === 'All' || invP.id === selectedProductId;
               return catMatch && prodMatch;
            });
         });
         
         if (!hasPurchased) return false;
      }

      return true;
    }).sort((a, b) => (b.orderCount || 0) - (a.orderCount || 0));
  }, [customers, searchTerm, selectedCategory, selectedProductId, orderCountFilter, orders, products]);

  const clearFilters = () => {
    setSelectedCategory('All');
    setSelectedProductId('All');
    setOrderCountFilter('All');
    setSearchTerm('');
  };

  const handleBulkImport = async () => {
    if (!importInput.trim()) return;
    
    setIsImporting(true);
    // Parse numbers: split by comma or newline, trim, filter valid
    const numbers = importInput.split(/[\n,]+/)
      .map(s => s.trim().replace(/[^\d]/g, '')) // Remove non-digits
      .filter(s => s.length >= 10); // Simple length check
    
    const uniqueNumbers = Array.from(new Set(numbers));
    let successCount = 0;

    for (const item of uniqueNumbers) {
      const phone = item as string;
      // Check for existing customer to prevent duplicates
      if (customers.some(c => c.phone.replace(/[^\d]/g, '') === phone)) continue;

      const newCustomer: Partial<Customer> = {
        name: `Customer ${phone.slice(-4)}`, // Placeholder name
        phone: phone,
        email: '',
        address: '',
        avatar: `https://ui-avatars.com/api/?name=${phone}&background=random`,
        orderCount: 0
      };

      await syncCustomerWithDB(newCustomer);
      successCount++;
    }

    setIsImporting(false);
    setImportInput('');
    setShowImportModal(false);
    
    if (successCount > 0) {
      alert(`${successCount} new customers added successfully!`);
      onRefresh();
    } else {
      alert("No new customers added. Numbers might already exist.");
    }
  };

  const hasActiveFilters = selectedCategory !== 'All' || selectedProductId !== 'All' || orderCountFilter !== 'All';

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-2xl font-bold text-gray-800">Customer Management</h2>
          <p className="text-sm text-gray-500">Analyze purchase frequency and reward your loyal buyers.</p>
        </div>
        <div className="flex items-center gap-3">
          <div className="bg-white px-5 py-3 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-3">
            <div className="w-10 h-10 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center">
              <Users size={20} />
            </div>
            <div>
              <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest leading-tight">Total Database</p>
              <p className="text-xl font-black text-gray-800">{customers.length}</p>
            </div>
          </div>
        </div>
      </div>

      <div className="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm space-y-4">
        <div className="flex flex-col md:flex-row gap-4 items-center justify-between">
          <div className="relative w-full md:w-96">
            <input 
              type="text" 
              placeholder="Search by name, phone or email..." 
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-100 rounded-xl text-sm outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/10 transition-all shadow-inner"
            />
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
          </div>
          
          <div className="flex items-center gap-3 w-full md:w-auto">
            <button 
              onClick={() => setShowImportModal(true)}
              className="flex-1 md:flex-none px-4 py-2.5 border border-gray-200 bg-gray-50 text-gray-700 rounded-xl transition-all flex items-center justify-center gap-2 text-sm font-bold shadow-sm hover:bg-white hover:text-orange-600 hover:border-orange-200"
            >
              <UserPlus size={16} /> Manual Input
            </button>
            <button 
              onClick={() => setShowFilters(!showFilters)}
              className={`flex-1 md:flex-none px-4 py-2.5 border rounded-xl transition-all flex items-center justify-center gap-2 text-sm font-bold shadow-sm ${showFilters ? 'bg-orange-600 text-white border-orange-600' : 'bg-white border-gray-200 text-gray-600 hover:text-orange-600'}`}
            >
              <Filter size={16} /> Advanced Filter {hasActiveFilters && <div className="w-2 h-2 rounded-full bg-white animate-pulse"></div>}
            </button>
          </div>
        </div>

        {showFilters && (
          <div className="pt-4 border-t border-gray-100 grid grid-cols-1 md:grid-cols-3 gap-4 animate-in slide-in-from-top-2">
             {/* Category Filter */}
             <div className="relative">
                <select 
                  value={selectedCategory} 
                  onChange={e => { setSelectedCategory(e.target.value); setSelectedProductId('All'); }} 
                  className="w-full pl-9 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold outline-none appearance-none cursor-pointer focus:border-orange-500"
                >
                  <option value="All">All Categories</option>
                  {uniqueCategories.filter(c => c !== 'All').map(c => <option key={c} value={c}>{c}</option>)}
                </select>
                <Tags size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <ChevronDown size={14} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
             </div>

             {/* Product Filter */}
             <div className="relative">
                <select 
                  value={selectedProductId} 
                  onChange={e => setSelectedProductId(e.target.value)} 
                  className="w-full pl-9 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold outline-none appearance-none cursor-pointer focus:border-orange-500"
                >
                  <option value="All">All Products</option>
                  {availableProducts.map(p => <option key={p.id} value={p.id}>{p.name.substring(0, 30)}...</option>)}
                </select>
                <Package size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <ChevronDown size={14} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
             </div>

             {/* Order Count Filter */}
             <div className="relative">
                <select 
                  value={orderCountFilter} 
                  onChange={e => setOrderCountFilter(e.target.value)} 
                  className="w-full pl-9 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold outline-none appearance-none cursor-pointer focus:border-orange-500"
                >
                  <option value="All">Any Order Count</option>
                  <option value="1">1 Order</option>
                  <option value="2">2 Orders</option>
                  <option value="3">3 Orders</option>
                  <option value="4">4 Orders</option>
                  <option value="5+">5+ Orders (VIP)</option>
                </select>
                <ClipboardList size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <ChevronDown size={14} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
             </div>

             {hasActiveFilters && (
               <div className="md:col-span-3 flex justify-end">
                 <button onClick={clearFilters} className="text-[10px] font-bold text-red-500 flex items-center gap-1 hover:underline uppercase">
                   <X size={10} /> Clear All Filters
                 </button>
               </div>
             )}
          </div>
        )}
      </div>

      <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left">
            <thead>
              <tr className="bg-gray-50/50 border-b border-gray-100">
                <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Customer Profile</th>
                <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Contact Information</th>
                <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Order History</th>
                <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Lifetime Value</th>
                <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
              {filteredCustomers.length > 0 ? filteredCustomers.map((customer, idx) => (
                <tr key={idx} className="hover:bg-gray-50/50 transition-all group">
                  <td className="px-6 py-5">
                    <div className="flex items-center gap-4">
                      <div className="relative">
                        <img 
                          src={customer.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(customer.name)}&background=random`} 
                          alt={customer.name} 
                          className="w-12 h-12 rounded-2xl object-cover ring-2 ring-white shadow-md group-hover:scale-110 transition-transform"
                        />
                        <div className={`absolute -bottom-1 -right-1 w-4 h-4 border-2 border-white rounded-full ${customer.orderCount > 1 ? 'bg-orange-500' : 'bg-green-500'}`}></div>
                      </div>
                      <div className="space-y-1">
                        <p className="text-sm font-black text-gray-800 group-hover:text-orange-600 transition-colors leading-none">{customer.name}</p>
                        <LoyaltyBadge count={customer.orderCount} />
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-5">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2 text-xs text-gray-600 font-bold">
                        <Phone size={12} className="text-gray-400" />
                        {customer.phone}
                      </div>
                      {customer.email && (
                        <div className="flex items-center gap-2 text-[11px] text-gray-400 font-medium">
                          <Mail size={12} />
                          {customer.email}
                        </div>
                      )}
                    </div>
                  </td>
                  <td className="px-6 py-5 text-center">
                    <div className="flex flex-col items-center">
                      <div className={`inline-flex items-center gap-2 px-4 py-1.5 rounded-xl text-sm font-black shadow-sm border ${
                        customer.orderCount >= 5 
                          ? 'bg-orange-600 text-white border-orange-700' 
                          : customer.orderCount > 1 
                            ? 'bg-blue-600 text-white border-blue-700'
                            : 'bg-white text-gray-800 border-gray-100'
                      }`}>
                        <ShoppingBag size={14} />
                        {customer.orderCount}
                      </div>
                      <span className="text-[10px] font-bold text-gray-400 mt-1 uppercase tracking-tighter">Total Orders</span>
                    </div>
                  </td>
                  <td className="px-6 py-5">
                    <div className="flex items-center gap-2">
                       <div className="p-1.5 bg-emerald-50 text-emerald-600 rounded-lg">
                         <DollarSign size={14} />
                       </div>
                       <p className="text-sm font-black text-gray-800">
                         ৳{(customer.totalSpent || 0).toLocaleString()}
                       </p>
                    </div>
                  </td>
                  <td className="px-6 py-5 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button 
                        onClick={() => onNavigateToSMS(customer.phone)}
                        className="p-3 text-gray-400 hover:text-orange-600 hover:bg-orange-50 rounded-2xl transition-all shadow-sm hover:shadow-md"
                        title="Send SMS"
                      >
                        <MessageSquare size={18} />
                      </button>
                      <button className="p-3 text-gray-400 hover:text-gray-800 hover:bg-gray-100 rounded-2xl transition-all shadow-sm">
                        <MoreVertical size={18} />
                      </button>
                    </div>
                  </td>
                </tr>
              )) : (
                <tr>
                  <td colSpan={5} className="px-6 py-24 text-center">
                    <div className="flex flex-col items-center justify-center opacity-30">
                      <div className="w-20 h-20 rounded-full bg-gray-50 flex items-center justify-center border-2 border-dashed border-gray-200 mb-4">
                        <Users size={40} className="text-gray-400" />
                      </div>
                      <p className="text-xs font-black uppercase tracking-[0.2em] text-gray-500 italic">No matching client found</p>
                    </div>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {showImportModal && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[110] flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in-95 duration-200 border border-gray-100">
            <div className="p-6 border-b border-gray-50 bg-gray-50/50 flex justify-between items-center">
              <div className="flex items-center gap-2">
                <div className="p-2 bg-blue-50 text-blue-600 rounded-lg">
                  <UserPlus size={20} />
                </div>
                <div>
                  <h3 className="font-bold text-gray-800">Manual Customer Import</h3>
                  <p className="text-[10px] text-gray-400 font-bold uppercase">Add numbers to database</p>
                </div>
              </div>
              <button onClick={() => setShowImportModal(false)} className="p-2 hover:bg-gray-200 rounded-full transition-colors text-gray-400 hover:text-red-500">
                <X size={20} />
              </button>
            </div>
            
            <div className="p-6 space-y-4">
              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Phone Numbers (Comma or New Line separated)</label>
                <textarea 
                  value={importInput}
                  onChange={e => setImportInput(e.target.value)}
                  placeholder="017xxxxxxxx, 018xxxxxxxx..." 
                  className="w-full h-40 p-4 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none resize-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 transition-all font-mono"
                />
              </div>
              
              <div className="pt-2">
                <button 
                  onClick={handleBulkImport}
                  disabled={isImporting || !importInput.trim()}
                  className="w-full py-4 bg-blue-600 text-white font-bold rounded-xl shadow-lg hover:bg-blue-700 transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isImporting ? <Loader2 size={18} className="animate-spin" /> : <UserPlus size={18} />}
                  Convert to Customers
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
