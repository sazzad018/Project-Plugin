import React, { useState, useMemo } from 'react';
import { 
  Search, 
  Plus, 
  Minus, 
  Trash2, 
  ShoppingCart, 
  UserPlus, 
  X, 
  Check,
  ChevronDown,
  Loader2,
  CheckCircle,
  Truck,
  Send,
  ExternalLink,
  User,
  Phone,
  Mail,
  MapPin,
  CreditCard,
  Wallet,
  // Fix: Added missing RefreshCcw icon from lucide-react
  RefreshCcw
} from 'lucide-react';
import { InventoryProduct, Customer, Product, Order } from '../types';

interface POSViewProps {
  products: InventoryProduct[];
  customers: Customer[];
  categories: { id: number, name: string }[];
  onPlaceOrder: (order: Omit<Order, 'id' | 'timestamp' | 'date' | 'statusHistory'>) => Promise<Order | null>;
  onSendToCourier: (order: Order, courier: 'Steadfast' | 'Pathao') => Promise<any>;
  onAddCustomer: (customer: Customer) => void;
}

interface CartItem extends Product {
  originalStock: number;
}

export const POSView: React.FC<POSViewProps> = ({ 
  products, 
  customers, 
  categories, 
  onPlaceOrder, 
  onSendToCourier,
  onAddCustomer 
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('All');
  const [cart, setCart] = useState<CartItem[]>([]);
  const [selectedCustomer, setSelectedCustomer] = useState<Customer | null>(null);
  const [extraDiscount, setExtraDiscount] = useState(0);
  const [paymentMethod, setPaymentMethod] = useState<'Hand Cash' | 'Card Pay'>('Hand Cash');
  
  // Modal states
  const [showAddCustomerModal, setShowAddCustomerModal] = useState(false);
  const [newCustomer, setNewCustomer] = useState({ name: '', phone: '', email: '', address: '' });
  
  // Placement states
  const [isPlacing, setIsPlacing] = useState(false);
  const [placedOrder, setPlacedOrder] = useState<Order | null>(null);
  const [isSendingCourier, setIsSendingCourier] = useState(false);

  // Filter products based on search and category
  const filteredProducts = useMemo(() => {
    return products.filter(p => {
      const matchesSearch = p.name.toLowerCase().includes(searchTerm.toLowerCase()) || 
                           (p.brand && p.brand.toLowerCase().includes(searchTerm.toLowerCase()));
      const matchesCategory = selectedCategory === 'All' || p.category === selectedCategory;
      return matchesSearch && matchesCategory;
    });
  }, [products, searchTerm, selectedCategory]);

  const addToCart = (product: InventoryProduct) => {
    setCart(prev => {
      const existing = prev.find(item => item.id === product.id);
      if (existing) {
        return prev.map(item => 
          item.id === product.id ? { ...item, qty: item.qty + 1 } : item
        );
      }
      return [...prev, {
        id: product.id,
        name: product.name,
        brand: product.brand || 'N/A',
        price: product.price,
        qty: 1,
        img: product.img,
        originalStock: product.stock
      }];
    });
  };

  const updateQty = (id: string, delta: number) => {
    setCart(prev => prev.map(item => {
      if (item.id === id) {
        const newQty = Math.max(1, item.qty + delta);
        return { ...item, qty: newQty };
      }
      return item;
    }));
  };

  const removeFromCart = (id: string) => {
    setCart(prev => prev.filter(item => item.id !== id));
  };

  const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
  const finalTotal = subtotal - extraDiscount;

  const handleAddCustomerSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newCustomer.name || !newCustomer.phone) return alert("Name and Phone are required!");
    
    const customer: Customer = {
      name: newCustomer.name,
      phone: newCustomer.phone,
      email: newCustomer.email || `${newCustomer.phone}@example.com`,
      address: newCustomer.address,
      avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(newCustomer.name)}&background=random`,
      orderCount: 0
    };
    
    onAddCustomer(customer);
    setSelectedCustomer(customer);
    setNewCustomer({ name: '', phone: '', email: '', address: '' });
    setShowAddCustomerModal(false);
  };

  const handlePlaceOrder = async () => {
    if (cart.length === 0) return alert("Cart is empty!");
    if (!selectedCustomer) return alert("Please select a customer!");

    setIsPlacing(true);
    try {
      const order = await onPlaceOrder({
        customer: selectedCustomer,
        address: selectedCustomer.address || "Point of Sale Entry",
        products: cart.map(({ originalStock, ...rest }) => rest),
        paymentMethod,
        subtotal,
        shippingCharge: 0,
        discount: extraDiscount,
        total: finalTotal,
        status: 'completed'
      });
      
      if (order) {
        setPlacedOrder(order);
        setCart([]);
        setExtraDiscount(0);
        setSelectedCustomer(null);
      }
    } catch (e) {
      alert("Failed to place order.");
    } finally {
      setIsPlacing(false);
    }
  };

  const handleSendCourier = async (courier: 'Steadfast' | 'Pathao') => {
    if (!placedOrder) return;
    setIsSendingCourier(true);
    try {
      await onSendToCourier(placedOrder, courier);
      setPlacedOrder(null); // Close modal on success
      alert(`Order sent to ${courier} successfully!`);
    } catch (e) {
      alert("Courier submission failed.");
    } finally {
      setIsSendingCourier(false);
    }
  };

  return (
    <div className="flex gap-6 h-[calc(100vh-140px)] animate-in fade-in duration-500 overflow-hidden relative">
      {/* Product Section */}
      <div className="flex-1 flex flex-col gap-6 overflow-hidden">
        {/* Top Filters */}
        <div className="bg-white/70 backdrop-blur-md p-4 rounded-2xl border border-gray-100 shadow-sm flex flex-wrap items-center justify-between gap-4 sticky top-0 z-10">
          <div className="flex items-center gap-2">
            <h2 className="text-lg font-bold text-gray-800">Marketplace</h2>
            <span className="w-8 h-8 rounded-full bg-orange-600 text-white flex items-center justify-center text-xs font-bold shadow-lg shadow-orange-100">
              {filteredProducts.length}
            </span>
          </div>

          <div className="flex items-center gap-2 overflow-x-auto custom-scrollbar pb-1 max-w-[400px]">
            <button 
              onClick={() => setSelectedCategory('All')}
              className={`px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all ${selectedCategory === 'All' ? 'bg-orange-600 text-white shadow-lg' : 'bg-gray-50 text-gray-500 hover:bg-orange-50 hover:text-orange-600'}`}
            >
              All Products
            </button>
            {categories.map(cat => (
              <button 
                key={cat.id}
                onClick={() => setSelectedCategory(cat.name)}
                className={`px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all ${selectedCategory === cat.name ? 'bg-orange-600 text-white shadow-lg' : 'bg-gray-50 text-gray-500 hover:bg-orange-50 hover:text-orange-600'}`}
              >
                {cat.name}
              </button>
            ))}
          </div>

          <div className="relative">
            <input 
              type="text" 
              placeholder="Quick search..." 
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="pl-9 pr-4 py-2 bg-gray-50 border border-transparent rounded-xl text-sm outline-none focus:border-orange-500 focus:bg-white w-48 lg:w-64 transition-all shadow-inner"
            />
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
          </div>
        </div>

        {/* Product Grid */}
        <div className="flex-1 overflow-y-auto custom-scrollbar pr-2 pb-10">
          {products.length === 0 ? (
            <div className="h-full flex flex-col items-center justify-center opacity-40">
              <Loader2 size={48} className="animate-spin text-orange-600 mb-4" />
              <p className="text-sm font-bold">Synchronizing Stock...</p>
            </div>
          ) : filteredProducts.length > 0 ? (
            <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
              {filteredProducts.map(product => (
                <div 
                  key={product.id}
                  onClick={() => addToCart(product)}
                  className="bg-white rounded-2xl border border-gray-100 p-3 hover:shadow-xl hover:-translate-y-1 transition-all group cursor-pointer relative flex flex-col active:scale-95"
                >
                  <div className="aspect-square rounded-xl overflow-hidden bg-gray-50 mb-3 border border-gray-100 relative shadow-inner">
                    <img 
                      src={product.img} 
                      alt={product.name} 
                      className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" 
                      onError={(e) => (e.currentTarget.src = 'https://via.placeholder.com/150')}
                    />
                    <div className="absolute inset-0 bg-black/0 group-hover:bg-orange-600/10 transition-colors flex items-center justify-center">
                       <Plus className="text-orange-600 opacity-0 group-hover:opacity-100 scale-50 group-hover:scale-100 transition-all drop-shadow-md bg-white p-2 rounded-full shadow-lg" size={40} />
                    </div>
                  </div>
                  <div className="flex-1">
                    <h3 className="text-[13px] font-bold text-gray-800 line-clamp-1 mb-1">{product.name}</h3>
                    <div className="flex items-center justify-between text-[10px] mb-2 font-bold uppercase tracking-tight">
                      <span className="text-gray-400">{product.brand || 'No Brand'}</span>
                      <span className={product.stock > 0 ? 'text-green-500' : 'text-red-500'}>
                        STOCK: {product.stock}
                      </span>
                    </div>
                    <div className="flex items-baseline gap-2">
                      <span className="text-base font-black text-gray-900 group-hover:text-orange-600 transition-colors">৳{product.price.toLocaleString()}</span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="h-full flex flex-col items-center justify-center text-gray-400 opacity-50">
              <Search size={48} className="mb-4" />
              <p className="text-sm font-bold">No matches found.</p>
            </div>
          )}
        </div>
      </div>

      {/* Bill Details Section */}
      <div className="w-[420px] bg-white border border-gray-100 shadow-2xl rounded-3xl flex flex-col overflow-hidden animate-in slide-in-from-right-4 duration-500 relative">
        <div className="p-6 border-b border-gray-50 bg-gray-50/50 flex justify-between items-center">
          <div>
            <h3 className="font-black text-gray-800 text-lg">Current Order</h3>
            <p className="text-[10px] text-gray-400 font-black uppercase tracking-widest">Transaction Preview</p>
          </div>
          <button 
            onClick={() => setCart([])} 
            className="p-2.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-xl transition-all"
            title="Reset Cart"
          >
            <RefreshCcw size={18} />
          </button>
        </div>

        {/* Customer Selector */}
        <div className="p-6 pb-2">
          <label className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 block">Recipient Details</label>
          <div className="flex gap-2">
            <div className="flex-1 relative">
              <select 
                className="w-full appearance-none pl-4 pr-10 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:border-orange-500 cursor-pointer font-bold text-gray-700 transition-all shadow-sm"
                value={selectedCustomer?.email || ''}
                onChange={(e) => setSelectedCustomer(customers.find(c => c.email === e.target.value) || null)}
              >
                <option value="">Guest Walk-in</option>
                {customers.map(c => <option key={c.phone + c.email} value={c.email}>{c.name} ({c.phone})</option>)}
              </select>
              <ChevronDown className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" size={16} />
            </div>
            <button 
              onClick={() => setShowAddCustomerModal(true)}
              className="w-12 h-12 bg-gray-900 text-white rounded-2xl flex items-center justify-center shadow-lg hover:bg-black active:scale-95 transition-all"
            >
              <UserPlus size={20} />
            </button>
          </div>
          {selectedCustomer && (
            <div className="mt-3 flex items-center gap-3 p-3 bg-blue-50 border border-blue-100 rounded-2xl animate-in fade-in slide-in-from-top-2">
               <img src={selectedCustomer.avatar} className="w-8 h-8 rounded-full border border-white shadow-sm" alt="avatar" />
               <div className="flex-1">
                 <p className="text-xs font-bold text-blue-900">{selectedCustomer.name}</p>
                 <p className="text-[10px] text-blue-500 font-medium">{selectedCustomer.phone}</p>
               </div>
               <button onClick={() => setSelectedCustomer(null)} className="p-1 hover:bg-blue-100 rounded-full text-blue-400">
                 <X size={14} />
               </button>
            </div>
          )}
        </div>

        {/* Cart Items */}
        <div className="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-3">
          {cart.length > 0 ? cart.map(item => (
            <div key={item.id} className="flex items-center gap-4 bg-white p-2 rounded-2xl border border-gray-100 group hover:border-orange-200 transition-all shadow-sm">
              <img src={item.img} alt={item.name} className="w-12 h-12 rounded-xl object-cover border border-gray-50" />
              <div className="flex-1 min-w-0">
                <p className="text-[12px] font-bold text-gray-800 line-clamp-1">{item.name}</p>
                <p className="text-[11px] text-orange-600 font-black">৳{item.price.toLocaleString()}</p>
              </div>
              <div className="flex items-center gap-2 bg-gray-50 rounded-xl p-1 border border-gray-100">
                <button onClick={() => updateQty(item.id, -1)} className="w-6 h-6 flex items-center justify-center text-gray-400 hover:text-orange-600 transition-colors">
                  <Minus size={12} />
                </button>
                <span className="text-xs font-black text-gray-800 w-4 text-center">{item.qty}</span>
                <button onClick={() => updateQty(item.id, 1)} className="w-6 h-6 flex items-center justify-center text-gray-400 hover:text-orange-600 transition-colors">
                  <Plus size={12} />
                </button>
              </div>
              <button onClick={() => removeFromCart(item.id)} className="text-gray-300 hover:text-red-500 transition-colors pr-2">
                <Trash2 size={16} />
              </button>
            </div>
          )) : (
            <div className="flex flex-col items-center justify-center h-full text-center text-gray-400 opacity-30 select-none">
              <div className="w-20 h-20 rounded-full bg-gray-50 flex items-center justify-center border-2 border-dashed border-gray-200 mb-4">
                <ShoppingCart size={32} />
              </div>
              <p className="text-[10px] font-black uppercase tracking-[0.2em]">Bag is Empty</p>
            </div>
          )}
        </div>

        {/* Summary */}
        <div className="p-6 bg-gray-50 border-t border-gray-100 space-y-6">
          <div className="flex items-center justify-between gap-4 bg-white p-3 rounded-2xl border border-gray-100 shadow-sm">
            <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Adjust Discount</span>
            <div className="relative w-28">
              <input 
                type="number" 
                value={extraDiscount}
                onChange={(e) => setExtraDiscount(parseFloat(e.target.value) || 0)}
                className="w-full pl-6 pr-3 py-1.5 bg-gray-50 border border-gray-100 rounded-xl text-xs font-black text-right outline-none focus:border-orange-500 shadow-inner"
              />
              <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400">৳</span>
            </div>
          </div>

          <div className="space-y-3 px-1">
            <div className="flex justify-between text-xs font-bold text-gray-400">
              <span>Merchandise Subtotal</span>
              <span>৳{subtotal.toLocaleString()}</span>
            </div>
            {extraDiscount > 0 && (
              <div className="flex justify-between text-xs font-bold text-emerald-500">
                <span>Special Promotion</span>
                <span>- ৳{extraDiscount.toLocaleString()}</span>
              </div>
            )}
            <div className="flex justify-between items-center border-t border-gray-200 pt-4">
              <span className="font-black text-gray-800 uppercase text-xs tracking-[0.1em]">Estimated Total</span>
              <span className="text-3xl font-black text-gray-900 tracking-tighter">৳{finalTotal.toLocaleString()}</span>
            </div>
          </div>

          <div className="space-y-3">
            <div className="grid grid-cols-2 gap-3">
              <button 
                onClick={() => setPaymentMethod('Hand Cash')}
                className={`py-3 px-4 rounded-2xl text-[11px] font-black uppercase transition-all flex items-center justify-center gap-2 border shadow-sm ${paymentMethod === 'Hand Cash' ? 'bg-orange-600 text-white border-orange-600 shadow-orange-100' : 'bg-white border-gray-100 text-gray-400 hover:bg-gray-50'}`}
              >
                <Wallet size={14} /> Cash
              </button>
              <button 
                onClick={() => setPaymentMethod('Card Pay')}
                className={`py-3 px-4 rounded-2xl text-[11px] font-black uppercase transition-all flex items-center justify-center gap-2 border shadow-sm ${paymentMethod === 'Card Pay' ? 'bg-orange-600 text-white border-orange-600 shadow-orange-100' : 'bg-white border-gray-100 text-gray-400 hover:bg-gray-50'}`}
              >
                <CreditCard size={14} /> Card
              </button>
            </div>
          </div>

          <div className="pt-2">
            <button 
              onClick={handlePlaceOrder}
              disabled={isPlacing || cart.length === 0}
              className="w-full py-5 bg-gray-900 text-white font-black rounded-[2rem] text-sm shadow-2xl shadow-gray-200 hover:bg-black transition-all uppercase tracking-widest disabled:opacity-50 flex items-center justify-center gap-3 active:scale-[0.98]"
            >
              {isPlacing ? <Loader2 size={18} className="animate-spin" /> : <CheckCircle size={18} />}
              Checkout Order
            </button>
          </div>
        </div>
      </div>

      {/* Add Customer Modal */}
      {showAddCustomerModal && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[300] flex items-center justify-center p-4 animate-in fade-in duration-300">
          <div className="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in-95 duration-200 border border-gray-100">
            <div className="p-8 border-b border-gray-50 bg-gray-50/50 flex justify-between items-center">
              <div>
                <h3 className="text-xl font-black text-gray-800">New Client</h3>
                <p className="text-[10px] text-gray-400 font-black uppercase tracking-widest mt-1">Direct Registration</p>
              </div>
              <button onClick={() => setShowAddCustomerModal(false)} className="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-full transition-all">
                <X size={24} />
              </button>
            </div>
            
            <form onSubmit={handleAddCustomerSubmit} className="p-8 space-y-5">
              <div className="space-y-4">
                <div className="space-y-1.5">
                  <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-2 flex items-center gap-2">
                    <User size={12} /> Name
                  </label>
                  <input 
                    type="text" 
                    required
                    placeholder="E.g. John Doe"
                    value={newCustomer.name}
                    onChange={(e) => setNewCustomer({...newCustomer, name: e.target.value})}
                    className="w-full px-5 py-4 bg-gray-50 border border-transparent rounded-[1.5rem] text-sm font-bold outline-none focus:border-orange-500 focus:bg-white transition-all shadow-inner"
                  />
                </div>
                
                <div className="space-y-1.5">
                  <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-2 flex items-center gap-2">
                    <Phone size={12} /> Mobile
                  </label>
                  <input 
                    type="tel" 
                    required
                    placeholder="01xxxxxxxxx"
                    value={newCustomer.phone}
                    onChange={(e) => setNewCustomer({...newCustomer, phone: e.target.value})}
                    className="w-full px-5 py-4 bg-gray-50 border border-transparent rounded-[1.5rem] text-sm font-bold outline-none focus:border-orange-500 focus:bg-white transition-all shadow-inner"
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-2 flex items-center gap-2">
                    <MapPin size={12} /> Address
                  </label>
                  <textarea 
                    placeholder="Delivery details..."
                    value={newCustomer.address}
                    onChange={(e) => setNewCustomer({...newCustomer, address: e.target.value})}
                    className="w-full px-5 py-4 bg-gray-50 border border-transparent rounded-[1.5rem] text-sm font-bold outline-none focus:border-orange-500 focus:bg-white transition-all h-24 resize-none shadow-inner"
                  />
                </div>
              </div>

              <div className="flex gap-4 pt-4">
                <button 
                  type="submit"
                  className="w-full py-5 bg-orange-600 text-white font-black rounded-[2rem] text-xs uppercase tracking-widest shadow-xl shadow-orange-100 hover:bg-orange-700 transition-all flex items-center justify-center gap-2"
                >
                  Confirm & Save
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Post-Order Action Modal */}
      {placedOrder && (
        <div className="fixed inset-0 bg-black/70 backdrop-blur-md z-[200] flex items-center justify-center p-4 animate-in fade-in duration-300">
          <div className="bg-white rounded-[3rem] shadow-2xl w-full max-w-lg overflow-hidden animate-in zoom-in-95 duration-200 border border-gray-100 relative">
            <div className="p-10 text-center bg-gray-50 relative">
               <div className="w-24 h-24 bg-orange-600 text-white rounded-[2rem] flex items-center justify-center mx-auto mb-6 shadow-2xl shadow-orange-200 animate-in zoom-in duration-500">
                  <Check size={48} strokeWidth={3} />
               </div>
               <h3 className="text-3xl font-black text-gray-900 mb-2 tracking-tighter">Order Success!</h3>
               <p className="text-xs font-black text-gray-400 uppercase tracking-[0.2em] mb-4">Invoice: {placedOrder.id}</p>
               <div className="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-100 rounded-2xl shadow-sm text-xs font-bold text-gray-600">
                  <span className="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                  Status: Ready for Courier
               </div>
            </div>
            
            <div className="p-10 space-y-8">
              <div className="grid grid-cols-2 gap-4">
                <button 
                  onClick={() => handleSendCourier('Steadfast')}
                  disabled={isSendingCourier}
                  className="flex flex-col items-center gap-4 p-8 bg-gray-50 border-2 border-transparent rounded-[2.5rem] hover:bg-white hover:border-orange-500 transition-all group shadow-sm"
                >
                  <div className="w-14 h-14 bg-white text-orange-600 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform shadow-sm">
                    <Send size={24} />
                  </div>
                  <span className="font-black text-gray-800 text-xs uppercase tracking-widest">Steadfast</span>
                </button>
                <button 
                  onClick={() => handleSendCourier('Pathao')}
                  disabled={isSendingCourier}
                  className="flex flex-col items-center gap-4 p-8 bg-gray-50 border-2 border-transparent rounded-[2.5rem] hover:bg-white hover:border-red-500 transition-all group shadow-sm"
                >
                  <div className="w-14 h-14 bg-white text-red-600 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform shadow-sm">
                    <Truck size={24} />
                  </div>
                  <span className="font-black text-gray-800 text-xs uppercase tracking-widest">Pathao</span>
                </button>
              </div>

              <div className="flex flex-col gap-3">
                <button 
                  onClick={() => setPlacedOrder(null)}
                  className="w-full py-5 bg-gray-900 text-white font-black rounded-[2rem] text-xs shadow-2xl shadow-gray-200 hover:bg-black transition-all uppercase tracking-[0.2em] flex items-center justify-center gap-2"
                >
                  Dismiss Terminal
                </button>
              </div>
            </div>

            {isSendingCourier && (
              <div className="absolute inset-0 bg-white/90 backdrop-blur-md flex flex-col items-center justify-center z-[210] animate-in fade-in">
                 <Loader2 size={64} className="animate-spin text-orange-600 mb-6" />
                 <p className="text-xs font-black text-gray-800 uppercase tracking-[0.3em] animate-pulse">Establishing Dispatch...</p>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};