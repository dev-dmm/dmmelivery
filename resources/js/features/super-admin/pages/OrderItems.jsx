import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function SuperAdminOrderItems({ auth, orderItems, tenants, filters, stats }) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedTenant, setSelectedTenant] = useState(filters.tenant || '');
    const [perPage, setPerPage] = useState(filters.per_page || 24);

    const handleSearch = (e) => {
        e.preventDefault();
        
        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);
        if (selectedTenant) params.append('tenant', selectedTenant);
        if (perPage !== 24) params.append('per_page', perPage);

        router.get(route('super-admin.order-items'), Object.fromEntries(params));
    };

    const clearFilters = () => {
        setSearchTerm('');
        setSelectedTenant('');
        setPerPage(24);
        router.get(route('super-admin.order-items'));
    };

    const handleBuyNow = (orderItem) => {
        // Fake CTA action - could redirect to the product URL or show a modal
        alert(`Buy Now clicked for: ${orderItem.product_name}\nFrom: ${orderItem.tenant?.business_name || orderItem.tenant?.name}\nPrice: €${parseFloat(orderItem.final_unit_price).toFixed(2)}`);
    };

    const getPrimaryImage = (productImages) => {
        if (!productImages || !Array.isArray(productImages) || productImages.length === 0) {
            return null;
        }
        return productImages[0];
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Super Admin - E-Shop Products</h2>}
        >
            <Head title="Super Admin - Order Items" />

            <div className="py-12">
                <div className="mx-auto">
                    
                    {/* Statistics Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Total Products</div>
                            <div className="mt-2 text-3xl font-bold text-gray-900">{stats.total_items.toLocaleString()}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Products with Images</div>
                            <div className="mt-2 text-3xl font-bold text-gray-900">{stats.items_with_images.toLocaleString()}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Active Tenants</div>
                            <div className="mt-2 text-3xl font-bold text-gray-900">{stats.active_tenants.toLocaleString()}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Total Value</div>
                            <div className="mt-2 text-3xl font-bold text-gray-900">€{stats.total_value.toLocaleString()}</div>
                        </div>
                    </div>

                    {/* Search and Filters */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                        <div className="p-6">
                            <form onSubmit={handleSearch} method="GET" className="space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label htmlFor="search" className="block text-sm font-medium text-gray-700">
                                            Search Products
                                        </label>
                                        <input
                                            type="text"
                                            id="search"
                                            value={searchTerm}
                                            onChange={(e) => setSearchTerm(e.target.value)}
                                            placeholder="Product name, SKU, description..."
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>
                                    
                                    <div>
                                        <label htmlFor="tenant" className="block text-sm font-medium text-gray-700">
                                            Tenant
                                        </label>
                                        <select
                                            id="tenant"
                                            value={selectedTenant}
                                            onChange={(e) => setSelectedTenant(e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">All Tenants</option>
                                            {tenants.map((tenant) => (
                                                <option key={tenant.id} value={tenant.id}>
                                                    {tenant.business_name || tenant.name} ({tenant.subdomain})
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label htmlFor="per_page" className="block text-sm font-medium text-gray-700">
                                            Per Page
                                        </label>
                                        <select
                                            id="per_page"
                                            value={perPage}
                                            onChange={(e) => setPerPage(e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="12">12</option>
                                            <option value="24">24</option>
                                            <option value="48">48</option>
                                            <option value="96">96</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div className="flex space-x-3">
                                    <button
                                        type="submit"
                                        className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded"
                                    >
                                        Search
                                    </button>
                                    <button
                                        type="button"
                                        onClick={clearFilters}
                                        className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded"
                                    >
                                        Clear Filters
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {/* Products Grid */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {orderItems.data.length > 0 ? (
                                <>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                        {orderItems.data.map((item) => {
                                            const primaryImage = getPrimaryImage(item.product_images);
                                            
                                            return (
                                                <div key={item.id} className="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                                                    {/* Product Image */}
                                                    <div className="aspect-w-1 aspect-h-1 w-full overflow-hidden rounded-t-lg bg-gray-200">
                                                        {primaryImage ? (
                                                            <img
                                                                src={primaryImage}
                                                                alt={item.product_name}
                                                                className="h-48 w-full object-cover object-center"
                                                                onError={(e) => {
                                                                    e.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjNmNGY2Ii8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzZiNzI4MCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg==';
                                                                }}
                                                            />
                                                        ) : (
                                                            <div className="h-48 w-full bg-gray-200 flex items-center justify-center">
                                                                <div className="text-center text-gray-500">
                                                                    <svg className="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                                                    </svg>
                                                                    <p className="mt-2 text-sm text-gray-500">No Image</p>
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>

                                                    {/* Product Info */}
                                                    <div className="p-4">
                                                        {/* Tenant Business Name */}
                                                        <div className="text-xs font-medium text-indigo-600 mb-1">
                                                            {item.tenant?.business_name || item.tenant?.name || 'Unknown Store'}
                                                        </div>

                                                        {/* Product Name */}
                                                        <h3 className="text-sm font-medium text-gray-900 mb-2 line-clamp-2">
                                                            {item.product_name}
                                                        </h3>

                                                        {/* Product Details */}
                                                        <div className="text-xs text-gray-500 mb-3">
                                                            {item.product_sku && (
                                                                <div>SKU: {item.product_sku}</div>
                                                            )}
                                                            {item.product_brand && (
                                                                <div>Brand: {item.product_brand}</div>
                                                            )}
                                                            {item.product_category && (
                                                                <div>Category: {item.product_category}</div>
                                                            )}
                                                        </div>

                                                        {/* Price */}
                                                        <div className="flex items-center justify-between mb-3">
                                                            <div className="text-lg font-bold text-gray-900">
                                                                €{parseFloat(item.final_unit_price).toFixed(2)}
                                                            </div>
                                                            {item.quantity > 1 && (
                                                                <div className="text-sm text-gray-500">
                                                                    Qty: {item.quantity}
                                                                </div>
                                                            )}
                                                        </div>

                                                        {/* Buy Now Button */}
                                                        <button
                                                            onClick={() => handleBuyNow(item)}
                                                            className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition-colors duration-200"
                                                        >
                                                            Buy Now
                                                        </button>

                                                        {/* Additional Info */}
                                                        <div className="mt-2 text-xs text-gray-400">
                                                            Order: #{item.order?.order_number || item.order?.external_order_id}
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    {/* Pagination */}
                                    {orderItems.links && (
                                        <div className="mt-8 flex items-center justify-between">
                                            <div className="text-sm text-gray-700">
                                                Showing {orderItems.from} to {orderItems.to} of {orderItems.total} results
                                            </div>
                                            <div className="flex space-x-1">
                                                {orderItems.links.map((link, index) => {
                                                    if (!link.url) {
                                                        return (
                                                            <span
                                                                key={index}
                                                                className="px-3 py-2 text-sm font-medium rounded-md bg-gray-100 text-gray-400 cursor-not-allowed"
                                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                            />
                                                        );
                                                    }
                                                    return (
                                                        <Link
                                                            key={index}
                                                            href={link.url}
                                                            className={`px-3 py-2 text-sm font-medium rounded-md ${
                                                                link.active
                                                                    ? 'bg-indigo-600 text-white'
                                                                    : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                                                            }`}
                                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                                        />
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <div className="text-center py-12">
                                    <svg className="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                    <h3 className="mt-2 text-sm font-medium text-gray-900">No products found</h3>
                                    <p className="mt-1 text-sm text-gray-500">Try adjusting your search criteria.</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
