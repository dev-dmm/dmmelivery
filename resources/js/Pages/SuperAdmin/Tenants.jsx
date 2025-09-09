import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function SuperAdminTenants({ auth, tenants, filters }) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [perPage, setPerPage] = useState(filters.per_page || 25);

    const handleSearch = (e) => {
        e.preventDefault();
        
        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);
        if (perPage !== 25) params.append('per_page', perPage);

        router.get(route('super-admin.tenants'), Object.fromEntries(params));
    };

    const clearFilters = () => {
        setSearchTerm('');
        setPerPage(25);
        router.get(route('super-admin.tenants'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Super Admin - All Tenants</h2>}
        >
            <Head title="Super Admin - Tenants" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    
                    {/* Search and Filters */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                        <div className="p-6">
                            <form onSubmit={handleSearch} className="space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label htmlFor="search" className="block text-sm font-medium text-gray-700">
                                            Search Tenants
                                        </label>
                                        <input
                                            type="text"
                                            id="search"
                                            value={searchTerm}
                                            onChange={(e) => setSearchTerm(e.target.value)}
                                            placeholder="Company name, subdomain, email..."
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
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
                                            <option value="10">10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
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

                    {/* Tenants Table */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Company
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Contact
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Users
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Orders
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Created
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {tenants.data.map((tenant) => (
                                            <tr key={tenant.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {tenant.name}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        @{tenant.subdomain}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm text-gray-900">
                                                        {tenant.contact_email}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {tenant.contact_phone}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm text-gray-900">
                                                        {tenant.users_count} user{tenant.users_count !== 1 ? 's' : ''}
                                                    </div>
                                                    {tenant.users && tenant.users.length > 0 && (
                                                        <div className="text-sm text-gray-500">
                                                            Latest: {tenant.users[tenant.users.length - 1]?.first_name} {tenant.users[tenant.users.length - 1]?.last_name}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {tenant.orders_count} order{tenant.orders_count !== 1 ? 's' : ''}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {new Date(tenant.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <Link
                                                        href={route('super-admin.tenants.show', tenant.id)}
                                                        className="text-indigo-600 hover:text-indigo-900"
                                                    >
                                                        View Details
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination */}
                            {tenants.links && (
                                <div className="mt-6 flex items-center justify-between">
                                    <div className="text-sm text-gray-700">
                                        Showing {tenants.from} to {tenants.to} of {tenants.total} results
                                    </div>
                                    <div className="flex space-x-1">
                                        {tenants.links.map((link, index) => (
                                            <Link
                                                key={index}
                                                href={link.url}
                                                className={`px-3 py-2 text-sm font-medium rounded-md ${
                                                    link.active
                                                        ? 'bg-indigo-600 text-white'
                                                        : link.url
                                                        ? 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                                                        : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                }`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
