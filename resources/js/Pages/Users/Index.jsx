import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { 
  Users, 
  ArrowLeft,
  Plus,
  Search,
  Filter,
  Edit,
  Trash2,
  Mail,
  Phone,
  Shield,
  Bell,
  MoreVertical,
  CheckCircle,
  X,
  UserPlus,
  Settings
} from 'lucide-react';

export default function UsersIndex() {
  const [searchQuery, setSearchQuery] = useState('');
  const [filterRole, setFilterRole] = useState('all');
  const [showFilters, setShowFilters] = useState(false);

  // Mock data - in real app this would come from props
  const users = [
    {
      id: 1,
      name: 'Δημήτρης Παπαδόπουλος',
      email: 'dimitris@example.com',
      phone: '+30 210 1234567',
      role: 'admin',
      status: 'active',
      lastLogin: new Date(),
      notifications: {
        email: true,
        sms: false,
        dashboard: true
      }
    },
    {
      id: 2,
      name: 'Μαρία Γεωργίου',
      email: 'maria@example.com',
      phone: '+30 210 2345678',
      role: 'manager',
      status: 'active',
      lastLogin: new Date(Date.now() - 3600000),
      notifications: {
        email: true,
        sms: true,
        dashboard: true
      }
    },
    {
      id: 3,
      name: 'Γιάννης Κωνσταντίνου',
      email: 'giannis@example.com',
      phone: '+30 210 3456789',
      role: 'user',
      status: 'inactive',
      lastLogin: new Date(Date.now() - 86400000),
      notifications: {
        email: false,
        sms: false,
        dashboard: true
      }
    },
    {
      id: 4,
      name: 'Ελένη Αντωνίου',
      email: 'eleni@example.com',
      phone: '+30 210 4567890',
      role: 'user',
      status: 'active',
      lastLogin: new Date(Date.now() - 7200000),
      notifications: {
        email: true,
        sms: false,
        dashboard: false
      }
    }
  ];

  const roleOptions = [
    { value: 'all', label: 'Όλοι οι ρόλοι' },
    { value: 'admin', label: 'Διαχειριστής' },
    { value: 'manager', label: 'Διαχειριστής' },
    { value: 'user', label: 'Χρήστης' }
  ];

  const getRoleColor = (role) => {
    switch (role) {
      case 'admin':
        return 'bg-red-100 text-red-800';
      case 'manager':
        return 'bg-blue-100 text-blue-800';
      case 'user':
        return 'bg-green-100 text-green-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'active':
        return 'bg-green-100 text-green-800';
      case 'inactive':
        return 'bg-gray-100 text-gray-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const filteredUsers = users.filter(user => {
    const matchesSearch = searchQuery === '' || 
      user.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      user.email.toLowerCase().includes(searchQuery.toLowerCase());
    
    const matchesRole = filterRole === 'all' || user.role === filterRole;
    
    return matchesSearch && matchesRole;
  });

  return (
    <AuthenticatedLayout>
      <Head title="Διαχείριση Χρηστών" />

      <div className="py-6">
        {/* Header */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="bg-blue-100 rounded-full p-3 mr-4">
                <Users className="w-6 h-6 text-blue-600" />
              </div>
              <div>
                <h1 className="text-2xl font-bold text-gray-900">Διαχείριση Χρηστών</h1>
                <p className="text-gray-600 mt-1">Ρυθμίστε ειδοποιήσεις για χρήστες</p>
              </div>
            </div>
            <div className="flex items-center space-x-2">
              <button className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center">
                <UserPlus className="w-4 h-4 mr-2" />
                Νέος Χρήστης
              </button>
            </div>
          </div>
        </div>

        {/* Search and Filters */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <div className="flex gap-4">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Αναζήτηση χρηστών..."
                  className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>
            <div className="flex items-center space-x-2">
              <select
                value={filterRole}
                onChange={(e) => setFilterRole(e.target.value)}
                className="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                {roleOptions.map(option => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              <button
                onClick={() => setShowFilters(!showFilters)}
                className="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors flex items-center"
              >
                <Filter className="w-4 h-4 mr-2" />
                Φίλτρα
              </button>
            </div>
          </div>

          {/* Advanced Filters */}
          {showFilters && (
            <div className="mt-4 pt-4 border-t border-gray-200">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Κατάσταση
                  </label>
                  <select className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Όλες οι καταστάσεις</option>
                    <option value="active">Ενεργός</option>
                    <option value="inactive">Ανενεργός</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Ειδοποιήσεις
                  </label>
                  <select className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Όλες οι ειδοποιήσεις</option>
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                    <option value="dashboard">Dashboard</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Τελευταία Σύνδεση
                  </label>
                  <select className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Όλες οι περιόδους</option>
                    <option value="today">Σήμερα</option>
                    <option value="week">Τελευταία εβδομάδα</option>
                    <option value="month">Τελευταίος μήνας</option>
                  </select>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Users List */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200">
          <div className="p-6 border-b border-gray-200">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-semibold text-gray-900">
                Χρήστες ({filteredUsers.length})
              </h2>
              <div className="flex items-center space-x-2">
                <span className="text-sm text-gray-500">
                  {users.filter(u => u.status === 'active').length} ενεργοί
                </span>
              </div>
            </div>
          </div>

          <div className="divide-y divide-gray-200">
            {filteredUsers.map((user) => (
              <div key={user.id} className="p-6 hover:bg-gray-50 transition-colors">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-4">
                    <div className="flex-shrink-0">
                      <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <span className="text-blue-600 font-medium">
                          {user.name.split(' ').map(n => n[0]).join('')}
                        </span>
                      </div>
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center space-x-2 mb-1">
                        <h3 className="text-sm font-medium text-gray-900 truncate">
                          {user.name}
                        </h3>
                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${getRoleColor(user.role)}`}>
                          {user.role}
                        </span>
                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(user.status)}`}>
                          {user.status}
                        </span>
                      </div>
                      <div className="flex items-center space-x-4 text-sm text-gray-500">
                        <div className="flex items-center">
                          <Mail className="w-4 h-4 mr-1" />
                          {user.email}
                        </div>
                        <div className="flex items-center">
                          <Phone className="w-4 h-4 mr-1" />
                          {user.phone}
                        </div>
                        <div className="flex items-center">
                          <Bell className="w-4 h-4 mr-1" />
                          {Object.values(user.notifications).filter(Boolean).length} ειδοποιήσεις
                        </div>
                      </div>
                      <div className="text-xs text-gray-400 mt-1">
                        Τελευταία σύνδεση: {user.lastLogin.toLocaleDateString('el-GR')} {user.lastLogin.toLocaleTimeString('el-GR')}
                      </div>
                    </div>
                  </div>
                  <div className="flex items-center space-x-2">
                    <button className="p-2 text-gray-400 hover:text-blue-600 transition-colors">
                      <Edit className="w-4 h-4" />
                    </button>
                    <button className="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                      <Settings className="w-4 h-4" />
                    </button>
                    <button className="p-2 text-gray-400 hover:text-red-600 transition-colors">
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Quick Actions */}
        <div className="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Γρήγορες Ενέργειες</h3>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Link
              href="/alerts"
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <Bell className="w-5 h-5 text-orange-600 mr-3" />
              <div>
                <h4 className="font-medium text-gray-900">Ρυθμίσεις Ειδοποιήσεων</h4>
                <p className="text-sm text-gray-500">Διαμορφώστε τις ειδοποιήσεις</p>
              </div>
            </Link>
            <Link
              href="/notifications"
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <Mail className="w-5 h-5 text-blue-600 mr-3" />
              <div>
                <h4 className="font-medium text-gray-900">Προβολή Ειδοποιήσεων</h4>
                <p className="text-sm text-gray-500">Δείτε όλες τις ειδοποιήσεις</p>
              </div>
            </Link>
            <Link
              href="/help/notifications"
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <Shield className="w-5 h-5 text-green-600 mr-3" />
              <div>
                <h4 className="font-medium text-gray-900">Βοήθεια</h4>
                <p className="text-sm text-gray-500">Μάθετε περισσότερα</p>
              </div>
            </Link>
          </div>
        </div>

        {/* Tips */}
        <div className="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
          <h3 className="text-lg font-semibold text-blue-800 mb-3">💡 Συμβουλές για Διαχείριση Χρηστών</h3>
          <ul className="space-y-2 text-blue-700">
            <li className="flex items-start">
              <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
              <span>Ρυθμίστε διαφορετικές ειδοποιήσεις για διαφορετικούς ρόλους χρηστών</span>
            </li>
            <li className="flex items-start">
              <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
              <span>Ελέγχετε τακτικά την ενεργότητα των χρηστών</span>
            </li>
            <li className="flex items-start">
              <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
              <span>Χρησιμοποιήστε φίλτρα για να βρείτε γρήγορα τους χρήστες που χρειάζεστε</span>
            </li>
            <li className="flex items-start">
              <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
              <span>Ενημερώστε τα στοιχεία επικοινωνίας των χρηστών τακτικά</span>
            </li>
          </ul>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
