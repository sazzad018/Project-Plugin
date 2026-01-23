
import { Customer } from "../types";

const CUSTOMERS_API = "api/customers.php";

export const fetchCustomersFromDB = async (): Promise<Customer[]> => {
  try {
    const res = await fetch(CUSTOMERS_API);
    if (!res.ok) {
      const errorText = await res.text();
      console.error(`HTTP Error ${res.status}: ${errorText}`);
      return [];
    }
    
    const text = await res.text();
    if (!text || text.trim() === "") {
      return [];
    }

    try {
      const data = JSON.parse(text);
      return Array.isArray(data) ? data : [];
    } catch (parseError) {
      console.error("Malformed JSON received from customers API:", parseError);
      return [];
    }
  } catch (e) {
    console.error("Network error fetching customers:", e);
    return [];
  }
};

export const syncCustomerWithDB = async (customer: Partial<Customer> & { total?: number, order_id?: string }) => {
  try {
    const response = await fetch(CUSTOMERS_API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        phone: customer.phone,
        name: customer.name,
        email: customer.email,
        address: customer.address,
        total: customer.total || 0,
        avatar: customer.avatar,
        order_id: customer.order_id
      })
    });
    
    const text = await response.text();
    if (!response.ok) {
        console.error("PHP Error response:", text);
        return { error: true };
    }

    if (!text || text.trim() === "") {
      return { success: true };
    }

    try {
      return JSON.parse(text);
    } catch (parseError) {
      console.error("Error parsing customer sync response:", parseError);
      return { error: true };
    }
  } catch (e) {
    console.error("Error syncing customer:", e);
    return { error: true };
  }
};
