
import { CourierConfig, Order } from "../types";
import { getPathaoOrderStatus, mapPathaoEventToStatus } from "./pathaoService";

const PROXY_URL = "api/courier.php";
const TRACKING_URL = "api/local_tracking.php";
const SETTINGS_URL = "api/settings.php";

export const identifyCourierByTrackingCode = (trackingCode: string): 'Steadfast' | 'Pathao' => {
  if (!trackingCode) return 'Steadfast';
  return /^\d+$/.test(trackingCode) ? 'Pathao' : 'Steadfast';
};

/**
 * Maps Steadfast Courier statuses to application internal order statuses
 * Fix: Mapped internal statuses to standard lowercase WCStatus values
 */
export const mapSteadfastStatusToInternal = (status: string): Order['status'] => {
  const s = status.toLowerCase();
  
  if (s.includes('delivered')) return 'completed';
  if (s.includes('cancelled') || s.includes('reject')) return 'cancelled';
  if (s.includes('return')) return 'refunded';
  if (s.includes('transit') || s.includes('shipping') || s.includes('out_for_delivery') || s.includes('picked')) return 'processing';
  if (s.includes('pending') || s.includes('hold') || s.includes('packaging')) return 'on-hold';
  
  return 'pending';
};

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
    console.error("Error saving setting:", e);
  }
};

export const getCourierConfig = async (): Promise<CourierConfig | null> => {
  return await fetchSetting('courier_config');
};

export const saveCourierConfig = async (config: CourierConfig) => {
  await saveSetting('courier_config', config);
};

export const fetchAllLocalTracking = async (): Promise<any[]> => {
  try {
    const res = await fetch(TRACKING_URL);
    if (!res.ok) return [];
    const text = await res.text();
    try {
      const data = JSON.parse(text);
      return Array.isArray(data) ? data : [];
    } catch (e) {
      return [];
    }
  } catch (e) {
    return [];
  }
};

export const saveTrackingLocally = async (orderId: string, trackingCode: string, status: string, courier?: 'Steadfast' | 'Pathao') => {
  const detectedCourier = courier || identifyCourierByTrackingCode(trackingCode);
  try {
    const response = await fetch(TRACKING_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        order_id: String(orderId),
        tracking_code: trackingCode,
        status: status,
        courier_name: detectedCourier 
      })
    });
    return await response.json();
  } catch (e) {
    console.error("Local tracking save failed:", e);
    return { status: "error" };
  }
};

export const getCourierBalance = async () => {
  const config = await getCourierConfig();
  if (!config || !config.apiKey) return 0;
  try {
    const response = await fetch(`${PROXY_URL}?action=balance`, {
      method: 'GET',
      headers: {
        'Api-Key': config.apiKey,
        'Secret-Key': config.secretKey,
        'Content-Type': 'application/json'
      }
    });
    const result = await response.json();
    return result.current_balance || result.balance || 0;
  } catch (error) {
    console.error("Error fetching courier balance:", error);
    return 0;
  }
};

export const createSteadfastOrder = async (order: Order) => {
  const config = await getCourierConfig();
  if (!config || !config.apiKey) throw new Error("Courier API not configured");

  try {
    const response = await fetch(`${PROXY_URL}?action=create`, {
      method: 'POST',
      headers: {
        'Api-Key': config.apiKey,
        'Secret-Key': config.secretKey,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        invoice: order.id,
        recipient_name: order.customer.name,
        recipient_phone: order.customer.phone,
        recipient_address: order.address,
        cod_amount: order.total,
        note: `Order from Admin Dashboard`
      })
    });

    const result = await response.json();
    if (result.status === 200 && result.consignment) {
      await saveTrackingLocally(order.id, result.consignment.tracking_code, result.consignment.status, 'Steadfast');
    }
    return result;
  } catch (error) {
    throw error;
  }
};

export const getDeliveryStatus = async (trackingCode: string) => {
  const config = await getCourierConfig();
  if (!config || !config.apiKey) return null;
  try {
    const response = await fetch(`${PROXY_URL}?action=status&tracking_code=${trackingCode}`, {
      method: 'GET',
      headers: {
        'Api-Key': config.apiKey,
        'Secret-Key': config.secretKey,
        'Content-Type': 'application/json'
      }
    });
    return await response.json();
  } catch (error) {
    return null;
  }
};

export const syncOrderStatusWithCourier = async (orders: Order[]) => {
  const localTracking = await fetchAllLocalTracking();
  const updatedOrders = [...orders];

  for (let i = 0; i < updatedOrders.length; i++) {
    const order = updatedOrders[i];
    const trackingInfo = localTracking.find(t => String(t.id) === String(order.id));
    if (trackingInfo) {
      const courier = trackingInfo.courier_name || identifyCourierByTrackingCode(trackingInfo.courier_tracking_code);
      let currentStatus = order.status;
      let courierStatus = trackingInfo.courier_status;

      if (courierStatus) {
        if (courier === 'Pathao') {
          currentStatus = mapPathaoEventToStatus(courierStatus);
        } else {
          // Default Steadfast mapping (polling or webhook)
          currentStatus = mapSteadfastStatusToInternal(courierStatus);
        }
      }

      updatedOrders[i] = {
        ...updatedOrders[i],
        status: currentStatus,
        courier_name: courier as 'Steadfast' | 'Pathao',
        courier_tracking_code: trackingInfo.courier_tracking_code,
        courier_status: courierStatus
      };
    }
  }

  return updatedOrders;
};
