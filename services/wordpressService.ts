
import { Order, InventoryProduct, WCStatus } from "../types";

export interface WPConfig {
  url: string;
  consumerKey: string;
  consumerSecret: string;
}

export interface WPCategory {
  id: number;
  name: string;
  slug: string;
  count: number;
}

export interface CreateProductPayload {
  name: string;
  type?: 'simple' | 'variable';
  regular_price: string;
  sale_price?: string;
  description?: string;
  short_description?: string;
  sku?: string;
  categories?: { id: number }[];
  images?: { src: string }[];
  manage_stock?: boolean;
  stock_quantity?: number;
  stock_status?: 'instock' | 'outofstock' | 'onbackorder';
  status?: 'publish' | 'draft' | 'pending' | 'private';
  weight?: string;
  dimensions?: {
    length: string;
    width: string;
    height: string;
  };
}

const SETTINGS_URL = "api/settings.php";
const TRACKING_URL = "api/local_tracking.php";

const fetchSetting = async (key: string) => {
  try {
    const res = await fetch(`${SETTINGS_URL}?key=${key}`);
    if (!res.ok) return null;
    const text = await res.text();
    if (!text || text === "null" || text.trim() === "") return null;
    try {
      const data = JSON.parse(text);
      return typeof data === 'string' ? JSON.parse(data) : data;
    } catch (e) {
      return null;
    }
  } catch (e) {
    return null;
  }
};

const saveSetting = async (key: string, value: any) => {
  try {
    await fetch(SETTINGS_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ key, value: JSON.stringify(value) })
    });
  } catch (e) {
    console.error("Error saving setting:", key, e);
  }
};

let cachedConfig: WPConfig | null = null;

export const getWPConfig = async (): Promise<WPConfig | null> => {
  if (cachedConfig) return cachedConfig;
  cachedConfig = await fetchSetting('wp_config');
  return cachedConfig;
};

export const saveWPConfig = async (config: WPConfig) => {
  cachedConfig = config;
  await saveSetting('wp_config', config);
};

const fetchLocalTrackingData = async (): Promise<any[]> => {
  try {
    const res = await fetch(TRACKING_URL);
    if (!res.ok) return [];
    const text = await res.text();
    if (!text || text.trim() === "" || text === "null") return [];
    try {
      const data = JSON.parse(text);
      return Array.isArray(data) ? data : [];
    } catch (e) {
      console.error("Error parsing tracking JSON:", e);
      return [];
    }
  } catch (e) {
    console.error("Network error fetching local tracking:", e);
    return [];
  }
};

