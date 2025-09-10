import React, { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

// ...imports

export default function SuperAdminUsers({ auth, users, tenants, availableRoles, roleStats, filters }) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedTenant, setSelectedTenant] = useState(filters.tenant || '');
    const [selectedRole, setSelectedRole] = useState(filters.role || '');
    const [perPage, setPerPage] = useState(Number(filters.per_page) || 25);
  
    const runSearch = () => {
      const params = new URLSearchParams();
      if (searchTerm) params.append('search', searchTerm);
      if (selectedTenant) params.append('tenant', selectedTenant);
      if (selectedRole) params.append('role', selectedRole);
      if (perPage !== 25) params.append('per_page', String(perPage));
  
      if (router?.get) {
        router.get(route('super-admin.users'), Object.fromEntries(params), {
          preserveScroll: true,
          preserveState: true,
        });
      } else {
        window.location.href = `${route('super-admin.users')}?${params.toString()}`;
      }
    };
  
    const clearFilters = () => {
      setSearchTerm('');
      setSelectedTenant('');
      setSelectedRole('');
      setPerPage(25);
      router?.get
        ? router.get(route('super-admin.users'), {}, { preserveScroll: true })
        : (window.location.href = route('super-admin.users'));
    };
  
    return (
      <AuthenticatedLayout user={auth.user}
        header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Super Admin - All Users</h2>}
      >
        <Head title="Super Admin - Users" />
  
        {/* 👇 Dummy form to satisfy the rogue listener */}
        <form method="GET" onSubmit={(e) => e.preventDefault()}>
  
          <div className="py-12">
            <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
  
              {/* Filters (no real submit) */}
              <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                <div className="p-6 space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    {/* search */}
                    <div>
                      <label htmlFor="search" className="block text-sm font-medium text-gray-700">Search Users</label>
                      <input
                        id="search"
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && runSearch()}
                        placeholder="Name, email, tenant..."
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      />
                    </div>
  
                    {/* tenant */}
                    <div>
                      <label htmlFor="tenant" className="block text-sm font-medium text-gray-700">Tenant</label>
                      <select
                        id="tenant"
                        value={selectedTenant}
                        onChange={(e) => setSelectedTenant(e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      >
                        <option value="">All Tenants</option>
                        {tenants.map(t => (
                          <option key={t.id} value={t.id}>{t.name} ({t.subdomain})</option>
                        ))}
                      </select>
                    </div>
  
                    {/* role */}
                    <div>
                      <label htmlFor="role" className="block text-sm font-medium text-gray-700">Role</label>
                      <select
                        id="role"
                        value={selectedRole}
                        onChange={(e) => setSelectedRole(e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      >
                        <option value="">All Roles</option>
                        {Object.entries(availableRoles).map(([key, name]) => (
                          <option key={key} value={key}>{name}</option>
                        ))}
                      </select>
                    </div>
  
                    {/* per page */}
                    <div>
                      <label htmlFor="per_page" className="block text-sm font-medium text-gray-700">Per Page</label>
                      <select
                        id="per_page"
                        value={perPage}
                        onChange={(e) => setPerPage(Number(e.target.value))}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      >
                        <option value={10}>10</option>
                        <option value={25}>25</option>
                        <option value={50}>50</option>
                        <option value={100}>100</option>
                      </select>
                    </div>
                  </div>
  
                  <div className="flex space-x-3">
                    <button type="button" onClick={runSearch}
                      className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                      Search
                    </button>
                    <button type="button" onClick={clearFilters}
                      className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                      Clear Filters
                    </button>
                  </div>
                </div>
              </div>
  
              {/* Users table + pagination (unchanged except null-url handling) */}
              {/* ... */}
            </div>
          </div>
        </form>
      </AuthenticatedLayout>
    );
  }
  
