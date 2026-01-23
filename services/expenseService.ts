
import { Expense } from "../types";

const SETTINGS_URL = "api/settings.php";

export const getExpenses = async (): Promise<Expense[]> => {
  try {
    const res = await fetch(`${SETTINGS_URL}?key=business_expenses`);
    if (!res.ok) return [];
    const text = await res.text();
    if (!text || text === "null") return [];
    const data = JSON.parse(text);
    return Array.isArray(data) ? data : JSON.parse(data);
  } catch (e) {
    console.error("Error fetching expenses:", e);
    return [];
  }
};

export const saveExpenses = async (expenses: Expense[]) => {
  try {
    await fetch(SETTINGS_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ key: 'business_expenses', value: JSON.stringify(expenses) })
    });
  } catch (e) {
    console.error("Error saving expenses:", e);
  }
};
