
import React, { useState } from 'react';
import { Plus, Search, Trash2, Calendar, DollarSign, X, ReceiptText } from 'lucide-react';
import { Expense } from '../types';

interface ExpenseListViewProps {
  expenses: Expense[];
  onAddExpense: (expense: Omit<Expense, 'id' | 'timestamp'>) => void;
  onDeleteExpense: (id: string) => void;
}

export const ExpenseListView: React.FC<ExpenseListViewProps> = ({ expenses, onAddExpense, onDeleteExpense }) => {
  const [showModal, setShowModal] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [formData, setFormData] = useState({
    category: 'Operational',
    amount: '',
    date: new Date().toISOString().split('T')[0],
    description: ''
  });

  const categories = ['Operational', 'Marketing', 'Salary', 'Rent', 'Utility', 'Inventory', 'Others'];

  const filteredExpenses = expenses.filter(e => 
    e.description.toLowerCase().includes(searchTerm.toLowerCase()) ||
    e.category.toLowerCase().includes(searchTerm.toLowerCase())
  ).sort((a, b) => b.timestamp - a.timestamp);

  const totalAmount = filteredExpenses.reduce((sum, e) => sum + e.amount, 0);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.amount || parseFloat(formData.amount) <= 0) return;
    
    onAddExpense({
      category: formData.category,
      amount: parseFloat(formData.amount),
      date: formData.date,
      description: formData.description
    });
    
    setFormData({
      category: 'Operational',
      amount: '',
      date: new Date().toISOString().split('T')[0],
      description: ''
    });
    setShowModal(false);
  };

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-2xl font-bold text-gray-800">Business Expenses</h2>
          <p className="text-sm text-gray-500">Track and manage all your business related costs.</p>
        </div>
        <button 
          onClick={() => setShowModal(true)}
          className="bg-orange-600 text-white px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 shadow-lg hover:bg-orange-700 transition-all active:scale-95"
        >
          <Plus size={18} /> Add New Expense
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center">
            <DollarSign size={24} />
          </div>
          <div>
            <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Expenses</p>
            <p className="text-2xl font-black text-gray-800">৳{totalAmount.toLocaleString()}</p>
          </div>
        </div>
        <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
            <ReceiptText size={24} />
          </div>
          <div>
            <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Records</p>
            <p className="text-2xl font-black text-gray-800">{filteredExpenses.length}</p>
          </div>
        </div>
      </div>

      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div className="p-4 border-b border-gray-50 flex justify-between items-center bg-gray-50/30">
          <h3 className="text-sm font-bold text-gray-700">Expense History</h3>
          <div className="relative">
            <input 
              type="text" 
              placeholder="Search expenses..." 
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="pl-9 pr-4 py-2 bg-white border border-gray-200 rounded-lg text-xs outline-none focus:border-orange-500 w-64"
            />
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
          </div>
        </div>
        
        <div className="overflow-x-auto">
          <table className="w-full text-left">
            <thead>
              <tr className="bg-white text-[10px] uppercase font-bold text-gray-400 tracking-wider">
                <th className="px-6 py-4 border-b border-gray-50">Date</th>
                <th className="px-6 py-4 border-b border-gray-50">Category</th>
                <th className="px-6 py-4 border-b border-gray-50">Description</th>
                <th className="px-6 py-4 border-b border-gray-50">Amount</th>
                <th className="px-6 py-4 border-b border-gray-50 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50 text-sm">
              {filteredExpenses.length > 0 ? filteredExpenses.map(expense => (
                <tr key={expense.id} className="hover:bg-gray-50/50 transition-colors">
                  <td className="px-6 py-4 text-gray-600 font-medium">
                    <div className="flex items-center gap-2">
                      <Calendar size={14} className="text-gray-400" />
                      {expense.date}
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <span className="px-2.5 py-1 bg-gray-100 text-gray-600 rounded-md text-[10px] font-bold uppercase tracking-tight">
                      {expense.category}
                    </span>
                  </td>
                  <td className="px-6 py-4 text-gray-700">{expense.description || '-'}</td>
                  <td className="px-6 py-4 font-bold text-gray-800">৳{expense.amount.toLocaleString()}</td>
                  <td className="px-6 py-4 text-right">
                    <button 
                      onClick={() => onDeleteExpense(expense.id)}
                      className="p-2 text-gray-400 hover:text-red-500 transition-colors"
                    >
                      <Trash2 size={16} />
                    </button>
                  </td>
                </tr>
              )) : (
                <tr>
                  <td colSpan={5} className="px-6 py-20 text-center text-gray-400 italic">No expense records found.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {showModal && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[110] flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in-95 duration-200">
            <div className="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
              <h3 className="font-bold text-gray-800">Add New Expense</h3>
              <button onClick={() => setShowModal(false)} className="text-gray-400 hover:text-red-500 transition-colors">
                <X size={20} />
              </button>
            </div>
            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase">Category</label>
                <select 
                  className="w-full p-2.5 bg-gray-50 border border-gray-100 rounded-lg text-sm outline-none focus:border-orange-500"
                  value={formData.category}
                  onChange={(e) => setFormData({...formData, category: e.target.value})}
                >
                  {categories.map(cat => <option key={cat} value={cat}>{cat}</option>)}
                </select>
              </div>
              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase">Amount (৳)</label>
                <input 
                  type="number" 
                  required
                  placeholder="0.00"
                  className="w-full p-2.5 bg-gray-50 border border-gray-100 rounded-lg text-sm outline-none focus:border-orange-500"
                  value={formData.amount}
                  onChange={(e) => setFormData({...formData, amount: e.target.value})}
                />
              </div>
              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase">Date</label>
                <input 
                  type="date" 
                  required
                  className="w-full p-2.5 bg-gray-50 border border-gray-100 rounded-lg text-sm outline-none focus:border-orange-500"
                  value={formData.date}
                  onChange={(e) => setFormData({...formData, date: e.target.value})}
                />
              </div>
              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase">Description</label>
                <textarea 
                  placeholder="What was this expense for?"
                  className="w-full p-2.5 bg-gray-50 border border-gray-100 rounded-lg text-sm outline-none focus:border-orange-500 h-24 resize-none"
                  value={formData.description}
                  onChange={(e) => setFormData({...formData, description: e.target.value})}
                />
              </div>
              <div className="pt-4 flex gap-3">
                <button 
                  type="button"
                  onClick={() => setShowModal(false)} 
                  className="flex-1 py-3 bg-gray-100 text-gray-600 font-bold rounded-xl"
                >
                  Cancel
                </button>
                <button 
                  type="submit"
                  className="flex-1 py-3 bg-orange-600 text-white font-bold rounded-xl shadow-lg hover:bg-orange-700 transition-all active:scale-[0.98]"
                >
                  Save Expense
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
