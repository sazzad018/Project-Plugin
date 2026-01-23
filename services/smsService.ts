
import { GoogleGenAI } from "@google/genai";
import { SMSAutomationConfig, Order, WCStatus } from "../types";

export interface SMSConfig {
  endpoint: string;
  apiKey: string;
  senderId: string;
}

export interface SMSTemplate {
  id: string;
  name: string;
  content: string;
}

export interface BkashConfig {
  appKey: string;
  appSecret: string;
  username: string;
  password: string;
  isSandbox?: boolean;
}

const SETTINGS_URL = "api/settings.php";
const BALANCE_API_URL = "api/manage_sms_balance.php";
const BKASH_RELAY_URL = "api/bkash_relay.php";

const fetchSetting = async (key: string): Promise<any> => {
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
    console.error(`Error saving setting ${key}:`, e);
  }
};

export const getSMSConfig = async (): Promise<SMSConfig | null> => {
  return await fetchSetting('sms_config') as SMSConfig | null;
};

export const saveSMSConfig = async (config: SMSConfig) => {
  await saveSetting('sms_config', config);
};

export const getBkashConfig = async (): Promise<BkashConfig | null> => {
  return await fetchSetting('bkash_config') as BkashConfig | null;
};

export const saveBkashConfig = async (config: BkashConfig) => {
  await saveSetting('bkash_config', config);
};

export const createBkashPayment = async (amount: number, smsQty: number) => {
  try {
    const res = await fetch(`${BKASH_RELAY_URL}?action=create`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ amount, sms_qty: smsQty })
    });
    return await res.json();
  } catch (e) {
    console.error("Failed to create bKash payment:", e);
    return { status: "error", message: "Network error" };
  }
};

export const getCustomTemplates = async (): Promise<SMSTemplate[]> => {
  const templates = await fetchSetting('sms_templates');
  return (templates as SMSTemplate[]) || [];
};

export const saveCustomTemplates = async (templates: SMSTemplate[]) => {
  await saveSetting('sms_templates', templates);
};

export const getSMSAutomationConfig = async (): Promise<SMSAutomationConfig> => {
  const config = await fetchSetting('sms_automation_config');
  const defaultConfig: SMSAutomationConfig = {
    pending: { enabled: false, template: "Hi [name], your order #[order_id] is pending payment." },
    processing: { enabled: false, template: "Hi [name], your order #[order_id] is being processed." },
    'on-hold': { enabled: false, template: "Hi [name], your order #[order_id] is on hold." },
    completed: { enabled: false, template: "Hi [name], your order #[order_id] has been completed! Tracking: [tracking_code]" },
    cancelled: { enabled: false, template: "Hi [name], your order #[order_id] was cancelled." },
    refunded: { enabled: false, template: "Hi [name], your order #[order_id] has been refunded." },
    failed: { enabled: false, template: "Hi [name], your order #[order_id] has failed." }
  };
  return config || defaultConfig;
};

export const saveSMSAutomationConfig = async (config: SMSAutomationConfig) => {
  await saveSetting('sms_automation_config', config);
};

// Updated: Robust balance fetching
export const getSMSBalance = async (): Promise<number> => {
  try {
    const res = await fetch(BALANCE_API_URL);
    if (!res.ok) return 0;
    const data = await res.json();
    // Check if balance exists and is a valid number, even if it is 0
    if (data && typeof data.balance !== 'undefined') {
        return parseInt(data.balance, 10);
    }
    return 0;
  } catch (e) {
    console.error("Error fetching SMS balance:", e);
    return 0;
  }
};

export const saveSMSBalance = async (balance: number) => {
  try {
    await fetch(BALANCE_API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ balance })
    });
  } catch (e) {
    console.error("Error saving SMS balance:", e);
  }
};

// Updated: Accepts 'cost' parameter to deduct specific amount based on SMS parts
export const sendActualSMS = async (config: SMSConfig, phone: string, message: string, cost: number = 1): Promise<{success: boolean, message: string}> => {
  try {
    // 1. Check Balance
    const balance = await getSMSBalance();
    
    // Ensure sufficient balance for this specific message cost
    if (balance < cost) {
      return { success: false, message: `Insufficient SMS Balance. Need ${cost}, have ${balance}.` };
    }

    const gsmRegex = /^[\u0000-\u007F]*$/;
    const isUnicode = !gsmRegex.test(message);
    const type = isUnicode ? 'unicode' : 'text';

    let formattedPhone = phone.trim().replace(/[^\d]/g, '');
    if (formattedPhone.length === 11 && formattedPhone.startsWith('01')) {
      formattedPhone = '88' + formattedPhone;
    }

    const response = await fetch('api/send_sms.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        api_key: config.apiKey,
        senderid: config.senderId,
        type: type,
        msg: message,
        contacts: formattedPhone
      })
    });
    
    if (!response.ok) {
      return { success: false, message: `Server Error: ${response.statusText}` };
    }
    
    const result = await response.json();

    if (result.success) {
      // Deduct the calculated cost (SMS parts) from balance
      await saveSMSBalance(balance - cost);
    }
    
    return result;
  } catch (error: any) {
    console.error("SMS sending failed:", error);
    return { success: false, message: error.message || 'Unknown network error' };
  }
};

export const triggerAutomationSMS = async (order: Order, newStatus: WCStatus) => {
  try {
    const [config, autoSettings] = await Promise.all([
      getSMSConfig(),
      getSMSAutomationConfig()
    ]);

    if (!config || !config.apiKey) return;

    const setting = autoSettings[newStatus];
    if (setting && setting.enabled && setting.template) {
      const firstName = order.customer.name.split(' ')[0] || 'Customer';
      const message = setting.template
        .replace(/\[name\]/g, firstName)
        .replace(/\[order_id\]/g, order.id)
        .replace(/\[tracking_code\]/g, order.courier_tracking_code || 'Pending');

      // Calculate parts for automation message to deduct correctly
      const gsmRegex = /^[\u0000-\u007F]*$/;
      const isUnicode = !gsmRegex.test(message);
      const count = message.length;
      let segments = 1;
      if (isUnicode) {
        segments = count <= 70 ? 1 : Math.ceil(count / 67);
      } else {
        segments = count <= 160 ? 1 : Math.ceil(count / 153);
      }

      console.log(`[SMS Automation] Triggered for Order ${order.id} - Status: ${newStatus} - Cost: ${segments}`);
      return await sendActualSMS(config, order.customer.phone, message, segments);
    }
  } catch (e) {
    console.error("SMS Automation trigger failed:", e);
  }
};

export const generateSMSTemplate = async (purpose: string, businessName: string): Promise<string> => {
  try {
    const ai = new GoogleGenAI({ apiKey: process.env.API_KEY });
    const response = await ai.models.generateContent({
      model: "gemini-3-flash-preview",
      contents: `Create a professional SMS message for "${businessName}". Purpose: "${purpose}". Use [name] for customer name, [order_id] for order id, [tracking_code] for tracking. Short & crisp.`,
    });
    return response.text?.trim() || "Hello [name], thank you for shopping with us!";
  } catch (error) {
    console.error("Gemini SMS template generation failed:", error);
    return "Hello [name], check out our new collection!";
  }
};
