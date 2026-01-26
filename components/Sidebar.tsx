
import React, { useState } from 'react';
import { 
  LayoutDashboard, 
  BarChart2, 
  Package, 
  Receipt, 
  Users,
  ChevronRight,
  Truck,
  Minus,
  MessageSquare,
  ShoppingCart,
  Zap,
  Sliders,
  Search,
  Key
} from 'lucide-react';

interface SidebarProps {
  activePage: string;
  onNavigate: (page: string) => void;
  statusCounts: Record<string, number>;
  activeStatus: string;
  onStatusFilter: (status: string) => void;
}

const SidebarItem: React.FC<{ 
  icon: React.ReactNode; 
  label: string; 
  active?: boolean; 
  hasSubmenu?: boolean;
  isOpen?: boolean;
  onClick?: () => void;
}> = ({ icon, label, active, hasSubmenu, isOpen, onClick }) => (
  <div 
    onClick={onClick}
    className={`flex items-center justify-between px-4 py-2.5 mb-1 cursor-pointer transition-all rounded-lg group ${
      active ? 'bg-orange-600 text-white shadow-lg' : 'text-gray-600 hover:bg-orange-50 hover:text-orange-600'
    }`}
  >
    <div className="flex items-center gap-3">
      <span className={active ? 'text-white' : 'text-gray-400 group-hover:text-orange-600'}>
        {icon}
      </span>
      <span className="text-sm font-medium">{label}</span>
    </div>
    {hasSubmenu && (
      isOpen ? <Minus size={14} className="opacity-50" /> : <ChevronRight size={14} />
    )}
  </div>
);

const SubMenuItem: React.FC<{
  label: string;
  count: number;
  active?: boolean;
  isLast?: boolean;
  onClick?: () => void;
}> = ({ label, count, active, isLast, onClick }) => (
  <div 
    onClick={onClick}
    className="relative flex items-center justify-between pl-14 pr-4 py-2.5 cursor-pointer group"
  >
    {/* Vertical line connector */}
    {!isLast && (
      <div className="absolute left-[1.82rem] top-6 bottom-[-5px] w-[1px] bg-gray-100"></div>
    )}
    
    {/* Dot */}
    <div className={`absolute left-7 w-2 h-2 rounded-full border-2 z-10 transition-all ${
      active ? 'bg-orange-600 border-orange-600 scale-110' : 'bg-gray-200 border-gray-200 group-hover:bg-orange-300 group-hover:border-orange-300'
    }`}></div>

    <span className={`text-xs font-medium transition-colors ${
      active ? 'text-orange-600' : 'text-gray-600 group-hover:text-orange-600'
    }`}>
      {label}
    </span>
    
    <span className={`w-5 h-5 flex items-center justify-center rounded-full border text-[10px] font-bold transition-colors ${
      active ? 'border-orange-600 text-orange-600 bg-orange-50' : 'border-gray-200 text-gray-400 group-hover:border-orange-500 group-hover:text-orange-500'
    }`}>
      {count}
    </span>
  </div>
);

