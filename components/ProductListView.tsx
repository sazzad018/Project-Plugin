import React, { useState, useMemo, useEffect, useRef } from 'react';
import { Search, Plus, X, Loader2, Image as ImageIcon, UploadCloud, Box, Layers, Settings, Save, Edit, RefreshCw } from 'lucide-react';
import { InventoryProduct } from '../types';
import { fetchCategoriesFromWP, WPCategory, createProductInWP, updateProductInWP, getProductFromWP, CreateProductPayload, uploadImageToWP } from '../services/wordpressService';

interface ProductListViewProps {
  initialProducts?: InventoryProduct[];
}

const CategoryFilterItem: React.FC<{ 
  label: string; 
  count: number; 
  checked: boolean;
  onToggle: () => void;
}> = ({ label, count, checked, onToggle }) => (
  <div 
    onClick={onToggle}
    className="flex items-center justify-between py-2 group cursor-pointer"
  >
    <div className="flex items-center gap-3">
      <div className={`w-4 h-4 rounded border flex items-center justify-center transition-colors ${checked ? 'bg-orange-500 border-orange-500' : 'border-gray-300 bg-white'}`}>
        {checked && <div className="w-1.5 h-1.5 rounded-full bg-white" />}
      </div>
      <span className={`text-xs ${checked ? 'text-gray-900 font-medium' : 'text-gray-500 group-hover:text-gray-700'}`}>{label}</span>
    </div>
    <span className="text-[10px] text-gray-400 font-bold">{count}</span>
  </div>
);

