
import React, { useState, useEffect } from 'react';
import { 
  Key, 
  Plus, 
  Trash2, 
  CheckCircle, 
  XCircle, 
  Loader2, 
  Copy, 
  Globe 
} from 'lucide-react';

interface License {
  id: string;
  domain_name: string;
  license_key: string;
  status: 'active' | 'inactive';
  created_at: string;
}

export const LicenseView: React.FC = () => {
  const [licenses, setLicenses] = useState<License[]>([]);
  const [loading, setLoading] = useState(false);
  const [showModal, setShowModal] = useState(false);
  const [newDomain, setNewDomain] = useState('');
  const [creating, setCreating] = useState(false);

  const fetchLicenses = async () => {
    setLoading(true);
    try {
      const res = await fetch('api/licenses.php');
      const data = await res.json();
      if (Array.isArray(data)) setLicenses(data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLicenses();
  }, []);

  const handleCreate = async () => {
    if (!newDomain) return;
    setCreating(true);
    try {
      await fetch('api/licenses.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create', domain: newDomain })
      });
      setShowModal(false);
      setNewDomain('');
      fetchLicenses();
    } catch (e) {
      alert("Failed to create license");
    } finally {
      setCreating(false);
    }
  };

  const handleToggle = async (id: string, currentStatus: string) => {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    // Optimistic update
    setLicenses(prev => prev.map(l => l.id === id ? { ...l, status: newStatus } : l));
    
    await fetch('api/licenses.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'toggle', id, status: newStatus })
    });
  };

  const handleDelete = async (id: string) => {
    if (!confirm("Are you sure? This will block the plugin on that site immediately.")) return;
    setLicenses(prev => prev.filter(l => l.id !== id));
    await fetch('api/licenses.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', id })
    });
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    alert("License Key Copied!");
  };

  return (
    <div className="space-y-6 animate-in fade-in duration-500 max-w-6xl mx-auto">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-2xl font-bold text-gray-800">License Manager</h2>
          <p className="text-sm text-gray-500">Control which websites can use your premium plugin features.</p>
        </div>
        <button 
          onClick={() => setShowModal(true)}
          className="bg-gray-900 text-white px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 shadow-lg hover:bg-black transition-all"
        >
          <Plus size={18} /> Add New Site
        </button>
      </div>

      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <table className="w-full text-left">
          <thead>
            <tr className="bg-gray-50/50 border-b border-gray-100 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
              <th className="px-6 py-4">Domain Name</th>
              <th className="px-6 py-4">License Key</th>
              <th className="px-6 py-4 text-center">Status</th>
              <th className="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-50 text-sm">
            {licenses.map(lic => (
              <tr key={lic.id} className="hover:bg-gray-50/50 transition-colors">
                <td className="px-6 py-4">
                  <div className="flex items-center gap-2 font-bold text-gray-700">
                    <Globe size={16} className="text-gray-400" />
                    {lic.domain_name}
                  </div>
                </td>
                <td className="px-6 py-4">
                  <div className="flex items-center gap-2 bg-gray-100 px-3 py-1.5 rounded-lg w-fit border border-gray-200">
                    <code className="text-xs font-mono text-gray-600">{lic.license_key}</code>
                    <button onClick={() => copyToClipboard(lic.license_key)} className="text-gray-400 hover:text-blue-600">
                      <Copy size={12} />
                    </button>
                  </div>
                </td>
                <td className="px-6 py-4 text-center">
                  <button 
                    onClick={() => handleToggle(lic.id, lic.status)}
                    className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide border transition-all ${
                      lic.status === 'active' 
                        ? 'bg-green-50 text-green-600 border-green-200 hover:bg-red-50 hover:text-red-600 hover:border-red-200' 
                        : 'bg-red-50 text-red-600 border-red-200 hover:bg-green-50 hover:text-green-600 hover:border-green-200'
                    }`}
                  >
                    {lic.status === 'active' ? <CheckCircle size={12} /> : <XCircle size={12} />}
                    {lic.status}
                  </button>
                </td>
                <td className="px-6 py-4 text-right">
                  <button onClick={() => handleDelete(lic.id)} className="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all">
                    <Trash2 size={16} />
                  </button>
                </td>
              </tr>
            ))}
            {licenses.length === 0 && !loading && (
              <tr>
                <td colSpan={4} className="px-6 py-12 text-center text-gray-400 italic">No licenses found. Add a website to get started.</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {showModal && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 animate-in zoom-in-95">
            <h3 className="text-lg font-bold text-gray-800 mb-4">Add Website</h3>
            <div className="space-y-4">
              <div>
                <label className="text-xs font-bold text-gray-500 uppercase mb-1 block">Website Domain</label>
                <input 
                  type="text" 
                  placeholder="example.com" 
                  value={newDomain}
                  onChange={e => setNewDomain(e.target.value)}
                  className="w-full p-3 border border-gray-200 rounded-xl text-sm outline-none focus:border-gray-900"
                />
              </div>
              <div className="flex gap-3 pt-2">
                <button onClick={() => setShowModal(false)} className="flex-1 py-3 bg-gray-100 text-gray-600 font-bold rounded-xl">Cancel</button>
                <button 
                  onClick={handleCreate} 
                  disabled={creating || !newDomain}
                  className="flex-1 py-3 bg-gray-900 text-white font-bold rounded-xl flex items-center justify-center gap-2 disabled:opacity-50"
                >
                  {creating ? <Loader2 size={16} className="animate-spin" /> : <Key size={16} />} Generate Key
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
