import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function SuperAdminUsers({
  auth,
  users,
  tenants,
  availableRoles,
  roleStats,
  filters
}) {
  const [searchTerm, setSearchTerm] = useState(filters?.search || '');
  const [selectedTenant, setSelectedTenant] = useState(filters?.tenant || '');
  const [selectedRole, setSelectedRole] = useState(filters?.role || '');
  const [perPage, setPerPage] = useState(Number(filters?.per_page) || 25);

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
    if (router?.get) {
      router.get(route('super-admin.users'), {}, { preserveScroll: true });
    } else {
      window.location.href = route('super-admin.users');
    }
  };

  const updateUserRole = (userId, newRole) => {
    if (router?.patch) {
      router.patch(
        route('super-admin.users.update-role', userId),
        { role: newRole },
        {
          preserveScroll: true,
          onSuccess: () => (router?.reload?.() ?? window.location.reload()),
        }
      );
    } else {
      window.location.reload();
    }
  };

  const toggleUserActive = (userId) => {
    if (router?.patch) {
      router.patch(
        route('super-admin.users.toggle-active', userId),
        {},
        {
          preserveScroll: true,
          onSuccess: () => (router?.reload?.() ?? window.location.reload()),
        }
      );
    } else {
      window.location.reload();
    }
  };

  const getRoleBadge = (role) =>
    ({
      super_admin: 'bg-red-100 text-red-800',
      admin: 'bg-blue-100 text-blue-800',
      user: 'bg-green-100 text-green-800',
    }[role] || 'bg-gray-100 text-gray-800');

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Super Admin - All Users</h2>}
    >
      <Head title="Super Admin - Users" />

      {/* Dummy form prevents rogue listeners from reading .method on null.
          It never actually submits. */}
      <form method="GET" onSubmit={(e) => e.preventDefault()}>
        <div className="py-12">
          <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {/* Role stats */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
              {Object.entries(availableRoles).map(([roleKey, roleName]) => {
                const count = roleStats?.[roleKey]?.count || 0;
                return (
                  <div key={roleKey} className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div className="text-sm font-medium text-gray-500">{roleName}</div>
                    <div className="mt-2 text-3xl font-bold text-gray-900">{count.toLocaleString()}</div>
                  </div>
                );
              })}
            </div>

            {/* Filters (no real form submit) */}
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
              <div className="p-6 space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                  <div>
                    <label htmlFor="search" className="block text-sm font-medium text-gray-700">
                      Search Users
                    </label>
                    <input
                      id="search"
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      onKeyDown={(e) => e.key === 'Enter' && runSearch()}
                      placeholder="Name, email, tenant..."
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
                      {tenants.map((t) => (
                        <option key={t.id} value={t.id}>
                          {t.name} ({t.subdomain})
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label htmlFor="role" className="block text-sm font-medium text-gray-700">
                      Role
                    </label>
                    <select
                      id="role"
                      value={selectedRole}
                      onChange={(e) => setSelectedRole(e.target.value)}
                      className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                      <option value="">All Roles</option>
                      {Object.entries(availableRoles).map(([roleKey, roleName]) => (
                        <option key={roleKey} value={roleKey}>
                          {roleName}
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

            {/* Users Table */}
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div className="p-6">
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          User
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Tenant/Company
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Role
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Status
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
                      {users?.data?.map((user) => (
                        <tr key={user.id} className="hover:bg-gray-50">
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm font-medium text-gray-900">
                              {user.first_name} {user.last_name}
                            </div>
                            <div className="text-sm text-gray-500">{user.email}</div>
                            {user.email_verified_at ? (
                              <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                Verified
                              </span>
                            ) : (
                              <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                Unverified
                              </span>
                            )}
                          </td>

                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm font-medium text-gray-900">
                              {user.tenant?.name || 'No Tenant'}
                            </div>
                            <div className="text-sm text-gray-500">@{user.tenant?.subdomain || 'N/A'}</div>
                          </td>

                          <td className="px-6 py-4 whitespace-nowrap">
                            <select
                              value={user.role}
                              onChange={(e) => updateUserRole(user.id, e.target.value)}
                              className={`text-xs font-semibold rounded-full px-2 py-1 border-0 ${getRoleBadge(user.role)}`}
                            >
                              {Object.entries(availableRoles).map(([roleKey, roleName]) => (
                                <option key={roleKey} value={roleKey}>
                                  {roleName}
                                </option>
                              ))}
                            </select>
                          </td>

                          <td className="px-6 py-4 whitespace-nowrap">
                            <button
                              onClick={() => toggleUserActive(user.id)}
                              className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                user.is_active
                                  ? 'bg-green-100 text-green-800 hover:bg-green-200'
                                  : 'bg-red-100 text-red-800 hover:bg-red-200'
                              }`}
                            >
                              {user.is_active ? 'Active' : 'Inactive'}
                            </button>
                          </td>

                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {new Date(user.created_at).toLocaleDateString()}
                          </td>

                          <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <Link
                              href={route('super-admin.users.show', user.id)}
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
                {users?.links && (
                  <div className="mt-6 flex items-center justify-between">
                    <div className="text-sm text-gray-700">
                      Showing {users.from} to {users.to} of {users.total} results
                    </div>
                    <div className="flex space-x-1">
                      {users.links.map((link, i) =>
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
      </form>
    </AuthenticatedLayout>
  );
}
