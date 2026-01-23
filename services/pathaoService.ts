
import { PathaoConfig, Order } from "../types";

const SETTINGS_URL = "api/settings.php";
const PROXY_URL = "api/pathao_proxy.php";

const fetchSetting = async (key: string) => {
  try {
    const res = await fetch(`${SETTINGS_URL}?key=${key}`);
    if (!res.ok) return null;
    const text = await res.text();
    if (!text || text === "null") return null;
    try {
      const data = JSON.parse(text);
      return typeof data === 'string' ? JSON.parse(data) : data;
    } catch (e) {
      console.error(`Error parsing setting for key ${key}:`, e);
      return null;
    }
  } catch (e) {
    console.error(`Error fetching setting for key ${key}:`, e);
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
    console.error("Error saving Pathao setting:", e);
  }
};

export const getPathaoConfig = async (): Promise<PathaoConfig | null> => {
  const config = await fetchSetting('pathao_config');
  return config || {
    clientId: '',
    clientSecret: '',
    username: '',
    password: '',
    storeId: '',
    isSandbox: true,
    webhookSecret: ''
  };
};

export const savePathaoConfig = async (config: PathaoConfig) => {
  await saveSetting('pathao_config', config);
};

/**
 * Maps Pathao Webhook events to application internal order statuses
 * Fix: Mapped internal statuses to standard lowercase WCStatus values
 */
export const mapPathaoEventToStatus = (event: string): Order['status'] => {
  const e = event.toLowerCase();
  
  // Delivered status
  if (e.includes('delivered') || e.includes('paid')) return 'completed';
  
  // Returned status
  if (e.includes('returned') || e.includes('failed')) return 'refunded';
  
  // Cancelled/Rejected status
  if (e.includes('cancelled') || e.includes('rejected')) return 'cancelled';
  
  // Shipping status
  if (e.includes('transit') || e.includes('sorting') || e.includes('assigned') || e.includes('picked')) return 'processing';
  
  // Packaging status
  if (e.includes('created') || e.includes('requested')) return 'on-hold';
  
  return 'pending';
};

async function pathaoRequest(endpoint: string, method: string = 'GET', body: any = null) {
  const config = await getPathaoConfig();
  if (!config || !config.clientId) {
    return { error: true, message: "Pathao is not configured in settings." };
  }

  try {
    const response = await fetch(PROXY_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        config,
        endpoint,
        method,
        data: body
      })
    });

    const result = await response.json();
    const successCodes = [200, 201, 202, "200", "201", "202"];
    
    if (!successCodes.includes(result.code)) {
      let errorMessage = result.message || "API Connection Error";
      if (result.errors && typeof result.errors === 'object') {
        const firstErrorKey = Object.keys(result.errors)[0];
        const firstErrorVal = result.errors[firstErrorKey];
        errorMessage = `${firstErrorKey}: ${Array.isArray(firstErrorVal) ? firstErrorVal[0] : firstErrorVal}`;
      }
      return { error: true, message: errorMessage, code: result.code };
    }

    return result;
  } catch (error: any) {
    console.error(`Pathao API Request Failed (${endpoint}):`, error);
    return { error: true, message: "Network error." };
  }
}

export const getPathaoOrderStatus = async (trackingCode: string) => {
  return await pathaoRequest(`aladdin/api/v1/orders/${trackingCode}/info`, 'GET');
};

export const checkPathaoConnection = async (): Promise<{ success: boolean; message: string }> => {
  try {
    const res = await pathaoRequest('aladdin/api/v1/stores', 'GET');
    if (res.error) return { success: false, message: res.message };
    return { success: true, message: "Successfully connected to Pathao API" };
  } catch (e: any) {
    return { success: false, message: e.message || "Unknown connection error" };
  }
};

export const getPathaoCities = async () => {
  const res = await pathaoRequest('aladdin/api/v1/city-list', 'GET');
  return res.data?.data || res.data || [];
};

export const getPathaoZones = async (cityId: number) => {
  const res = await pathaoRequest(`aladdin/api/v1/cities/${cityId}/zone-list`, 'GET');
  return res.data?.data || res.data || [];
};

export const getPathaoAreas = async (zoneId: number) => {
  const res = await pathaoRequest(`aladdin/api/v1/zones/${zoneId}/area-list`, 'GET');
  return res.data?.data || res.data || [];
};

export const createPathaoOrder = async (order: Order, location: { city: number, zone: number, area: number }) => {
  const config = await getPathaoConfig();
  if (!config) throw new Error("Pathao config missing");

  let cleanPhone = order.customer.phone.trim().replace(/[^\d]/g, '');
  if (cleanPhone.startsWith('8801')) cleanPhone = cleanPhone.substring(2);
  else if (cleanPhone.startsWith('801')) cleanPhone = '0' + cleanPhone.substring(1);
  if (!cleanPhone.startsWith('0')) cleanPhone = '0' + cleanPhone;

  const payload = {
    store_id: parseInt(config.storeId),
    merchant_order_id: order.id,
    recipient_name: order.customer.name.substring(0, 50),
    recipient_phone: cleanPhone,
    recipient_address: order.address.substring(0, 200),
    recipient_city: location.city,
    recipient_zone: location.zone,
    recipient_area: location.area,
    delivery_type: 48, 
    item_type: 2, 
    item_quantity: Math.max(1, order.products.reduce((acc, p) => acc + p.qty, 0)),
    item_weight: 0.5,
    amount_to_collect: Math.round(order.total),
    item_description: order.products.map(p => p.name).join(', ').substring(0, 200)
  };

  return await pathaoRequest('aladdin/api/v1/orders', 'POST', payload);
};
