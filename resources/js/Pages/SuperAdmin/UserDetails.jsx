import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function SuperAdminUserDetails({ auth, user, availableRoles }) {
    const updateUserRole = (userId, newRole) => {
        if (router?.patch) {
            router.patch(
                route('super-admin.users.update-role', userId),
                { role: newRole },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        // Refresh the page to show updated data
                        if (router?.reload) {
                            router.reload();
                        } else {
                            window.location.reload();
                        }
                    }
                }
            );
        } else {
            console.error('Router or router.patch is not available');
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
                    onSuccess: () => {
                        if (router?.reload) {
                            router.reload();
                        } else {
                            window.location.reload();
                        }
                    }
                }
            );
        } else {
            console.error('Router or router.patch is not available');
            window.location.reload();
        }
    };

    const getRoleBadge = (role) => {
        const roleColors = {
            'super_admin': 'bg-red-100 text-red-800',
            'admin': 'bg-blue-100 text-blue-800',
            'user': 'bg-green-100 text-green-800'
        };
        
        return roleColors[role] || 'bg-gray-100 text-gray-800';
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        User Details - {user.first_name} {user.last_name}
                    </h2>
                    <Link
                        href={route('super-admin.users')}
                        className="text-indigo-600 hover:text-indigo-500 text-sm font-medium"
                    >
                        ← Back to Users
                    </Link>
                </div>
            }
        >
            <Head title={`User Details - ${user.first_name} ${user.last_name}`} />

            <div className="py-12">
                <div className="mx-auto">
                    {/* User Information Card */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                        <div className="p-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* Basic Information */}
                                <div>
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Basic Information</h3>
                                    
                                    <div className="space-y-3">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Full Name</label>
                                            <div className="mt-1 text-sm text-gray-900">
                                                {user.first_name} {user.last_name}
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Email</label>
                                            <div className="mt-1 text-sm text-gray-900">{user.email}</div>
                                        </div>
                                        
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Email Status</label>
                                            <div className="mt-1">
                                                {user.email_verified_at ? (
                                                    <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                        Verified ({new Date(user.email_verified_at).toLocaleDateString()})
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Unverified
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Account Status</label>
                                            <div className="mt-1">
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
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Created</label>
                                            <div className="mt-1 text-sm text-gray-900">
                                                {new Date(user.created_at).toLocaleDateString()}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Role & Tenant Information */}
                                <div>
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Role & Tenant</h3>
                                    
                                    <div className="space-y-3">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Role</label>
                                            <div className="mt-1">
                                                {availableRoles ? (
                                                    <select
                                                        value={user.role}
                                                        onChange={(e) => updateUserRole(user.id, e.target.value)}
                                                        className={`text-sm font-semibold rounded px-2 py-1 border ${getRoleBadge(user.role)}`}
                                                    >
                                                        {Object.entries(availableRoles).map(([roleKey, roleName]) => (
                                                            <option key={roleKey} value={roleKey}>
                                                                {roleName}
                                                            </option>
                                                        ))}
                                                    </select>
                                                ) : (
                                                    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getRoleBadge(user.role)}`}>
                                                        {user.role}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        
                                        {user.tenant ? (
                                            <>
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700">Tenant</label>
                                                    <div className="mt-1">
                                                        <Link
                                                            href={route('super-admin.tenants.show', user.tenant.id)}
                                                            className="text-indigo-600 hover:text-indigo-500 font-medium"
                                                        >
                                                            {user.tenant.name}
                                                        </Link>
                                                    </div>
                                                </div>
                                                
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700">Subdomain</label>
                                                    <div className="mt-1 text-sm text-gray-900">
                                                        @{user.tenant.subdomain}
                                                    </div>
                                                </div>
                                                
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700">Tenant Contact</label>
                                                    <div className="mt-1 text-sm text-gray-900">
                                                        {user.tenant.contact_email || 'N/A'}
                                                    </div>
                                                </div>
                                                
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700">Tenant Created</label>
                                                    <div className="mt-1 text-sm text-gray-900">
                                                        {new Date(user.tenant.created_at).toLocaleDateString()}
                                                    </div>
                                                </div>
                                            </>
                                        ) : (
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Tenant</label>
                                                <div className="mt-1 text-sm text-red-600">
                                                    No tenant assigned
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Actions</h3>
                            
                            <div className="flex space-x-3">
                                <button
                                    onClick={() => toggleUserActive(user.id)}
                                    className={`px-4 py-2 text-sm font-medium rounded-md ${
                                        user.is_active
                                            ? 'bg-red-600 hover:bg-red-700 text-white'
                                            : 'bg-green-600 hover:bg-green-700 text-white'
                                    }`}
                                >
                                    {user.is_active ? 'Deactivate User' : 'Activate User'}
                                </button>
                                
                                <Link
                                    href={route('super-admin.users')}
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md"
                                >
                                    Back to Users List
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
