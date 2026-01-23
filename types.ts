
export interface Product {
  id?: string;
  name: string;
  brand: string;
  price: number;
  qty: number;
  img: string;
}

export interface InventoryProduct {
  id: string;
  name: string;
  brand: string;
  category: string;
  price: number;
  originalPrice?: number;
  discountPercent: number;
  stock: number;
  status: boolean;
  img: string;
}

export interface Customer {
  name: string;
  email: string;
  phone: string;
  avatar: string;
  orderCount: number;
  totalSpent?: number;
  address?: string;
}

export interface OrderStatusHistory {
  placed: string;
  packaging?: string;
  shipping?: string;
  delivered?: string;
}

/* Standard WooCommerce statuses */
export type WCStatus = 
  | 'pending' 
  | 'processing' 
  | 'on-hold' 
  | 'completed' 
  | 'cancelled' 
  | 'refunded' 
  | 'failed';

export interface Order {
  id: string;
  timestamp: number;
  customer: Customer;
  address: string;
  date: string;
  products: Product[];
  paymentMethod: string;
  subtotal: number;
  shippingCharge: number;
  discount: number;
  total: number;
  status: WCStatus;
  statusHistory: OrderStatusHistory;
  courier_tracking_code?: string;
  courier_status?: string;
  courier_name?: 'Steadfast' | 'Pathao' | 'RedX';
}

export interface Expense {
  id: string;
  category: string;
  amount: number;
  date: string;
  description: string;
  timestamp: number;
}

export interface DashboardStats {
  netProfit: number;
  grossProfit: number;
  totalExpenses: number;
  totalRevenue: number;
  onlineSold: number;
  orders: number;
  customers: number;
  totalProducts: number;
}

export interface CourierConfig {
  apiKey: string;
  secretKey: string;
  email?: string;
  password?: string;
}

export interface PathaoConfig {
  clientId: string;
  clientSecret: string;
  username: string;
  password: string;
  storeId: string;
  isSandbox: boolean;
  webhookSecret?: string;
}

export interface RedxConfig {
  accessToken: string;
}

export interface SMSAutomationStatus {
  enabled: boolean;
  template: string;
}

export interface SMSAutomationConfig {
  pending: SMSAutomationStatus;
  processing: SMSAutomationStatus;
  'on-hold': SMSAutomationStatus;
  completed: SMSAutomationStatus;
  cancelled: SMSAutomationStatus;
  refunded: SMSAutomationStatus;
  failed: SMSAutomationStatus;
}

export interface Lead {
  id: number;
  session_id: string;
  customer_name: string;
  phone: string;
  email: string;
  address: string;
  cart_items: any[];
  cart_total: number;
  status: 'new' | 'contacted' | 'recovered' | 'lost';
  updated_at: string;
}

export interface FeatureFlags {
  live_capture: boolean;
  fraud_guard: boolean;
  sms_automation: boolean;
  pixel_capi: boolean;
}