export const Sidebar: React.FC<SidebarProps> = ({ activePage, onNavigate, statusCounts, activeStatus, onStatusFilter }) => {
  const [isOrderOpen, setIsOrderOpen] = useState(activePage === 'orders');
  const [isProductsOpen, setIsProductsOpen] = useState(activePage === 'all-products');
  const [activeSubPage, setActiveSubPage] = useState<string | null>(activePage === 'all-products' ? 'all-products' : null);

  const handleOrderSectionClick = () => {
    setIsOrderOpen(!isOrderOpen);
    setIsProductsOpen(false);
    onNavigate('orders');
    if (!isOrderOpen) onStatusFilter('All');
  };

  const handleProductsSectionClick = () => {
    setIsProductsOpen(!isProductsOpen);
    setIsOrderOpen(false);
    if (!isProductsOpen) {
      onNavigate('all-products');
      setActiveSubPage('all-products');
    }
  };

  const orderStatuses = [
    { label: 'All Orders', key: 'All' },
    { label: 'Pending Payment', key: 'pending' },
    { label: 'Processing', key: 'processing' },
    { label: 'On Hold', key: 'on-hold' },
    { label: 'Completed', key: 'completed' },
    { label: 'Cancelled', key: 'cancelled' },
    { label: 'Refunded', key: 'refunded' },
    { label: 'Failed', key: 'failed' },
  ];

  return (
    <aside className="w-64 bg-white border-r border-gray-100 h-screen sticky top-0 flex flex-col overflow-y-auto custom-scrollbar">
      <div className="p-6">
        <div className="flex items-center gap-3">
          {/* Logo Section */}
          <img 
            src="https://ibb.co/MkgsZHxn" 
            alt="Logo" 
            className="w-10 h-10 rounded-lg shadow-md object-cover"
            onError={(e) => {
              // Fallback if image fails
              e.currentTarget.style.display = 'none';
              e.currentTarget.nextElementSibling?.classList.remove('hidden');
            }}
          />
          {/* Fallback Icon if image fails to load */}
          <div className="w-10 h-10 bg-orange-600 rounded-lg hidden flex items-center justify-center text-white font-bold shadow-md">
            SA
          </div>
          
          <div className="flex flex-col">
            <span className="text-sm font-bold text-gray-800 leading-tight">Social Ads</span>
            <span className="text-xs font-bold text-orange-600 uppercase tracking-wide">Expert</span>
          </div>
        </div>
      </div>

      <div className="px-4 mb-4">
        <p className="text-[10px] uppercase font-bold text-gray-400 mb-4 px-2 tracking-wider">Home Menu</p>
        <SidebarItem 
          icon={<LayoutDashboard size={18} />} 
          label="Dashboard" 
          active={activePage === 'dashboard'} 
          onClick={() => {
            onNavigate('dashboard');
            setIsOrderOpen(false);
            setIsProductsOpen(false);
          }}
        />
        <SidebarItem 
          icon={<BarChart2 size={18} />} 
          label="Analytics" 
          active={activePage === 'analytics'} 
          onClick={() => {
            onNavigate('analytics');
            setIsOrderOpen(false);
            setIsProductsOpen(false);
          }}
        />
        <SidebarItem 
          icon={<Truck size={18} />} 
          label="Courier" 
          active={activePage === 'courier'} 
          onClick={() => {
            onNavigate('courier');
            setIsOrderOpen(false);
            setIsProductsOpen(false);
          }}
        />
        <SidebarItem 
          icon={<MessageSquare size={18} />} 
          label="Bulk SMS" 
          active={activePage === 'bulk-sms'} 
          onClick={() => {
            onNavigate('bulk-sms');
            setIsOrderOpen(false);
            setIsProductsOpen(false);
          }}
        />
        <SidebarItem 
          icon={<ShoppingCart size={18} />} 
          label="Buy Sms" 
          active={activePage === 'buy-sms'}
          onClick={() => {
            onNavigate('buy-sms');
            setIsOrderOpen(false);
            setIsProductsOpen(false);
          }}
        />
      </div>

      <div className="px-4 pb-8">
        <p className="text-[10px] uppercase font-bold text-gray-400 mb-4 px-2 tracking-wider">All Page</p>
        
        {/* NEW: Leads Section */}
        <SidebarItem 
          icon={<Zap size={18} />} 
          label="Leads (Live)" 
          active={activePage === 'leads'} 
          onClick={() => {
            onNavigate('leads');
            setIsOrderOpen(false);
            setIsProductsOpen(false);
          }}
        />

        <SidebarItem 
          icon={<Truck size={18} />} 
          label="Order" 
          hasSubmenu 
          isOpen={isOrderOpen} 
          active={activePage === 'orders' || activePage === 'order-detail'}
          onClick={handleOrderSectionClick}
        />
        
        {isOrderOpen && (
          <div className="mb-4 animate-in fade-in slide-in-from-top-1 duration-200">
            {orderStatuses.map((status, index) => (
              <SubMenuItem 
                key={index}
                label={status.label} 
                count={statusCounts[status.key] || 0} 
                active={activeStatus === status.key && activePage === 'orders'} 
                isLast={index === orderStatuses.length - 1}
                onClick={() => {
                  onNavigate('orders');
                  onStatusFilter(status.key);
                }}
              />
            ))}
          </div>
        )}

        <SidebarItem 
          icon={<Package size={18} />} 
          label="Products" 
          hasSubmenu 
          isOpen={isProductsOpen}
          active={activePage === 'all-products'}
          onClick={handleProductsSectionClick}
        />

        {isProductsOpen && (
          <div className="mb-4 animate-in fade-in slide-in-from-top-1 duration-200">
            <SubMenuItem 
              label="All Products" 
              count={0} 
              active={activeSubPage === 'all-products' && activePage === 'all-products'} 
              isLast={false}
              onClick={() => {
                onNavigate('all-products');
                setActiveSubPage('all-products');
              }}
            />
            <SubMenuItem 
              label="Stock Alert" 
              count={0} 
              active={activeSubPage === 'stock-alert' && activePage === 'all-products'} 
              isLast={true}
              onClick={() => {
                onNavigate('all-products');
                setActiveSubPage('stock-alert');
              }}
            />
          </div>
        )}

        <SidebarItem 
          icon={<Receipt size={18} />} 
          label="Expense" 
          active={activePage === 'expenses'} 
          onClick={() => {
            onNavigate('expenses');
            setIsOrderOpen(false);
            setIsProductsOpen(false);
          }}
        />
        <SidebarItem 
          icon={<Users size={18} />} 
          label="Customers" 
          active={activePage === 'customers'}
          onClick={() => {
            onNavigate('customers');
            setIsOrderOpen(false);
            setIsProductsOpen(false);
          }}
        />

        <SidebarItem 
          icon={<Sliders size={18} />} 
          label="Plugin Control" 
          active={activePage === 'plugin-control'}
          onClick={() => {
            onNavigate('plugin-control');
            setIsOrderOpen(false);
            setIsProductsOpen(false);
          }}
        />

        {/* License Manager */}
        <SidebarItem 
          icon={<Key size={18} />} 
          label="License Manager" 
          active={activePage === 'licenses'}
          onClick={() => {
            onNavigate('licenses');
            setIsOrderOpen(false);
            setIsProductsOpen(false);
          }}
        />

        <SidebarItem 
          icon={<Search size={18} />} 
          label="Phone Search" 
          active={activePage === 'phone-search'}
          onClick={() => {
            onNavigate('phone-search');
            setIsOrderOpen(false);
            setIsProductsOpen(false);
          }}
        />
      </div>
    </aside>
  );
};
