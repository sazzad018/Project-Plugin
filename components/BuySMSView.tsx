
import React, { useState } from 'react';
import { 
  CheckCircle2, 
  Zap, 
  ShieldCheck, 
  CreditCard, 
  Smartphone,
  Loader2,
  X,
  CheckCircle
} from 'lucide-react';
import { saveSMSBalance, getSMSBalance, getBkashConfig, createBkashPayment } from '../services/smsService';

interface SMSPackage {
  id: number;
  name: string;
  smsCount: number;
  price: number;
  isPopular?: boolean;
  features: string[];
  color: string;
}

export const BuySMSView: React.FC = () => {
  const [selectedPackage, setSelectedPackage] = useState<SMSPackage | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [showSuccess, setShowSuccess] = useState(false);

  const packages: SMSPackage[] = [
    {
      id: 1,
      name: 'Starter',
      smsCount: 400,
      price: 250,
      color: 'blue',
      features: [
        'Non-Masking SMS',
        'Validity: 30 Days',
        'Instant Delivery',
        'API Access'
      ]
    },
    {
      id: 2,
      name: 'Business Pro',
      smsCount: 900,
      price: 500,
      isPopular: true,
      color: 'orange',
      features: [
        'Non-Masking SMS',
        'Validity: Unlimited',
        'Instant Delivery',
        'API Access',
        'Priority Support'
      ]
    },
    {
      id: 3,
      name: 'Enterprise',
      smsCount: 2000,
      price: 1000,
      color: 'purple',
      features: [
        'Non-Masking SMS',
        'Validity: Unlimited',
        'High Speed TPS',
        'Dedicated Manager',
        'API Access'
      ]
    }
  ];

  const handleBuy = async (method: 'bkash' | 'manual') => {
    setIsProcessing(true);
    
    if (method === 'bkash') {
        const bkashConfig = await getBkashConfig();
        
        if (!bkashConfig || !bkashConfig.appKey || !bkashConfig.appSecret) {
            alert("bKash is not configured in the database. Please configure it via Dashboard > Connections.");
            setIsProcessing(false);
            return;
        }

        if (selectedPackage) {
            const res = await createBkashPayment(selectedPackage.price, selectedPackage.smsCount);
            if (res.status === 'success' && res.bkashURL) {
                // Redirect to bKash gateway
                window.location.href = res.bkashURL;
            } else {
                alert("Payment initiation failed: " + (res.message || "Unknown error"));
                setIsProcessing(false);
            }
        }
        return;
    }

    // Fallback for manual or dummy (original logic)
    setTimeout(async () => {
      if (selectedPackage) {
        try {
          const currentBalance = await getSMSBalance();
          await saveSMSBalance(currentBalance + selectedPackage.smsCount);
        } catch (e) {
          console.error("Failed to update balance", e);
        }
      }
      setIsProcessing(false);
      setShowSuccess(true);
    }, 2000);
  };

  return (
    <div className="space-y-8 animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-start">
        <div className="text-center space-y-2 max-w-2xl mx-auto flex-1">
            <h2 className="text-3xl font-black text-gray-800">Buy SMS Credits</h2>
            <p className="text-gray-500">Choose a package that suits your business needs. Instant activation.</p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto mt-8">
        {packages.map((pkg) => (
          <div 
            key={pkg.id} 
            className={`relative bg-white rounded-3xl border transition-all duration-300 hover:-translate-y-2 flex flex-col ${
              pkg.isPopular 
                ? 'border-orange-500 shadow-2xl shadow-orange-100 ring-4 ring-orange-500/10 scale-105 z-10' 
                : 'border-gray-100 shadow-sm hover:shadow-xl'
            }`}
          >
            {pkg.isPopular && (
              <div className="absolute -top-4 left-1/2 -translate-x-1/2 bg-gradient-to-r from-orange-600 to-red-500 text-white px-4 py-1 rounded-full text-xs font-black uppercase tracking-widest shadow-lg flex items-center gap-1">
                <Zap size={12} fill="currentColor" /> Best Value
              </div>
            )}

            <div className="p-8 flex-1">
              <h3 className={`font-bold text-lg mb-2 ${pkg.isPopular ? 'text-orange-600' : 'text-gray-500'}`}>
                {pkg.name}
              </h3>
              <div className="flex items-baseline gap-1 mb-6">
                <span className="text-4xl font-black text-gray-800">৳{pkg.price}</span>
                <span className="text-gray-400 font-medium">/ {pkg.smsCount} SMS</span>
              </div>
              
              <div className="space-y-4 mb-8">
                {pkg.features.map((feature, idx) => (
                  <div key={idx} className="flex items-center gap-3 text-sm text-gray-600">
                    <div className={`p-1 rounded-full ${pkg.isPopular ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-400'}`}>
                      <CheckCircle2 size={12} />
                    </div>
                    {feature}
                  </div>
                ))}
              </div>
            </div>

            <div className="p-8 pt-0 mt-auto">
              <button 
                onClick={() => setSelectedPackage(pkg)}
                className={`w-full py-4 rounded-2xl font-bold transition-all active:scale-95 shadow-lg ${
                  pkg.isPopular 
                    ? 'bg-orange-600 text-white hover:bg-orange-700 shadow-orange-200' 
                    : 'bg-gray-900 text-white hover:bg-black shadow-gray-200'
                }`}
              >
                Choose {pkg.name}
              </button>
            </div>
          </div>
        ))}
      </div>

      <div className="max-w-4xl mx-auto mt-12 bg-blue-50 rounded-2xl p-6 border border-blue-100 flex items-start gap-4">
        <div className="p-3 bg-white rounded-xl text-blue-600 shadow-sm">
          <ShieldCheck size={24} />
        </div>
        <div>
          <h4 className="font-bold text-blue-900 mb-1">Secure & Automated</h4>
          <p className="text-sm text-blue-700 leading-relaxed">
            Your credits will be added to your account immediately after payment verification via bKash. 
            We use secure tokenized payment API.
          </p>
        </div>
      </div>

      {/* Payment Modal */}
      {selectedPackage && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[200] flex items-center justify-center p-4 animate-in fade-in duration-200">
          {!showSuccess ? (
            <div className="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in-95 duration-200">
              <div className="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <div>
                  <h3 className="text-lg font-black text-gray-800">Checkout</h3>
                  <p className="text-xs text-gray-500">Complete your purchase</p>
                </div>
                <button onClick={() => setSelectedPackage(null)} className="p-2 hover:bg-gray-200 rounded-full transition-colors text-gray-400">
                  <X size={20} />
                </button>
              </div>

              <div className="p-6 space-y-6">
                <div className="bg-orange-50 p-4 rounded-2xl border border-orange-100 flex justify-between items-center">
                  <div>
                    <p className="text-xs font-bold text-orange-600 uppercase mb-1">Package</p>
                    <p className="font-bold text-gray-800">{selectedPackage.name} ({selectedPackage.smsCount} SMS)</p>
                  </div>
                  <div className="text-right">
                    <p className="text-xs font-bold text-orange-600 uppercase mb-1">Total</p>
                    <p className="text-xl font-black text-gray-800">৳{selectedPackage.price}</p>
                  </div>
                </div>

                <div className="space-y-3">
                  <p className="text-xs font-bold text-gray-400 uppercase tracking-widest">Select Payment Method</p>
                  <div className="grid grid-cols-2 gap-3">
                    <button 
                      onClick={() => handleBuy('bkash')}
                      className="p-4 border-2 border-pink-100 bg-pink-50 rounded-xl hover:border-pink-500 transition-all flex flex-col items-center gap-2 group cursor-pointer"
                    >
                      <div className="w-10 h-10 bg-white text-pink-600 rounded-lg flex items-center justify-center shadow-sm">
                        <Smartphone size={20} />
                      </div>
                      <span className="text-xs font-black text-pink-600">bKash Pay</span>
                    </button>
                    
                    <button 
                      className="p-4 border border-gray-200 rounded-xl opacity-50 flex flex-col items-center gap-2 group cursor-not-allowed"
                      title="Coming Soon"
                    >
                      <div className="w-10 h-10 bg-gray-100 text-gray-400 rounded-lg flex items-center justify-center">
                        <CreditCard size={20} />
                      </div>
                      <span className="text-xs font-bold text-gray-400">Cards (Soon)</span>
                    </button>
                  </div>
                </div>

                <div className="pt-2 text-center text-xs text-gray-400 italic">
                    By clicking pay, you will be redirected to payment gateway.
                </div>
              </div>
            </div>
          ) : (
            <div className="bg-white rounded-[3rem] shadow-2xl w-full max-w-sm overflow-hidden animate-in zoom-in-95 duration-200 p-8 text-center relative">
               <div className="w-20 h-20 bg-green-500 text-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-xl shadow-green-200 animate-in zoom-in duration-300">
                 <CheckCircle size={40} strokeWidth={3} />
               </div>
               <h3 className="text-2xl font-black text-gray-800 mb-2">Payment Successful!</h3>
               <p className="text-gray-500 text-sm mb-8">
                 You have successfully purchased <br/>
                 <span className="font-bold text-gray-800">{selectedPackage.smsCount} SMS Credits</span>.
               </p>
               <button 
                 onClick={() => { setShowSuccess(false); setSelectedPackage(null); }}
                 className="w-full py-4 bg-gray-900 text-white font-bold rounded-2xl shadow-lg hover:bg-black transition-all"
               >
                 Done
               </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
};
