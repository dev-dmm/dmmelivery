import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { 
  Bell, 
  ArrowLeft,
  Mail,
  Phone,
  MessageCircle,
  CheckCircle,
  AlertTriangle,
  Clock,
  Filter,
  Search,
  X,
  Eye,
  Archive
} from 'lucide-react';

export default function Notifications() {
  const [filter, setFilter] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');

  // Mock data - in real app this would come from props
  const notifications = [
    {
      id: 1,
      type: 'shipment_status',
      title: 'Αποστολή Παραδομένη',
      message: 'Η αποστολή #12345 έχει παραδοθεί επιτυχώς στον παραλήπτη.',
      timestamp: new Date(),
      read: false,
      priority: 'high',
      icon: <CheckCircle className="w-5 h-5 text-green-600" />
    },
    {
      id: 2,
      type: 'delay_alert',
      title: 'Καθυστέρηση Αποστολής',
      message: 'Η αποστολή #12346 έχει καθυστερήσει. Εκτιμώμενη παράδοση: 2 ημέρες.',
      timestamp: new Date(Date.now() - 3600000),
      read: false,
      priority: 'medium',
      icon: <Clock className="w-5 h-5 text-yellow-600" />
    },
    {
      id: 3,
      type: 'system_update',
      title: 'Ενημέρωση Συστήματος',
      message: 'Νέα δυνατότητα: Προηγμένα αναλυτικά στοιχεία διαθέσιμα.',
      timestamp: new Date(Date.now() - 7200000),
      read: true,
      priority: 'low',
      icon: <Bell className="w-5 h-5 text-blue-600" />
    },
    {
      id: 4,
      type: 'courier_issue',
      title: 'Πρόβλημα με Courier',
      message: 'Ανιχνεύθηκε πρόβλημα με τον courier ACS. Ελέγξτε τις ρυθμίσεις.',
      timestamp: new Date(Date.now() - 10800000),
      read: true,
      priority: 'high',
      icon: <AlertTriangle className="w-5 h-5 text-red-600" />
    }
  ];

  const filterOptions = [
    { value: 'all', label: 'Όλες', count: notifications.length },
    { value: 'unread', label: 'Αδιάβαστες', count: notifications.filter(n => !n.read).length },
    { value: 'high', label: 'Υψηλή Προτεραιότητα', count: notifications.filter(n => n.priority === 'high').length },
    { value: 'shipment', label: 'Αποστολές', count: notifications.filter(n => n.type.includes('shipment')).length }
  ];

  const getPriorityColor = (priority) => {
    switch (priority) {
      case 'high':
        return 'border-l-red-500 bg-red-50';
      case 'medium':
        return 'border-l-yellow-500 bg-yellow-50';
      case 'low':
        return 'border-l-blue-500 bg-blue-50';
      default:
        return 'border-l-gray-500 bg-gray-50';
    }
  };

  const getPriorityText = (priority) => {
    switch (priority) {
      case 'high':
        return 'Υψηλή Προτεραιότητα';
      case 'medium':
        return 'Μέτρια Προτεραιότητα';
      case 'low':
        return 'Χαμηλή Προτεραιότητα';
      default:
        return 'Κανονική Προτεραιότητα';
    }
  };

  const filteredNotifications = notifications.filter(notification => {
    const matchesFilter = filter === 'all' || 
      (filter === 'unread' && !notification.read) ||
      (filter === 'high' && notification.priority === 'high') ||
      (filter === 'shipment' && notification.type.includes('shipment'));
    
    const matchesSearch = searchQuery === '' || 
      notification.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
      notification.message.toLowerCase().includes(searchQuery.toLowerCase());
    
    return matchesFilter && matchesSearch;
  });

  return (
    <AuthenticatedLayout>
      <Head title="Ειδοποιήσεις" />

      <div className="py-6">
        {/* Header */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="bg-orange-100 rounded-full p-3 mr-4">
                <Bell className="w-6 h-6 text-orange-600" />
              </div>
              <div>
                <h1 className="text-2xl font-bold text-gray-900">Ειδοποιήσεις</h1>
                <p className="text-gray-600 mt-1">Δείτε όλες τις ειδοποιήσεις σας</p>
              </div>
            </div>
            <div className="flex items-center space-x-2">
              <Link
                href="/alerts"
                className="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors"
              >
                Ρυθμίσεις
              </Link>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
          {/* Filters Sidebar */}
          <div className="lg:col-span-1">
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Φίλτρα</h3>
              
              {/* Search */}
              <div className="mb-6">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                  <input
                    type="text"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="Αναζήτηση..."
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                  />
                </div>
              </div>

              {/* Filter Options */}
              <div className="space-y-2">
                {filterOptions.map((option) => (
                  <button
                    key={option.value}
                    onClick={() => setFilter(option.value)}
                    className={`w-full flex items-center justify-between p-3 rounded-lg transition-colors ${
                      filter === option.value
                        ? 'bg-orange-100 text-orange-700'
                        : 'hover:bg-gray-50 text-gray-700'
                    }`}
                  >
                    <span className="font-medium">{option.label}</span>
                    <span className="text-sm bg-gray-200 text-gray-600 px-2 py-1 rounded-full">
                      {option.count}
                    </span>
                  </button>
                ))}
              </div>

              {/* Quick Actions */}
              <div className="mt-6 pt-6 border-t border-gray-200">
                <h4 className="text-sm font-medium text-gray-900 mb-3">Γρήγορες Ενέργειες</h4>
                <div className="space-y-2">
                  <button className="w-full flex items-center p-2 text-gray-700 hover:bg-gray-50 rounded-lg transition-colors">
                    <Archive className="w-4 h-4 mr-2" />
                    Αρχειοθέτηση Όλων
                  </button>
                  <button className="w-full flex items-center p-2 text-gray-700 hover:bg-gray-50 rounded-lg transition-colors">
                    <CheckCircle className="w-4 h-4 mr-2" />
                    Σήμανση Όλων ως Διαβασμένες
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Notifications List */}
          <div className="lg:col-span-3">
            <div className="bg-white rounded-lg shadow-sm border border-gray-200">
              <div className="p-6 border-b border-gray-200">
                <div className="flex items-center justify-between">
                  <h2 className="text-lg font-semibold text-gray-900">
                    Ειδοποιήσεις ({filteredNotifications.length})
                  </h2>
                  <div className="flex items-center space-x-2">
                    <span className="text-sm text-gray-500">
                      {notifications.filter(n => !n.read).length} αδιάβαστες
                    </span>
                  </div>
                </div>
              </div>

              <div className="divide-y divide-gray-200">
                {filteredNotifications.length > 0 ? (
                  filteredNotifications.map((notification) => (
                    <div
                      key={notification.id}
                      className={`p-6 border-l-4 ${getPriorityColor(notification.priority)} ${
                        !notification.read ? 'bg-white' : 'bg-gray-50'
                      }`}
                    >
                      <div className="flex items-start">
                        <div className="flex-shrink-0 mr-4">
                          {notification.icon}
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center justify-between">
                            <h3 className={`text-sm font-medium ${
                              !notification.read ? 'text-gray-900' : 'text-gray-700'
                            }`}>
                              {notification.title}
                            </h3>
                            <div className="flex items-center space-x-2">
                              <span className="text-xs text-gray-500">
                                {notification.timestamp.toLocaleDateString('el-GR')}
                              </span>
                              {!notification.read && (
                                <div className="w-2 h-2 bg-orange-500 rounded-full"></div>
                              )}
                            </div>
                          </div>
                          <p className="mt-1 text-sm text-gray-600">
                            {notification.message}
                          </p>
                          <div className="mt-2 flex items-center space-x-4">
                            <span className="text-xs text-gray-500">
                              {getPriorityText(notification.priority)}
                            </span>
                            <div className="flex items-center space-x-2">
                              <button className="text-xs text-orange-600 hover:text-orange-700">
                                Προβολή
                              </button>
                              <button className="text-xs text-gray-500 hover:text-gray-700">
                                Αρχειοθέτηση
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))
                ) : (
                  <div className="p-12 text-center">
                    <Bell className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <h3 className="text-lg font-medium text-gray-900 mb-2">
                      Δεν βρέθηκαν ειδοποιήσεις
                    </h3>
                    <p className="text-gray-600">
                      Δοκιμάστε να αλλάξετε τα φίλτρα αναζήτησης
                    </p>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Tips */}
        <div className="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
          <h3 className="text-lg font-semibold text-blue-800 mb-3">💡 Συμβουλές για Ειδοποιήσεις</h3>
          <ul className="space-y-2 text-blue-700">
            <li className="flex items-start">
              <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
              <span>Χρησιμοποιήστε φίλτρα για να βρείτε γρήγορα τις ειδοποιήσεις που σας ενδιαφέρουν</span>
            </li>
            <li className="flex items-start">
              <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
              <span>Οι ειδοποιήσεις υψηλής προτεραιότητας απαιτούν άμεση προσοχή</span>
            </li>
            <li className="flex items-start">
              <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
              <span>Ρυθμίστε τις ειδοποιήσεις σας για να λαμβάνετε μόνο αυτές που χρειάζεστε</span>
            </li>
            <li className="flex items-start">
              <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
              <span>Αρχειοθετήστε παλιές ειδοποιήσεις για να κρατήσετε το σύστημα οργανωμένο</span>
            </li>
          </ul>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
