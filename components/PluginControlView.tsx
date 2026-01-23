
import React, { useState, useEffect } from 'react';
import { 
  Zap, 
  ShieldCheck, 
  MessageSquare, 
  Activity, 
  Loader2,
  CheckCircle,
  ToggleLeft,
  ToggleRight
} from 'lucide-react';
import { FeatureFlags } from '../types';

export const PluginControlView: React.FC = () => {
  const [features, setFeatures] = useState<FeatureFlags>({
    live_capture: true,
    fraud_guard: true,
    sms_automation: true,
    pixel_capi: false
  });
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetchFeatures();
  }, []);

  const fetchFeatures = async () => {
    try {
      const res = await fetch('api/features.php');
      const data = await res.json();
      if (data) setFeatures(data);
    } catch (e) {
      console.error("Failed to load features", e);
    }
  };

  const toggleFeature = async (key: keyof FeatureFlags) => {
    const newState = !features[key];
    setFeatures(prev => ({ ...prev, [key]: newState }));
    setLoading(true);
    
    try {
      await fetch('api/features.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key, enabled: newState })
      });
    } catch (e) {
      console.error("Failed to toggle feature", e);
      // Revert if failed
      setFeatures(prev => ({ ...prev, [key]: !newState }));
    } finally {
      setLoading(false);
    }
  };

  const FeatureCard: React.FC<{ 
    id: keyof FeatureFlags;
    title: string; 
    desc: string; 
    icon: React.ReactNode; 
    enabled: boolean; 
  }> = ({ id, title, desc, icon, enabled }) => (
    <div className={`p-6 rounded-2xl border transition-all duration-300 ${enabled ? 'bg-white border-orange-200 shadow-lg shadow-orange-50' : 'bg-gray-50 border-gray-100 opacity-75 grayscale'}`}>
      <div className="flex justify-between items-start mb-4">
        <div className={`w-12 h-12 rounded-2xl flex items-center justify-center transition-colors ${enabled ? 'bg-orange-50 text-orange-600' : 'bg-gray-200 text-gray-400'}`}>
          {icon}
        </div>
        <button 
          onClick={() => toggleFeature(id)}
          className={`transition-colors ${enabled ? 'text-orange-600' : 'text-gray-300 hover:text-gray-400'}`}
          disabled={loading}
        >
          {enabled ? <ToggleRight size={40} className="fill-current" /> : <ToggleLeft size={40} />}
        </button>
      </div>
      <h3 className={`text-lg font-bold mb-2 ${enabled ? 'text-gray-800' : 'text-gray-500'}`}>{title}</h3>
      <p className="text-sm text-gray-500 leading-relaxed min-h-[40px]">{desc}</p>
      
      <div className="mt-4 flex items-center gap-2">
        <div className={`w-2 h-2 rounded-full ${enabled ? 'bg-green-500 animate-pulse' : 'bg-red-300'}`}></div>
        <span className={`text-[10px] font-bold uppercase tracking-wider ${enabled ? 'text-green-600' : 'text-red-400'}`}>
          {enabled ? 'Active on Store' : 'Disabled'}
        </span>
      </div>
    </div>
  );

  return (
    <div className="space-y-8 animate-in fade-in duration-500 max-w-5xl mx-auto">
      <div className="text-center space-y-2 mb-8">
        <h2 className="text-3xl font-black text-gray-800">Plugin Control Center</h2>
        <p className="text-gray-500">Manage global features for your e-commerce ecosystem. Changes apply immediately.</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <FeatureCard 
          id="live_capture"
          title="Live Lead Capture"
          desc="Automatically captures incomplete forms on checkout. Allows you to follow up with potential customers who dropped off."
          icon={<Zap size={24} />}
          enabled={features.live_capture}
        />
        
        <FeatureCard 
          id="fraud_guard"
          title="Fraud Guard AI"
          desc="Prevents fake orders by checking 11-digit numbers, VPN/Proxy usage, and customer history before order placement."
          icon={<ShieldCheck size={24} />}
          enabled={features.fraud_guard}
        />
        
        <FeatureCard 
          id="sms_automation"
          title="SMS Automation"
          desc="Sends automated SMS notifications for Order Pending, Processing, Completed, and OTP verifications."
          icon={<MessageSquare size={24} />}
          enabled={features.sms_automation}
        />
        
        <FeatureCard 
          id="pixel_capi"
          title="Facebook Pixel & CAPI"
          desc="Server-side tracking for Facebook Events with advanced matching (Email, Phone Hashing) for higher ROAS."
          icon={<Activity size={24} />}
          enabled={features.pixel_capi}
        />
      </div>

      <div className="bg-blue-50 border border-blue-100 rounded-xl p-4 flex items-center gap-4 text-blue-800 mt-8">
        <div className="p-2 bg-white rounded-full text-blue-600">
          <CheckCircle size={20} />
        </div>
        <div>
          <p className="text-sm font-bold">Performance Optimized</p>
          <p className="text-xs opacity-80">Disabled features will not load any scripts on your WordPress site, ensuring maximum speed.</p>
        </div>
      </div>
    </div>
  );
};
