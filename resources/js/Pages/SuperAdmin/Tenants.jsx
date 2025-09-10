import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function SuperAdminTenants({ auth, tenants, filters }) {
  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [perPage, setPerPage] = useState(Number(filters.per_page) || 25);

  const runSearch = () => {
    const params = new URLSearchParams();
    if (searchTerm) params.append('search', searchTerm);
    if (perPage !== 25) params.append('per_page', String(perPage));
    if (router?.get) {
      router.get(route('super-admin.tenants'), Object.fromEntries(params), {
        preserveScroll: true,
        preserveState: true,
      });
    } else {
      window.location.href = `${route('super-admin.tenants')}?${params.toString()}`;
    }
  };

  const clearFilters = () => {
    setSearchTerm('');
    setPerPage(25);
    if (router?.get) {
      router.get(route('super-admin.tenants'), {}, { preserveScroll: true });
    } else {
      window.location.href = route('super-admin.tenants');
    }
  };

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Super Admin - All Tenants</h2>}
    >
      <Head title="Super Admin - Tenants" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">

          {/* Search and Filters (no <form>) */}
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
            <div className="p-6">
              <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label htmlFor="search" className="block text-sm font-medium text-gray-700">Search Tenants</label>
                    <input
                      id="search"
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      onKeyDown={(e) => e.key === 'Enter' && runSearch()}
                      placeholder="Company name, subdomain, email..."
                      className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                  </div>

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
                  <button
                    type="button"
                    onClick={runSearch}
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
              </div>
            </div>
          </div>

          {/* Tenants Table */}
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6">
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  {/* ... your table head and body unchanged ... */}
                </table>
              </div>

              {/* Pagination */}
              {tenants?.links && (
                <div className="mt-6 flex items-center justify-between">
                  <div className="text-sm text-gray-700">
                    Showing {tenants.from} to {tenants.to} of {tenants.total} results
                  </div>
                  <div className="flex space-x-1">
                    {tenants.links.map((link, i) =>
                      link.url ? (
                        <Link
                          key={i}
                          href={link.url}
                          className={`px-3 py-2 text-sm font-medium rounded-md ${
                            link.active
                              ? 'bg-indigo-600 text-white'
                              : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                          }`}
                          dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                      ) : (
                        <span
                          key={i}
                          className="px-3 py-2 text-sm font-medium rounded-md bg-gray-100 text-gray-400 cursor-not-allowed"
                          dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                      )
                    )}
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