export const fetchOrdersFromWP = async (): Promise<Order[]> => {
  try {
    const config = await getWPConfig();
    const localTracking = await fetchLocalTrackingData();
    
    let allWcOrders: any[] = [];
    if (config && config.url && config.consumerKey) {
      const { url, consumerKey, consumerSecret } = config;
      const baseUrl = url.endsWith('/') ? url.slice(0, -1) : url;
      const apiBase = `${baseUrl}/wp-json/wc/v3/orders?consumer_key=${consumerKey}&consumer_secret=${consumerSecret}&per_page=100`;
      
      try {
        const response = await fetch(apiBase);
        if (response.ok) {
          allWcOrders = await response.json();
        }
      } catch (err) {
        console.warn("Could not fetch WC orders, showing local only.");
      }
    }

    const mappedOrders: Order[] = (Array.isArray(allWcOrders) ? allWcOrders : []).map((wc: any): Order => {
      const tracking = localTracking.find(t => String(t.id) === String(wc.id));
      
      // Use WordPress original status directly
      let mappedStatus: WCStatus = wc.status;
      
      // If courier tracking overrides it based on shipment progress
      if (tracking && tracking.courier_status) {
        const cs = tracking.courier_status.toLowerCase();
        if (cs.includes('delivered')) mappedStatus = 'completed';
        else if (cs.includes('cancelled') || cs.includes('reject')) mappedStatus = 'cancelled';
        else if (cs.includes('return')) mappedStatus = 'refunded';
      }

      let detectedCourier = tracking?.courier_name;
      if (!detectedCourier && tracking?.courier_tracking_code) {
        const isNumeric = /^\d+$/.test(tracking.courier_tracking_code);
        detectedCourier = isNumeric ? 'Pathao' : 'Steadfast';
      }

      return {
        id: wc.id.toString(),
        timestamp: new Date(wc.date_created).getTime(),
        customer: {
          name: `${wc.billing.first_name} ${wc.billing.last_name}`.trim() || wc.billing.email || 'Guest Customer',
          email: wc.billing.email,
          phone: wc.billing.phone,
          avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(wc.billing.first_name || 'U')}+${encodeURIComponent(wc.billing.last_name || 'C')}&background=random`,
          orderCount: 0
        },
        address: `${wc.billing.address_1}${wc.billing.city ? ', ' + wc.billing.city : ''}`,
        date: new Date(wc.date_created).toLocaleString(),
        paymentMethod: wc.payment_method_title || 'Unknown',
        products: wc.line_items.map((item: any) => ({
          id: item.product_id.toString(),
          name: item.name,
          brand: 'N/A',
          price: parseFloat(item.price),
          qty: item.quantity,
          img: 'https://picsum.photos/seed/' + item.product_id + '/100/100'
        })),
        subtotal: parseFloat(wc.total) - (parseFloat(wc.shipping_total) || 0),
        shippingCharge: parseFloat(wc.shipping_total) || 0,
        discount: parseFloat(wc.discount_total) || 0,
        total: parseFloat(wc.total),
        status: mappedStatus,
        statusHistory: { placed: new Date(wc.date_created).toLocaleDateString() },
        courier_tracking_code: tracking?.courier_tracking_code || undefined,
        courier_status: tracking?.courier_status || undefined,
        courier_name: (detectedCourier as 'Steadfast' | 'Pathao') || undefined
      };
    });

    return mappedOrders.sort((a, b) => b.timestamp - a.timestamp);
  } catch (error) {
    console.error("Fetch orders failed", error);
    return [];
  }
};

export const fetchProductsFromWP = async (): Promise<InventoryProduct[]> => {
  try {
    const config = await getWPConfig();
    if (!config || !config.url) return [];

    const { url, consumerKey, consumerSecret } = config;
    const baseUrl = url.endsWith('/') ? url.slice(0, -1) : url;
    const apiBase = `${baseUrl}/wp-json/wc/v3/products?consumer_key=${consumerKey}&consumer_secret=${consumerSecret}&per_page=100`;

    const response = await fetch(apiBase);
    if (!response.ok) return [];
    const data = await response.json();
    if (!Array.isArray(data)) return [];

    return data.map((wc: any): InventoryProduct => ({
      id: wc.id.toString(),
      name: wc.name,
      brand: 'N/A',
      category: wc.categories[0]?.name || 'Uncategorized',
      price: parseFloat(wc.price || '0'),
      discountPercent: 0,
      stock: wc.stock_quantity || 0,
      status: wc.status === 'publish',
      img: wc.images[0]?.src || 'https://picsum.photos/seed/' + wc.id + '/100/100'
    }));
  } catch (error) {
    return [];
  }
};

export const getProductFromWP = async (id: string): Promise<any> => {
  try {
    const config = await getWPConfig();
    if (!config || !config.url || !config.consumerKey) return null;

    const { url, consumerKey, consumerSecret } = config;
    const baseUrl = url.endsWith('/') ? url.slice(0, -1) : url;
    const apiBase = `${baseUrl}/wp-json/wc/v3/products/${id}?consumer_key=${consumerKey}&consumer_secret=${consumerSecret}`;

    const response = await fetch(apiBase);
    if (!response.ok) return null;
    return await response.json();
  } catch (error) {
    console.error("Fetch single product failed", error);
    return null;
  }
};

export const fetchCategoriesFromWP = async (): Promise<WPCategory[]> => {
  try {
    const config = await getWPConfig();
    if (!config || !config.url) return [];
    const { url, consumerKey, consumerSecret } = config;
    const baseUrl = url.endsWith('/') ? url.slice(0, -1) : url;
    const apiBase = `${baseUrl}/wp-json/wc/v3/products/categories?consumer_key=${consumerKey}&consumer_secret=${consumerSecret}&per_page=100`;
    const res = await fetch(apiBase);
    if (!res.ok) return [];
    const data = await res.json();
    return Array.isArray(data) ? data : [];
  } catch (e) {
    return [];
  }
};

export const uploadImageToWP = async (file: File): Promise<{ success: boolean; url?: string; error?: string }> => {
  const config = await getWPConfig();
  if (!config || !config.url || !config.consumerKey) {
    console.error("WP Config missing for upload");
    return { success: false, error: "Settings not configured" };
  }

  const { url, consumerKey, consumerSecret } = config;
  const baseUrl = url.endsWith('/') ? url.slice(0, -1) : url;
  
  // Custom Plugin Endpoint
  const apiBase = `${baseUrl}/wp-json/bdcommerce/v1/upload`;

  try {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('consumer_key', consumerKey);
    formData.append('consumer_secret', consumerSecret);

    const response = await fetch(apiBase, {
        method: 'POST',
        body: formData
    });

    if (response.status === 404) {
        throw new Error("Plugin not installed. Please install 'BdCommerce Connect' plugin on WordPress.");
    }

    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.message || data.code || "Upload failed");
    }

    if (data.url) {
        return { success: true, url: data.url };
    }
    
    throw new Error("Invalid response from plugin");

  } catch (e: any) {
    console.error("Plugin upload failed:", e);
    return { success: false, error: e.message };
  }
};

export const createProductInWP = async (productData: CreateProductPayload): Promise<{success: boolean; message?: string; product?: any}> => {
  try {
    const config = await getWPConfig();
    if (!config || !config.url || !config.consumerKey) {
      return { success: false, message: "WordPress connection not configured." };
    }

    const { url, consumerKey, consumerSecret } = config;
    const baseUrl = url.endsWith('/') ? url.slice(0, -1) : url;
    const apiBase = `${baseUrl}/wp-json/wc/v3/products?consumer_key=${consumerKey}&consumer_secret=${consumerSecret}`;

    const response = await fetch(apiBase, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(productData)
    });

    const data = await response.json();

    if (!response.ok) {
      return { success: false, message: data.message || "Failed to create product in WordPress" };
    }

    return { success: true, product: data };
  } catch (error: any) {
    console.error("Create product failed", error);
    return { success: false, message: error.message || "Network error occurred" };
  }
};

export const updateProductInWP = async (productId: string, productData: Partial<CreateProductPayload>): Promise<{success: boolean; message?: string; product?: any}> => {
  try {
    const config = await getWPConfig();
    if (!config || !config.url || !config.consumerKey) {
      return { success: false, message: "WordPress connection not configured." };
    }

    const { url, consumerKey, consumerSecret } = config;
    const baseUrl = url.endsWith('/') ? url.slice(0, -1) : url;
    const apiBase = `${baseUrl}/wp-json/wc/v3/products/${productId}?consumer_key=${consumerKey}&consumer_secret=${consumerSecret}`;

    const response = await fetch(apiBase, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(productData)
    });

    const data = await response.json();

    if (!response.ok) {
      return { success: false, message: data.message || "Failed to update product in WordPress" };
    }

    return { success: true, product: data };
  } catch (error: any) {
    console.error("Update product failed", error);
    return { success: false, message: error.message || "Network error occurred" };
  }
};

export const updateOrderInWP = async (orderId: string, status: string): Promise<boolean> => {
  try {
    const config = await getWPConfig();
    if (!config || !config.url || !config.consumerKey) {
       console.error("WP connection missing for order update");
       return false;
    }

    const { url, consumerKey, consumerSecret } = config;
    const baseUrl = url.endsWith('/') ? url.slice(0, -1) : url;
    const apiBase = `${baseUrl}/wp-json/wc/v3/orders/${orderId}?consumer_key=${consumerKey}&consumer_secret=${consumerSecret}`;

    const response = await fetch(apiBase, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ status })
    });

    if (!response.ok) {
       const err = await response.json();
       console.error("Failed to update WP order status:", err);
       return false;
    }

    return true;
  } catch (error) {
    console.error("Network error updating WP order:", error);
    return false;
  }
};
