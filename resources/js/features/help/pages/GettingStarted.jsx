import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { 
  BookOpen, 
  ArrowRight, 
  CheckCircle, 
  Play,
  FileText,
  BarChart3,
  Bell,
  Settings
} from 'lucide-react';

export default function GettingStarted() {
  const steps = [
    {
      number: 1,
      title: 'Πρώτα Βήματα',
      description: 'Εξοικειωθείτε με το σύστημα',
      icon: <Play className="w-5 h-5" />,
      details: [
        'Δημιουργήστε τον πρώτο σας λογαριασμό',
        'Ρυθμίστε τις βασικές πληροφορίες της επιχείρησής σας',
        'Εξερευνήστε το κεντρικό dashboard',
        'Διαβάστε τις οδηγίες ασφαλείας'
      ]
    },
    {
      number: 2,
      title: 'Διαχείριση Αποστολών',
      description: 'Μάθετε πώς να δημιουργείτε και να παρακολουθείτε αποστολές',
      icon: <FileText className="w-5 h-5" />,
      details: [
        'Δημιουργία νέας αποστολής',
        'Προσθήκη πληροφοριών πελάτη',
        'Επιλογή courier και υπηρεσιών',
        'Παρακολούθηση κατάστασης αποστολής'
      ]
    },
    {
      number: 3,
      title: 'Αναλύσεις & Αναφορές',
      description: 'Κατανοήστε τα αναλυτικά στοιχεία και τις τάσεις',
      icon: <BarChart3 className="w-5 h-5" />,
      details: [
        'Προβολή στατιστικών αποστολών',
        'Ανάλυση απόδοσης courier',
        'Δημιουργία αναφορών',
        'Εξαγωγή δεδομένων'
      ]
    },
    {
      number: 4,
      title: 'Ειδοποιήσεις & Ρυθμίσεις',
      description: 'Ρυθμίστε τις ειδοποιήσεις και τις προτιμήσεις σας',
      icon: <Bell className="w-5 h-5" />,
      details: [
        'Ρύθμιση ειδοποιήσεων email',
        'Διαμόρφωση ειδοποιήσεων SMS',
        'Προσαρμογή dashboard',
        'Διαχείριση χρηστών'
      ]
    }
  ];

  const quickLinks = [
    {
      title: 'Δημιουργία Πρώτης Αποστολής',
      description: 'Οδηγός βήμα-βήμα για τη δημιουργία της πρώτης σας αποστολής',
      href: '/help/shipments/create-first',
      icon: <FileText className="w-4 h-4" />
    },
    {
      title: 'Ρύθμιση Ειδοποιήσεων',
      description: 'Μάθετε πώς να ρυθμίσετε τις ειδοποιήσεις σας',
      href: '/help/notifications/setup',
      icon: <Bell className="w-4 h-4" />
    },
    {
      title: 'Κατανόηση Dashboard',
      description: 'Εξερευνήστε όλες τις δυνατότητες του dashboard',
      href: '/help/dashboard/overview',
      icon: <BarChart3 className="w-4 h-4" />
    }
  ];

  return (
    <AuthenticatedLayout>
      <Head title="Ξεκινώντας - Βοήθεια" />

      <div className="py-6">
          {/* Header */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div className="flex items-center">
              <div className="bg-green-100 rounded-full p-3 mr-4">
                <BookOpen className="w-6 h-6 text-green-600" />
              </div>
              <div>
                <h1 className="text-2xl font-bold text-gray-900">Ξεκινώντας</h1>
                <p className="text-gray-600 mt-1">Βασικές οδηγίες για τη χρήση του συστήματος</p>
              </div>
            </div>
          </div>

          {/* Steps */}
          <div className="space-y-6 mb-8">
            {steps.map((step, index) => (
              <div key={index} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div className="flex items-start">
                  <div className="flex-shrink-0">
                    <div className="bg-blue-100 rounded-full p-3 mr-4">
                      {step.icon}
                    </div>
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center mb-3">
                      <span className="bg-blue-600 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold mr-3">
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

          {/* Quick Links */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Σχετικοί Οδηγοί</h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {quickLinks.map((link, index) => (
                <Link
                  key={index}
                  href={link.href}
                  className="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors group"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center">
                      <div className="bg-gray-100 rounded-lg p-2 mr-3">
                        {link.icon}
                      </div>
                      <div>
                        <h3 className="font-medium text-gray-900">{link.title}</h3>
                        <p className="text-sm text-gray-500 mt-1">{link.description}</p>
                      </div>
                    </div>
                    <ArrowRight className="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-colors" />
                  </div>
                </Link>
              ))}
            </div>
          </div>

          {/* Tips */}
          <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
            <h3 className="text-lg font-semibold text-yellow-800 mb-3">💡 Συμβουλές για Αρχάριους</h3>
            <ul className="space-y-2 text-yellow-700">
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-yellow-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Ξεκινήστε με μικρό αριθμό αποστολών για να εξοικειωθείτε με το σύστημα</span>
              </li>
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-yellow-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Ρυθμίστε τις ειδοποιήσεις σας από την αρχή για να μην χάσετε σημαντικές ενημερώσεις</span>
              </li>
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-yellow-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Εξερευνήστε τα αναλυτικά στοιχεία για να κατανοήσετε την απόδοση των αποστολών σας</span>
              </li>
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-yellow-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Χρησιμοποιήστε την υποστήριξη πελατών όταν χρειάζεστε βοήθεια</span>
              </li>
            </ul>
          </div>
      </div>
    </AuthenticatedLayout>
  );
}