export const ProductListView: React.FC<ProductListViewProps> = ({ initialProducts = [] }) => {
  const [allProducts, setAllProducts] = useState<InventoryProduct[]>(initialProducts);
  const [categories, setCategories] = useState<WPCategory[]>([]);
  const [loadingCategories, setLoadingCategories] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategories, setSelectedCategories] = useState<string[]>([]);
  const [priceRange, setPriceRange] = useState({ min: 0, max: 1000000 });
  const [statusFilter, setStatusFilter] = useState<'All' | 'Active' | 'Inactive'>('All');
  
  // Modal State
  const [showAddModal, setShowAddModal] = useState(false);
  const [isCreating, setIsCreating] = useState(false);
  const [activeTab, setActiveTab] = useState<'general' | 'inventory' | 'shipping'>('general');
  
  // Main Image State
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [selectedImageFile, setSelectedImageFile] = useState<File | null>(null);
  const [imagePreviewUrl, setImagePreviewUrl] = useState<string>('');
  
  // Gallery State
  const galleryInputRef = useRef<HTMLInputElement>(null);
  const [galleryFiles, setGalleryFiles] = useState<{ id: string, file: File, preview: string }[]>([]);

  const [uploadingImage, setUploadingImage] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [loadingDetails, setLoadingDetails] = useState(false);
  
  // Detailed Product State
  const [productData, setProductData] = useState({
    name: '',
    description: '',
    short_description: '',
    regular_price: '',
    sale_price: '',
    sku: '',
    stock_status: 'instock',
    manage_stock: false,
    stock_quantity: '',
    weight: '',
    length: '',
    width: '',
    height: '',
    image_url: '', // This will hold the uploaded URL
    gallery_urls: [''] as string[],
    selected_categories: [] as number[],
    status: 'publish'
  });

  useEffect(() => {
    const loadCategories = async () => {
      setLoadingCategories(true);
      try {
        const fetchedCats = await fetchCategoriesFromWP();
        setCategories(fetchedCats);
      } catch (err) {
        console.error("Failed to load categories:", err);
      } finally {
        setLoadingCategories(false);
      }
    };
    loadCategories();
  }, []);

  useEffect(() => {
    if (initialProducts.length > 0) {
      setAllProducts(initialProducts);
    }
  }, [initialProducts]);

  const filteredProducts = useMemo(() => {
    return allProducts.filter(p => {
      const matchesSearch = p.name.toLowerCase().includes(searchTerm.toLowerCase()) || 
                           p.brand.toLowerCase().includes(searchTerm.toLowerCase());
      const matchesCategory = selectedCategories.length === 0 || selectedCategories.includes(p.category);
      const matchesPrice = p.price >= priceRange.min && p.price <= priceRange.max;
      const matchesStatus = statusFilter === 'All' || 
                           (statusFilter === 'Active' && p.status) || 
                           (statusFilter === 'Inactive' && !p.status);
      
      return matchesSearch && matchesCategory && matchesPrice && matchesStatus;
    });
  }, [allProducts, searchTerm, selectedCategories, priceRange, statusFilter]);

  const toggleStatus = (id: string) => {
    setAllProducts(prev => prev.map(p => p.id === id ? { ...p, status: !p.status } : p));
  };

  const toggleCategory = (cat: string) => {
    setSelectedCategories(prev => 
      prev.includes(cat) ? prev.filter(c => c !== cat) : [...prev, cat]
    );
  };

  const toggleProductCategory = (catId: number) => {
    setProductData(prev => {
      const exists = prev.selected_categories.includes(catId);
      return {
        ...prev,
        selected_categories: exists 
          ? prev.selected_categories.filter(id => id !== catId)
          : [...prev.selected_categories, catId]
      };
    });
  };

  const clearAllFilters = () => {
    setSearchTerm('');
    setSelectedCategories([]);
    setPriceRange({ min: 0, max: 1000000 });
    setStatusFilter('All');
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0] as File;
      setSelectedImageFile(file);
      // Create local preview
      const preview = URL.createObjectURL(file);
      setImagePreviewUrl(preview);
    }
  };

  const handleGalleryFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files) {
      const newFiles = Array.from(e.target.files).map((file) => ({
        id: Math.random().toString(36).substr(2, 9),
        file: file as File,
        preview: URL.createObjectURL(file as Blob)
      }));
      setGalleryFiles(prev => [...prev, ...newFiles]);
    }
    // Reset input so same file can be selected again if needed
    if (e.target) e.target.value = '';
  };

  const resetForm = () => {
    setProductData({
      name: '', description: '', short_description: '', regular_price: '', sale_price: '',
      sku: '', stock_status: 'instock', manage_stock: false, stock_quantity: '',
      weight: '', length: '', width: '', height: '',
      image_url: '', gallery_urls: [''], selected_categories: [], status: 'publish'
    });
    setSelectedImageFile(null);
    setImagePreviewUrl('');
    setGalleryFiles([]);
    setEditingId(null);
  };

  const openAddModal = () => {
    resetForm();
    setShowAddModal(true);
  };

  const openEditModal = async (product: InventoryProduct) => {
    setLoadingDetails(true);
    setEditingId(product.id);
    setGalleryFiles([]); // Clear pending files
    
    try {
      // Fetch full details from WP because InventoryProduct list only has summary
      const fullProduct = await getProductFromWP(product.id);
      
      if (fullProduct) {
        setProductData({
          name: fullProduct.name,
          description: fullProduct.description ? fullProduct.description.replace(/<[^>]+>/g, '') : '', // Simple strip HTML
          short_description: fullProduct.short_description ? fullProduct.short_description.replace(/<[^>]+>/g, '') : '',
          regular_price: fullProduct.regular_price || '',
          sale_price: fullProduct.sale_price || '',
          sku: fullProduct.sku || '',
          stock_status: fullProduct.stock_status || 'instock',
          manage_stock: fullProduct.manage_stock || false,
          stock_quantity: fullProduct.stock_quantity ? fullProduct.stock_quantity.toString() : '',
          weight: fullProduct.weight || '',
          length: fullProduct.dimensions?.length || '',
          width: fullProduct.dimensions?.width || '',
          height: fullProduct.dimensions?.height || '',
          image_url: fullProduct.images?.[0]?.src || '',
          gallery_urls: fullProduct.images?.slice(1).map((img: any) => img.src) || [],
          selected_categories: fullProduct.categories?.map((c: any) => c.id) || [],
          status: fullProduct.status || 'publish'
        });
        setImagePreviewUrl(fullProduct.images?.[0]?.src || '');
        setShowAddModal(true);
      } else {
        alert("Could not fetch product details. Please check connection.");
      }
    } catch (e) {
      console.error(e);
      alert("Error loading product details");
    } finally {
      setLoadingDetails(false);
    }
  };

  const handleSaveProduct = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!productData.name || !productData.regular_price) {
      alert("Product Name and Regular Price are required!");
      return;
    }

    setIsCreating(true);
    setUploadingImage(true);

    try {
      let finalImageUrl = productData.image_url;

      // Handle Main Image Upload
      if (selectedImageFile) {
        const uploadResult = await uploadImageToWP(selectedImageFile);
        
        if (uploadResult.success && uploadResult.url) {
          finalImageUrl = uploadResult.url;
        } else {
          // If upload fails, show specific error and ask user if they want to continue
          const proceed = confirm(`Image upload failed: ${uploadResult.error || 'Unknown Error'}\n\nDo you want to continue without the image?`);
          if (!proceed) {
            setIsCreating(false);
            setUploadingImage(false);
            return;
          }
        }
      }

      // Handle Gallery Images Upload
      const uploadedGalleryUrls: string[] = [];
      for (const item of galleryFiles) {
          const res = await uploadImageToWP(item.file);
          if (res.success && res.url) {
              uploadedGalleryUrls.push(res.url);
          } else {
             console.warn("Failed to upload a gallery image", res.error);
          }
      }

      const payload: CreateProductPayload = {
        name: productData.name,
        type: 'simple',
        regular_price: productData.regular_price,
        description: productData.description,
        short_description: productData.short_description,
        status: productData.status as any,
        categories: productData.selected_categories.map(id => ({ id })),
        images: [
          ...(finalImageUrl ? [{ src: finalImageUrl }] : []),
          ...productData.gallery_urls.filter(url => url && url.length > 0).map(url => ({ src: url })),
          ...uploadedGalleryUrls.map(url => ({ src: url }))
        ]
      };

      if (productData.sale_price) payload.sale_price = productData.sale_price;
      if (productData.sku) payload.sku = productData.sku;
      
      // Inventory
      payload.manage_stock = productData.manage_stock;
      if (productData.manage_stock && productData.stock_quantity) {
        payload.stock_quantity = parseInt(productData.stock_quantity);
      }
      if (productData.stock_status) payload.stock_status = productData.stock_status as any;

      // Shipping
      if (productData.weight) payload.weight = productData.weight;
      if (productData.length || productData.width || productData.height) {
        payload.dimensions = {
          length: productData.length,
          width: productData.width,
          height: productData.height
        };
      }

      let result;
      if (editingId) {
        result = await updateProductInWP(editingId, payload);
      } else {
        result = await createProductInWP(payload);
      }
      
      if (result.success && result.product) {
        const updatedProduct: InventoryProduct = {
          id: result.product.id.toString(),
          name: result.product.name,
          brand: 'N/A',
          category: result.product.categories?.[0]?.name || 'Uncategorized',
          price: parseFloat(result.product.price || '0'),
          discountPercent: 0,
          stock: result.product.stock_quantity || 0,
          status: result.product.status === 'publish',
          img: result.product.images?.[0]?.src || 'https://via.placeholder.com/150'
        };
        
        if (editingId) {
          setAllProducts(prev => prev.map(p => p.id === editingId ? updatedProduct : p));
          alert("Product Updated Successfully!");
        } else {
          setAllProducts(prev => [updatedProduct, ...prev]);
          alert("Product Published Successfully!");
        }
        
        setShowAddModal(false);
        resetForm();
      } else {
        alert("Error: " + result.message);
      }
    } catch (error) {
      console.error(error);
      alert("Failed to save product");
    } finally {
      setIsCreating(false);
      setUploadingImage(false);
    }
  };

  return (
    <div className="flex gap-6 animate-in fade-in duration-500 pb-20 relative">
      {loadingDetails && (
        <div className="absolute inset-0 bg-white/50 backdrop-blur-[2px] z-[50] flex items-center justify-center rounded-xl">
          <div className="flex flex-col items-center gap-3">
             <Loader2 size={32} className="animate-spin text-orange-600" />
             <span className="text-sm font-bold text-gray-600">Loading Product Details...</span>
          </div>
        </div>
      )}

      <div className="flex-1 space-y-4">
        <div className="bg-white rounded-lg border border-gray-100 shadow-sm overflow-hidden">
          <div className="p-4 flex items-center justify-between border-b border-gray-50">
            <div className="flex items-center gap-2">
              <h2 className="text-lg font-bold text-gray-800">All Products</h2>
              <span className="w-6 h-6 rounded-full border border-orange-500 text-orange-500 flex items-center justify-center text-[10px] font-bold">
                {filteredProducts.length}
              </span>
            </div>

            <div className="flex items-center gap-3">
              <select 
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value as any)}
                className="px-4 py-2 border border-gray-200 rounded-md text-sm text-gray-500 outline-none"
              >
                <option value="All">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
              <div className="relative">
                <input 
                  type="text" 
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  placeholder="Search Products..." 
                  className="pl-4 pr-10 py-2 border border-gray-200 rounded-md text-sm w-64 outline-none"
                />
                <Search className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
              </div>
              <button 
                onClick={openAddModal}
                className="bg-orange-600 text-white px-6 py-2 rounded-lg text-sm font-bold flex items-center gap-2 shadow-lg shadow-orange-200 hover:bg-orange-700 transition-all"
              >
                <Plus size={16} /> Add Product
              </button>
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr className="border-b border-gray-50">
                  <th className="px-6 py-4 text-xs font-medium text-gray-400 uppercase">Product Name</th>
                  <th className="px-6 py-4 text-xs font-medium text-gray-400 uppercase">Price</th>
                  <th className="px-6 py-4 text-xs font-medium text-gray-400 uppercase">Stock</th>
                  <th className="px-6 py-4 text-xs font-medium text-gray-400 uppercase">Status</th>
                  <th className="px-6 py-4 text-xs font-medium text-gray-400 uppercase text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {filteredProducts.map((p) => (
                  <tr key={p.id} className="hover:bg-gray-50/30">
                    <td className="px-6 py-5 flex items-center gap-4">
                      <img src={p.img} alt={p.name} className="w-12 h-12 rounded border p-1 object-cover" />
                      <div>
                        <p className="text-sm font-medium text-gray-800 line-clamp-1">{p.name}</p>
                        <p className="text-[10px] text-gray-400">{p.category}</p>
                      </div>
                    </td>
                    <td className="px-6 py-5 text-sm font-medium">৳{p.price.toLocaleString()}</td>
                    <td className="px-6 py-5">
                      <span className="text-xs font-bold text-gray-700">{p.stock}</span>
                    </td>
                    <td className="px-6 py-5">
                      <div 
                        onClick={() => toggleStatus(p.id)}
                        className={`w-10 h-5 rounded-full relative cursor-pointer transition-colors ${p.status ? 'bg-green-500' : 'bg-gray-200'}`}
                      >
                        <div className={`absolute top-1 w-3 h-3 bg-white rounded-full transition-all ${p.status ? 'right-1' : 'left-1'}`} />
                      </div>
                    </td>
                    <td className="px-6 py-5 text-right">
                      <button 
                        onClick={() => openEditModal(p)}
                        className="text-orange-600 text-xs font-bold hover:underline flex items-center justify-end gap-1 ml-auto"
                      >
                        <Edit size={12} /> Details
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div className="w-80 space-y-6">
        <div className="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
          <div className="flex justify-between items-center mb-6">
            <h3 className="text-sm font-bold text-gray-800">Tags</h3>
            <button onClick={clearAllFilters} className="text-[10px] text-gray-400 underline uppercase">Clear All</button>
          </div>
          <div className="flex flex-wrap gap-2">
            {searchTerm && (
              <span className="px-3 py-1 bg-orange-50 text-orange-600 text-[10px] font-bold rounded-full flex items-center gap-2">
                "{searchTerm}" <X size={10} className="cursor-pointer" onClick={() => setSearchTerm('')} />
              </span>
            )}
            {selectedCategories.map(cat => (
              <span key={cat} className="px-3 py-1 bg-blue-50 text-blue-600 text-[10px] font-bold rounded-full flex items-center gap-2">
                {cat} <X size={10} className="cursor-pointer" onClick={() => toggleCategory(cat)} />
              </span>
            ))}
            {!searchTerm && selectedCategories.length === 0 && (
              <p className="text-[10px] text-gray-400 italic">No active tags</p>
            )}
          </div>
        </div>

        <div className="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
          <h3 className="text-sm font-bold text-gray-800 mb-6">Category</h3>
          <div className="space-y-1 max-h-[300px] overflow-y-auto custom-scrollbar pr-2">
            {loadingCategories ? (
              <Loader2 className="animate-spin text-orange-500 mx-auto" />
            ) : categories.map(cat => (
              <CategoryFilterItem 
                key={cat.id} 
                label={cat.name} 
                count={cat.count} 
                checked={selectedCategories.includes(cat.name)}
                onToggle={() => toggleCategory(cat.name)}
              />
            ))}
          </div>
        </div>
      </div>

      {/* Advanced Add/Edit Product Modal - WordPress Style */}
      {showAddModal && (
        <div className="fixed inset-0 bg-gray-100/90 backdrop-blur-sm z-[200] flex items-start justify-center overflow-y-auto p-4 md:p-8">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-6xl flex flex-col min-h-[85vh] animate-in zoom-in-95 duration-200">
            {/* Header */}
            <div className="p-5 border-b border-gray-100 flex justify-between items-center bg-white sticky top-0 z-20 rounded-t-xl">
              <div>
                <h3 className="text-xl font-bold text-gray-800 flex items-center gap-2">
                  <Box className="text-orange-600" /> {editingId ? 'Edit Product' : 'Add New Product'}
                </h3>
                <p className="text-xs text-gray-400 mt-1">{editingId ? 'Update product details and inventory.' : 'Create a new product card for your store.'}</p>
              </div>
              <div className="flex gap-3">
                <button onClick={() => setShowAddModal(false)} className="px-4 py-2 border border-gray-200 rounded-lg text-sm font-bold text-gray-600 hover:bg-gray-50 transition-all">Cancel</button>
                <button 
                  onClick={handleSaveProduct}
                  disabled={isCreating || uploadingImage}
                  className="px-6 py-2 bg-orange-600 text-white rounded-lg text-sm font-bold shadow-lg hover:bg-orange-700 transition-all flex items-center gap-2 disabled:opacity-70"
                >
                  {(uploadingImage || isCreating) ? <Loader2 size={16} className="animate-spin" /> : (editingId ? <RefreshCw size={16} /> : <Save size={16} />)}
                  {uploadingImage ? 'Uploading Image...' : (isCreating ? (editingId ? 'Updating...' : 'Publishing...') : (editingId ? 'Update Product' : 'Publish Product'))}
                </button>
              </div>
            </div>

            <div className="flex-1 flex flex-col lg:flex-row bg-gray-50">
              {/* Left Column - Main Content */}
              <div className="flex-1 p-6 space-y-6">
                
                {/* Product Name */}
                <div className="space-y-2">
                   <label className="text-xs font-bold text-gray-500 uppercase">Product Name <span className="text-red-500">*</span></label>
                   <input 
                     type="text" 
                     className="w-full p-3 border border-gray-200 rounded-lg text-lg font-medium focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none shadow-sm"
                     placeholder="Product Name"
                     value={productData.name}
                     onChange={e => setProductData({...productData, name: e.target.value})}
                   />
                </div>

                {/* Description */}
                <div className="space-y-2">
                   <label className="text-xs font-bold text-gray-500 uppercase">Product Description</label>
                   <textarea 
                     className="w-full p-4 border border-gray-200 rounded-lg text-sm min-h-[200px] focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none shadow-sm resize-y"
                     placeholder="Detailed description of the product..."
                     value={productData.description}
                     onChange={e => setProductData({...productData, description: e.target.value})}
                   />
                </div>

                {/* Product Data Meta Box */}
                <div className="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                  <div className="border-b border-gray-200 bg-gray-50 px-4 py-3 flex items-center gap-2">
                    <Settings size={16} className="text-gray-500" />
                    <h4 className="font-bold text-gray-700 text-sm">Product Data</h4>
                    <span className="ml-auto text-xs font-medium text-gray-400 px-2 py-1 bg-white border rounded">Simple product</span>
                  </div>
                  
                  <div className="flex min-h-[250px]">
                     {/* Tabs */}
                     <div className="w-40 bg-gray-50 border-r border-gray-200">
                        <button 
                          onClick={() => setActiveTab('general')}
                          className={`w-full text-left px-4 py-3 text-xs font-bold flex items-center gap-2 border-l-4 transition-all ${activeTab === 'general' ? 'bg-white border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:bg-gray-100'}`}
                        >
                          <Settings size={14} /> General
                        </button>
                        <button 
                          onClick={() => setActiveTab('inventory')}
                          className={`w-full text-left px-4 py-3 text-xs font-bold flex items-center gap-2 border-l-4 transition-all ${activeTab === 'inventory' ? 'bg-white border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:bg-gray-100'}`}
                        >
                          <Layers size={14} /> Inventory
                        </button>
                        <button 
                          onClick={() => setActiveTab('shipping')}
                          className={`w-full text-left px-4 py-3 text-xs font-bold flex items-center gap-2 border-l-4 transition-all ${activeTab === 'shipping' ? 'bg-white border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:bg-gray-100'}`}
                        >
                          <UploadCloud size={14} /> Shipping
                        </button>
                     </div>

                     {/* Tab Content */}
                     <div className="flex-1 p-6">
                        {activeTab === 'general' && (
                          <div className="space-y-4 max-w-md animate-in fade-in">
                             <div className="grid grid-cols-3 items-center gap-4">
                                <label className="text-xs font-bold text-gray-600 text-right">Regular price (৳)</label>
                                <input 
                                  type="number" 
                                  className="col-span-2 p-2 border border-gray-200 rounded text-sm focus:border-orange-500 outline-none"
                                  placeholder="0.00"
                                  value={productData.regular_price}
                                  onChange={e => setProductData({...productData, regular_price: e.target.value})}
                                />
                             </div>
                             <div className="grid grid-cols-3 items-center gap-4">
                                <label className="text-xs font-bold text-gray-600 text-right">Sale price (৳)</label>
                                <input 
                                  type="number" 
                                  className="col-span-2 p-2 border border-gray-200 rounded text-sm focus:border-orange-500 outline-none"
                                  placeholder="0.00"
                                  value={productData.sale_price}
                                  onChange={e => setProductData({...productData, sale_price: e.target.value})}
                                />
                             </div>
                          </div>
                        )}

                        {activeTab === 'inventory' && (
                          <div className="space-y-4 max-w-md animate-in fade-in">
                             <div className="grid grid-cols-3 items-center gap-4">
                                <label className="text-xs font-bold text-gray-600 text-right">SKU</label>
                                <input 
                                  type="text" 
                                  className="col-span-2 p-2 border border-gray-200 rounded text-sm focus:border-orange-500 outline-none"
                                  placeholder="SKU-123"
                                  value={productData.sku}
                                  onChange={e => setProductData({...productData, sku: e.target.value})}
                                />
                             </div>
                             <div className="grid grid-cols-3 items-center gap-4">
                                <label className="text-xs font-bold text-gray-600 text-right">Manage stock?</label>
                                <div className="col-span-2 flex items-center">
                                  <input 
                                    type="checkbox" 
                                    className="w-4 h-4 text-orange-600 rounded"
                                    checked={productData.manage_stock}
                                    onChange={e => setProductData({...productData, manage_stock: e.target.checked})}
                                  />
                                  <span className="ml-2 text-xs text-gray-500">Enable stock management at product level</span>
                                </div>
                             </div>
                             {productData.manage_stock && (
                               <div className="grid grid-cols-3 items-center gap-4">
                                  <label className="text-xs font-bold text-gray-600 text-right">Stock quantity</label>
                                  <input 
                                    type="number" 
                                    className="col-span-2 p-2 border border-gray-200 rounded text-sm focus:border-orange-500 outline-none"
                                    value={productData.stock_quantity}
                                    onChange={e => setProductData({...productData, stock_quantity: e.target.value})}
                                  />
                               </div>
                             )}
                             <div className="grid grid-cols-3 items-center gap-4">
                                <label className="text-xs font-bold text-gray-600 text-right">Stock status</label>
                                <select 
                                  className="col-span-2 p-2 border border-gray-200 rounded text-sm focus:border-orange-500 outline-none bg-white"
                                  value={productData.stock_status}
                                  onChange={e => setProductData({...productData, stock_status: e.target.value})}
                                >
                                  <option value="instock">In stock</option>
                                  <option value="outofstock">Out of stock</option>
                                  <option value="onbackorder">On backorder</option>
                                </select>
                             </div>
                          </div>
                        )}

                        {activeTab === 'shipping' && (
                          <div className="space-y-4 max-w-md animate-in fade-in">
                             <div className="grid grid-cols-3 items-center gap-4">
                                <label className="text-xs font-bold text-gray-600 text-right">Weight (kg)</label>
                                <input 
                                  type="number" 
                                  step="0.01"
                                  className="col-span-2 p-2 border border-gray-200 rounded text-sm focus:border-orange-500 outline-none"
                                  placeholder="0.00"
                                  value={productData.weight}
                                  onChange={e => setProductData({...productData, weight: e.target.value})}
                                />
                             </div>
                             <div className="grid grid-cols-3 items-start gap-4">
                                <label className="text-xs font-bold text-gray-600 text-right pt-2">Dimensions (cm)</label>
                                <div className="col-span-2 flex gap-2">
                                  <input type="number" placeholder="Length" className="w-full p-2 border border-gray-200 rounded text-sm outline-none" value={productData.length} onChange={e => setProductData({...productData, length: e.target.value})} />
                                  <input type="number" placeholder="Width" className="w-full p-2 border border-gray-200 rounded text-sm outline-none" value={productData.width} onChange={e => setProductData({...productData, width: e.target.value})} />
                                  <input type="number" placeholder="Height" className="w-full p-2 border border-gray-200 rounded text-sm outline-none" value={productData.height} onChange={e => setProductData({...productData, height: e.target.value})} />
                                </div>
                             </div>
                          </div>
                        )}
                     </div>
                  </div>
                </div>

                {/* Short Description */}
                <div className="space-y-2">
                   <label className="text-xs font-bold text-gray-500 uppercase">Product Short Description</label>
                   <textarea 
                     className="w-full p-4 border border-gray-200 rounded-lg text-sm min-h-[100px] focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none shadow-sm resize-y"
                     placeholder="Brief summary..."
                     value={productData.short_description}
                     onChange={e => setProductData({...productData, short_description: e.target.value})}
                   />
                </div>

              </div>

              {/* Right Column - Sidebar */}
              <div className="w-full lg:w-80 border-l border-gray-200 bg-white p-6 space-y-6">
                
                {/* Publish Box */}
                <div className="bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-3">
                  <h4 className="font-bold text-sm text-gray-700">Publish</h4>
                  <div className="flex items-center justify-between text-xs text-gray-600">
                    <span>Status:</span>
                    <select 
                      className="bg-white border border-gray-200 rounded px-2 py-1 outline-none"
                      value={productData.status}
                      onChange={e => setProductData({...productData, status: e.target.value})}
                    >
                      <option value="publish">Published</option>
                      <option value="draft">Draft</option>
                      <option value="pending">Pending Review</option>
                    </select>
                  </div>
                  <div className="flex items-center justify-between text-xs text-gray-600">
                    <span>Visibility:</span>
                    <span className="font-bold">Public</span>
                  </div>
                  <div className="pt-2 flex justify-between items-center">
                    <button onClick={() => setProductData({...productData, status: 'draft'})} className="text-xs text-gray-500 hover:text-orange-600 underline">Save Draft</button>
                    <button 
                      onClick={handleSaveProduct}
                      disabled={isCreating || uploadingImage}
                      className="px-4 py-2 bg-orange-600 text-white rounded text-xs font-bold shadow hover:bg-orange-700 transition-all disabled:opacity-70 flex items-center gap-1"
                    >
                      {uploadingImage ? <Loader2 size={12} className="animate-spin" /> : null}
                      {uploadingImage ? 'Uploading...' : (isCreating ? (editingId ? 'Updating...' : 'Publishing...') : (editingId ? 'Update' : 'Publish'))}
                    </button>
                  </div>
                </div>

                {/* Categories */}
                <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                   <div className="bg-gray-50 px-4 py-2 border-b border-gray-200">
                     <h4 className="font-bold text-sm text-gray-700">Product categories</h4>
                   </div>
                   <div className="p-4 max-h-60 overflow-y-auto custom-scrollbar space-y-2">
                      {loadingCategories ? (
                        <div className="flex justify-center py-4"><Loader2 className="animate-spin text-orange-500" /></div>
                      ) : categories.map(cat => (
                        <div key={cat.id} className="flex items-start gap-2">
                           <input 
                             type="checkbox" 
                             id={`cat-${cat.id}`} 
                             className="mt-1"
                             checked={productData.selected_categories.includes(cat.id)}
                             onChange={() => toggleProductCategory(cat.id)}
                           />
                           <label htmlFor={`cat-${cat.id}`} className="text-sm text-gray-600 select-none cursor-pointer leading-tight">{cat.name}</label>
                        </div>
                      ))}
                   </div>
                   <div className="bg-gray-50 px-4 py-2 border-t border-gray-200">
                     <button className="text-xs text-orange-600 hover:underline flex items-center gap-1">+ Add new category</button>
                   </div>
                </div>

                {/* Product Image */}
                <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                   <div className="bg-gray-50 px-4 py-2 border-b border-gray-200">
                     <h4 className="font-bold text-sm text-gray-700">Product image</h4>
                   </div>
                   <div className="p-4 space-y-3">
                      {(imagePreviewUrl || productData.image_url) ? (
                        <div className="relative group">
                          <img 
                            src={imagePreviewUrl || productData.image_url} 
                            alt="Product" 
                            className="w-full h-auto rounded border border-gray-200" 
                            onError={(e) => (e.currentTarget.src = 'https://via.placeholder.com/300?text=Invalid+URL')} 
                          />
                          <button 
                            onClick={() => {
                              setProductData({...productData, image_url: ''});
                              setImagePreviewUrl('');
                              setSelectedImageFile(null);
                            }}
                            className="absolute top-2 right-2 bg-red-500 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
                          >
                            <X size={12} />
                          </button>
                        </div>
                      ) : (
                        <div 
                          className="w-full h-32 bg-gray-50 rounded border-2 border-dashed border-gray-300 flex flex-col items-center justify-center text-gray-400 hover:border-orange-500 hover:bg-orange-50 hover:text-orange-600 transition-all cursor-pointer"
                          onClick={() => fileInputRef.current?.click()}
                        >
                          <UploadCloud size={24} />
                          <span className="text-xs mt-1 font-bold">Select Image</span>
                        </div>
                      )}
                      
                      <input 
                        type="file" 
                        ref={fileInputRef} 
                        className="hidden" 
                        accept="image/*" 
                        onChange={handleFileSelect} 
                      />

                      <div className="pt-2 border-t border-dashed border-gray-200">
                         <div className="flex items-center gap-2 mb-1">
                           <span className="text-[10px] font-bold text-gray-400 uppercase">Or use URL</span>
                         </div>
                         <input 
                          type="url" 
                          placeholder="https://..." 
                          className="w-full p-2 border border-gray-200 rounded text-xs outline-none focus:border-orange-500"
                          value={productData.image_url}
                          onChange={e => {
                            setProductData({...productData, image_url: e.target.value});
                            setImagePreviewUrl(''); // Clear file preview if URL is typed
                            setSelectedImageFile(null);
                          }}
                        />
                      </div>
                   </div>
                </div>

                {/* Product Gallery */}
                 <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                   <div className="bg-gray-50 px-4 py-2 border-b border-gray-200">
                     <h4 className="font-bold text-sm text-gray-700">Product gallery</h4>
                   </div>
                   <div className="p-4 space-y-3">
                      <div className="grid grid-cols-3 gap-2">
                        {/* Existing URLs */}
                        {productData.gallery_urls.filter(u => u).map((url, idx) => (
                          <div key={`url-${idx}`} className="relative group aspect-square">
                            <img src={url} alt="Gallery" className="w-full h-full object-cover rounded border border-gray-200" />
                            <button 
                              onClick={() => {
                                const newUrls = productData.gallery_urls.filter((_, i) => i !== idx);
                                setProductData({...productData, gallery_urls: newUrls});
                              }}
                              className="absolute top-1 right-1 bg-red-500 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
                            >
                              <X size={10} />
                            </button>
                          </div>
                        ))}
                        
                        {/* Pending Uploads */}
                        {galleryFiles.map((item) => (
                          <div key={item.id} className="relative group aspect-square">
                            <img src={item.preview} alt="Gallery Preview" className="w-full h-full object-cover rounded border border-gray-200 opacity-80" />
                            <button 
                              onClick={() => setGalleryFiles(prev => prev.filter(f => f.id !== item.id))}
                              className="absolute top-1 right-1 bg-red-500 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
                            >
                              <X size={10} />
                            </button>
                            <div className="absolute bottom-1 right-1 bg-blue-500 text-white text-[8px] px-1 rounded">New</div>
                          </div>
                        ))}

                        {/* Add Button */}
                        <div 
                          onClick={() => galleryInputRef.current?.click()}
                          className="aspect-square border-2 border-dashed border-gray-300 rounded flex flex-col items-center justify-center text-gray-400 hover:border-orange-500 hover:text-orange-600 hover:bg-orange-50 transition-colors cursor-pointer"
                        >
                          <Plus size={24} />
                          <span className="text-[10px] font-bold mt-1">Add Image</span>
                        </div>
                      </div>

                      <input 
                        type="file" 
                        ref={galleryInputRef} 
                        className="hidden" 
                        accept="image/*" 
                        multiple 
                        onChange={handleGalleryFileSelect} 
                      />
                      
                      <p className="text-[10px] text-gray-400 italic">Select multiple images to upload to gallery.</p>
                   </div>
                </div>

              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};