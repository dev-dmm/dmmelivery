import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { 
  Bell, 
  ArrowRight, 
  CheckCircle, 
  Settings,
  Mail,
  Phone,
  MessageCircle,
  AlertTriangle,
  CheckCircle2,
  Clock,
  User,
  Shield
} from 'lucide-react';

export default function NotificationsSetup() {
  const setupSteps = [
    {
      number: 1,
      title: 'Επιλογή Τύπων Ειδοποιήσεων',
      description: 'Επιλέξτε ποιες ειδοποιήσεις θέλετε να λαμβάνετε',
      icon: <Bell className="w-5 h-5" />,
      details: [
        'Ενημερώσεις κατάστασης αποστολής',
        'Ειδοποιήσεις καθυστέρησης',
        'Επιβεβαιώσεις παράδοσης',
        'Αναφορές απόδοσης'
      ]
    },
    {
      number: 2,
      title: 'Ρύθμιση Μέσων Επικοινωνίας',
      description: 'Επιλέξτε email, SMS ή dashboard ειδοποιήσεις',
      icon: <Settings className="w-5 h-5" />,
      details: [
        'Email ειδοποιήσεις',
        'SMS ειδοποιήσεις',
        'Dashboard ειδοποιήσεις',
        'Push notifications'
      ]
    },
    {
      number: 3,
      title: 'Διαμόρφωση Συχνότητας',
      description: 'Ρυθμίστε πόσο συχνά θέλετε να λαμβάνετε ειδοποιήσεις',
      icon: <Clock className="w-5 h-5" />,
      details: [
        'Άμεσες ειδοποιήσεις',
        'Συγκεντρωμένες αναφορές',
        'Εβδομαδιαίες αναφορές',
        'Μηνιαίες αναφορές'
      ]
    },
    {
      number: 4,
      title: 'Δοκιμή Ρυθμίσεων',
      description: 'Στείλτε δοκιμαστικές ειδοποιήσεις',
      icon: <CheckCircle2 className="w-5 h-5" />,
      details: [
        'Δοκιμαστικό email',
        'Δοκιμαστικό SMS',
        'Έλεγχος dashboard',
        'Επιβεβαίωση ρυθμίσεων'
      ]
    }
  ];

  const notificationTypes = [
    {
      title: 'Email Ειδοποιήσεις',
      description: 'Αυτόματες ειδοποιήσεις μέσω email',
      icon: <Mail className="w-5 h-5" />,
      features: [
        'Ενημερώσεις κατάστασης αποστολής',
        'Ειδοποιήσεις καθυστέρησης',
        'Επιβεβαιώσεις παράδοσης',
        'Αναφορές απόδοσης'
      ],
      color: 'bg-blue-50 border-blue-200'
    },
    {
      title: 'SMS Ειδοποιήσεις',
      description: 'Γρήγορες ειδοποιήσεις μέσω SMS',
      icon: <Phone className="w-5 h-5" />,
      features: [
        'Κρίσιμες ενημερώσεις',
        'Ειδοποιήσεις παράδοσης',
        'Ενημερώσεις καθυστέρησης',
        'Επιβεβαιώσεις παραλαβής'
      ],
      color: 'bg-green-50 border-green-200'
    },
    {
      title: 'Dashboard Ειδοποιήσεις',
      description: 'Ειδοποιήσεις στο dashboard',
      icon: <MessageCircle className="w-5 h-5" />,
      features: [
        'Ζωντανές ενημερώσεις',
        'Ειδοποιήσεις συστήματος',
        'Προειδοποιήσεις απόδοσης',
        'Ενημερώσεις ασφαλείας'
      ],
      color: 'bg-purple-50 border-purple-200'
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
      title: 'Βοήθεια Ειδοποιήσεων',
      description: 'Μάθετε περισσότερα για τις ειδοποιήσεις',
      href: '/help/notifications',
      icon: <Bell className="w-4 h-4" />
    }
  ];

  return (
    <AuthenticatedLayout>
      <Head title="Ρύθμιση Ειδοποιήσεων - Βοήθεια" />

      <div className="py-6">
        {/* Header */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <div className="flex items-center">
            <div className="bg-orange-100 rounded-full p-3 mr-4">
              <Bell className="w-6 h-6 text-orange-600" />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Ρύθμιση Ειδοποιήσεων</h1>
              <p className="text-gray-600 mt-1">Μάθετε πώς να ρυθμίσετε τις ειδοποιήσεις σας</p>
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

        {/* Setup Steps */}
        <div className="space-y-6 mb-8">
          {setupSteps.map((step, index) => (
            <div key={index} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <div className="flex items-start">
                <div className="flex-shrink-0">
                  <div className="bg-orange-100 rounded-full p-3 mr-4">
                    {step.icon}
                  </div>
                </div>
                <div className="flex-1">
                  <div className="flex items-center mb-3">
                    <span className="bg-orange-600 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold mr-3">
                      {step.number}
                    </span>
                    <h3 className="text-lg font-semibold text-gray-900">{step.title}</h3>
                  </div>
                  <p className="text-gray-600 mb-4">{step.description}</p>
                  <ul className="space-y-2">
                    {step.details.map((detail, detailIndex) => (
                      <li key={detailIndex} className="flex items-center text-sm text-gray-700">
                        <CheckCircle className="w-4 h-4 text-green-500 mr-2 flex-shrink-0" />
                        {detail}
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Notification Types */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Τύποι Ειδοποιήσεων</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {notificationTypes.map((type, index) => (
              <div key={index} className={`p-4 border rounded-lg ${type.color}`}>
                <div className="flex items-center mb-3">
                  <div className="bg-white rounded-lg p-2 mr-3">
                    {type.icon}
                  </div>
                  <h3 className="font-semibold text-gray-900">{type.title}</h3>
                </div>
                <p className="text-sm text-gray-600 mb-3">{type.description}</p>
                <ul className="space-y-1">
                  {type.features.map((feature, featureIndex) => (
                    <li key={featureIndex} className="flex items-center text-sm text-gray-700">
                      <CheckCircle className="w-3 h-3 text-green-500 mr-2 flex-shrink-0" />
                      {feature}
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>

        {/* Security Tips */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Ασφάλεια Ειδοποιήσεων</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="p-4 border border-gray-200 rounded-lg">
              <div className="flex items-center mb-2">
                <Shield className="w-4 h-4 text-blue-600 mr-2" />
                <h3 className="font-medium text-gray-900">Προστασία Δεδομένων</h3>
              </div>
              <p className="text-sm text-gray-600">Οι ειδοποιήσεις σας είναι κρυπτογραφημένες και ασφαλείς</p>
            </div>
            <div className="p-4 border border-gray-200 rounded-lg">
              <div className="flex items-center mb-2">
                <User className="w-4 h-4 text-green-600 mr-2" />
                <h3 className="font-medium text-gray-900">Προσωποποίηση</h3>
              </div>
              <p className="text-sm text-gray-600">Ρυθμίστε ειδοποιήσεις για κάθε χρήστη ξεχωριστά</p>
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
