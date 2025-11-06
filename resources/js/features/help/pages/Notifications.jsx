import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { 
  MessageCircle, 
  ArrowRight, 
  CheckCircle, 
  Bell,
  Mail,
  Phone,
  Settings,
  AlertTriangle,
  CheckCircle2,
  Clock,
  User
} from 'lucide-react';

export default function Notifications() {
  const notificationTypes = [
    {
      title: 'Ειδοποιήσεις Email',
      description: 'Αυτόματες ειδοποιήσεις μέσω email',
      icon: <Mail className="w-5 h-5" />,
      features: [
        'Ενημερώσεις κατάστασης αποστολής',
        'Ειδοποιήσεις καθυστέρησης',
        'Επιβεβαιώσεις παράδοσης',
        'Αναφορές απόδοσης'
      ]
    },
    {
      title: 'Ειδοποιήσεις SMS',
      description: 'Γρήγορες ειδοποιήσεις μέσω SMS',
      icon: <Phone className="w-5 h-5" />,
      features: [
        'Κρίσιμες ενημερώσεις',
        'Ειδοποιήσεις παράδοσης',
        'Ενημερώσεις καθυστέρησης',
        'Επιβεβαιώσεις παραλαβής'
      ]
    },
    {
      title: 'Ειδοποιήσεις Dashboard',
      description: 'Ειδοποιήσεις στο dashboard',
      icon: <Bell className="w-5 h-5" />,
      features: [
        'Ζωντανές ενημερώσεις',
        'Ειδοποιήσεις συστήματος',
        'Προειδοποιήσεις απόδοσης',
        'Ενημερώσεις ασφαλείας'
      ]
    }
  ];

  const alertTypes = [
    {
      type: 'critical',
      label: 'Κρίσιμες Ειδοποιήσεις',
      description: 'Επείγουσες ειδοποιήσεις που απαιτούν άμεση ενέργεια',
      icon: <AlertTriangle className="w-4 h-4" />,
      color: 'text-red-600',
      examples: [
        'Αποστολή χάθηκε ή καταστράφηκε',
        'Κρίσιμη καθυστέρηση',
        'Πρόβλημα με τον courier',
        'Ασφαλείας θέματα'
      ]
    },
    {
      type: 'warning',
      label: 'Προειδοποιήσεις',
      description: 'Ειδοποιήσεις για πιθανά προβλήματα',
      icon: <Clock className="w-4 h-4" />,
      color: 'text-yellow-600',
      examples: [
        'Καθυστέρηση παράδοσης',
        'Αλλαγή στο χρονοδιάγραμμα',
        'Προειδοποίηση κόστους',
        'Ενημερώσεις courier'
      ]
    },
    {
      type: 'info',
      label: 'Πληροφορίες',
      description: 'Γενικές ενημερώσεις και πληροφορίες',
      icon: <CheckCircle2 className="w-4 h-4" />,
      color: 'text-blue-600',
      examples: [
        'Ενημερώσεις κατάστασης',
        'Επιβεβαιώσεις παράδοσης',
        'Αναφορές απόδοσης',
        'Συστήματα ενημερώσεις'
      ]
    }
  ];

  const quickActions = [
    {
      title: 'Ρυθμίσεις Ειδοποιήσεων',
      description: 'Διαμορφώστε τις ειδοποιήσεις σας',
      href: '/alerts',
      icon: <Settings className="w-4 h-4" />
    },
    {
      title: 'Προβολή Ειδοποιήσεων',
      description: 'Δείτε όλες τις ειδοποιήσεις σας',
      href: '/notifications',
      icon: <Bell className="w-4 h-4" />
    },
    {
      title: 'Διαχείριση Χρηστών',
      description: 'Ρυθμίστε ειδοποιήσεις για χρήστες',
      href: '/users',
      icon: <User className="w-4 h-4" />
    }
  ];

  return (
    <AuthenticatedLayout>
      <Head title="Ειδοποιήσεις - Βοήθεια" />

      <div className="py-6">
          {/* Header */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div className="flex items-center">
              <div className="bg-orange-100 rounded-full p-3 mr-4">
                <MessageCircle className="w-6 h-6 text-orange-600" />
              </div>
              <div>
                <h1 className="text-2xl font-bold text-gray-900">Ειδοποιήσεις & Ενημερώσεις</h1>
                <p className="text-gray-600 mt-1">Ρύθμιση και διαχείριση ειδοποιήσεων</p>
              </div>
            </div>
          </div>

          {/* Quick Actions */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Γρήγορες Ενέργειες</h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {quickActions.map((action, index) => (
                <Link
                  key={index}
                  href={action.href}
                  className="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors group"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center">
                      <div className="bg-orange-100 rounded-lg p-2 mr-3">
                        {action.icon}
                      </div>
                      <div>
                        <h3 className="font-medium text-gray-900">{action.title}</h3>
                        <p className="text-sm text-gray-500 mt-1">{action.description}</p>
                      </div>
                    </div>
                    <ArrowRight className="w-4 h-4 text-gray-400 group-hover:text-orange-600 transition-colors" />
                  </div>
                </Link>
              ))}
            </div>
          </div>

          {/* Notification Types */}
          <div className="space-y-6 mb-8">
            {notificationTypes.map((type, index) => (
              <div key={index} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div className="flex items-start">
                  <div className="flex-shrink-0">
                    <div className="bg-orange-100 rounded-full p-3 mr-4">
                      {type.icon}
                    </div>
                  </div>
                  <div className="flex-1">
                    <h3 className="text-lg font-semibold text-gray-900 mb-2">{type.title}</h3>
                    <p className="text-gray-600 mb-4">{type.description}</p>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                      {type.features.map((feature, featureIndex) => (
                        <div key={featureIndex} className="flex items-center text-sm text-gray-700">
                          <CheckCircle className="w-4 h-4 text-green-500 mr-2 flex-shrink-0" />
                          {feature}
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Alert Types */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Τύποι Ειδοποιήσεων</h2>
            <div className="space-y-4">
              {alertTypes.map((alert, index) => (
                <div key={index} className="p-4 border border-gray-200 rounded-lg">
                  <div className="flex items-center mb-3">
                    <div className={`${alert.color} mr-3`}>
                      {alert.icon}
                    </div>
                    <div>
                      <h3 className="font-semibold text-gray-900">{alert.label}</h3>
                      <p className="text-sm text-gray-600">{alert.description}</p>
                    </div>
                  </div>
                  <div className="ml-8">
                    <h4 className="text-sm font-medium text-gray-700 mb-2">Παραδείγματα:</h4>
                    <ul className="space-y-1">
                      {alert.examples.map((example, exampleIndex) => (
                        <li key={exampleIndex} className="flex items-center text-sm text-gray-600">
                          <CheckCircle className="w-3 h-3 text-gray-400 mr-2 flex-shrink-0" />
                          {example}
                        </li>
                      ))}
                    </ul>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Setup Guide */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Ρύθμιση Ειδοποιήσεων</h2>
            <div className="space-y-4">
              <div className="flex items-start">
                <span className="bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm font-bold mr-3 mt-0.5 flex-shrink-0">1</span>
                <div>
                  <h3 className="font-medium text-gray-900">Επιλέξτε Τύπους Ειδοποιήσεων</h3>
                  <p className="text-sm text-gray-600 mt-1">Επιλέξτε ποιες ειδοποιήσεις θέλετε να λαμβάνετε</p>
                </div>
              </div>
              <div className="flex items-start">
                <span className="bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm font-bold mr-3 mt-0.5 flex-shrink-0">2</span>
                <div>
                  <h3 className="font-medium text-gray-900">Ρυθμίστε Μέσα Επικοινωνίας</h3>
                  <p className="text-sm text-gray-600 mt-1">Επιλέξτε email, SMS ή dashboard ειδοποιήσεις</p>
                </div>
              </div>
              <div className="flex items-start">
                <span className="bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm font-bold mr-3 mt-0.5 flex-shrink-0">3</span>
                <div>
                  <h3 className="font-medium text-gray-900">Διαμορφώστε Συχνότητα</h3>
                  <p className="text-sm text-gray-600 mt-1">Ρυθμίστε πόσο συχνά θέλετε να λαμβάνετε ειδοποιήσεις</p>
                </div>
              </div>
              <div className="flex items-start">
                <span className="bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm font-bold mr-3 mt-0.5 flex-shrink-0">4</span>
                <div>
                  <h3 className="font-medium text-gray-900">Δοκιμάστε τις Ρυθμίσεις</h3>
                  <p className="text-sm text-gray-600 mt-1">Στείλτε δοκιμαστικές ειδοποιήσεις για να ελέγξετε τις ρυθμίσεις</p>
                </div>
              </div>
            </div>
          </div>

          {/* Tips */}
          <div className="bg-orange-50 border border-orange-200 rounded-lg p-6">
            <h3 className="text-lg font-semibold text-orange-800 mb-3">💡 Συμβουλές για Ειδοποιήσεις</h3>
            <ul className="space-y-2 text-orange-700">
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-orange-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Ρυθμίστε ειδοποιήσεις για κρίσιμες καταστάσεις μόνο</span>
              </li>
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-orange-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Χρησιμοποιήστε διαφορετικά κανάλια για διαφορετικούς τύπους ειδοποιήσεων</span>
              </li>
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-orange-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Ενημερώστε τα στοιχεία επικοινωνίας σας τακτικά</span>
              </li>
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-orange-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Χρησιμοποιήστε φίλτρα για να αποφύγετε spam ειδοποιήσεις</span>
              </li>
            </ul>
          </div>
      </div>
    </AuthenticatedLayout>
  );
}
