import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { 
  BookOpen, 
  FileText, 
  Lightbulb, 
  MessageCircle, 
  ArrowRight,
  HelpCircle,
  Phone,
  Mail,
  Clock
} from 'lucide-react';

export default function HelpIndex() {
  const quickActions = [
    {
      title: 'Δημιουργία Νέας Αποστολής',
      description: 'Προσθέστε μια νέα αποστολή στο σύστημα',
      href: '/shipments/create',
      icon: <FileText className="w-5 h-5" />
    },
    {
      title: 'Προβολή Όλων των Αποστολών',
      description: 'Δείτε όλες τις αποστολές σας',
      href: '/shipments',
      icon: <FileText className="w-5 h-5" />
    },
    {
      title: 'Ρυθμίσεις Ειδοποιήσεων',
      description: 'Διαμορφώστε τις ειδοποιήσεις σας',
      href: '/alerts',
      icon: <MessageCircle className="w-5 h-5" />
    }
  ];

  const helpTopics = [
    {
      id: 'getting-started',
      title: 'Ξεκινώντας',
      description: 'Βασικές οδηγίες για τη χρήση του συστήματος',
      icon: <BookOpen className="w-5 h-5" />,
      href: '/help/getting-started'
    },
    {
      id: 'shipments',
      title: 'Διαχείριση Αποστολών',
      description: 'Πώς να δημιουργείτε και να παρακολουθείτε αποστολές',
      icon: <FileText className="w-5 h-5" />,
      href: '/help/shipments'
    },
    {
      id: 'analytics',
      title: 'Αναλύσεις',
      description: 'Κατανόηση των αναλυτικών στοιχείων και μετρικών',
      icon: <Lightbulb className="w-5 h-5" />,
      href: '/help/analytics'
    },
    {
      id: 'notifications',
      title: 'Ειδοποιήσεις',
      description: 'Ρύθμιση και διαχείριση ειδοποιήσεων',
      icon: <MessageCircle className="w-5 h-5" />,
      href: '/help/notifications'
    }
  ];

  return (
    <AuthenticatedLayout>
      <Head title="Βοήθεια & Υποστήριξη" />

      <div className="py-6">
          {/* Header */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <div className="bg-blue-100 rounded-full p-3 mr-4">
                  <HelpCircle className="w-6 h-6 text-blue-600" />
                </div>
                <div>
                  <h1 className="text-2xl font-bold text-gray-900">Βοήθεια & Υποστήριξη</h1>
                  <p className="text-gray-600 mt-1">Βρείτε απαντήσεις και βοήθεια</p>
                </div>
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
                      <div className="bg-blue-100 rounded-lg p-2 mr-3">
                        {action.icon}
                      </div>
                      <div>
                        <h3 className="font-medium text-gray-900">{action.title}</h3>
                        <p className="text-sm text-gray-500 mt-1">{action.description}</p>
                      </div>
                    </div>
                    <ArrowRight className="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-colors" />
                  </div>
                </Link>
              ))}
            </div>
          </div>

          {/* Help Topics */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Θέματα Βοήθειας</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {helpTopics.map((topic) => (
                <Link
                  key={topic.id}
                  href={topic.href}
                  className="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors group"
                >
                  <div className="flex items-center">
                    <div className="bg-gray-100 rounded-lg p-2 mr-3">
                      {topic.icon}
                    </div>
                    <div>
                      <h3 className="font-medium text-gray-900">{topic.title}</h3>
                      <p className="text-sm text-gray-500 mt-1">{topic.description}</p>
                    </div>
                  </div>
                </Link>
              ))}
            </div>
          </div>

          {/* Contact Support */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div className="flex items-center mb-4">
              <MessageCircle className="w-5 h-5 text-blue-600 mr-2" />
              <h3 className="text-lg font-semibold text-blue-900">Χρειάζεστε Περισσότερη Βοήθεια;</h3>
            </div>
            <p className="text-blue-700 mb-4">
              Επικοινωνήστε με την ομάδα υποστήριξης για άμεση βοήθεια.
            </p>
            <div className="flex flex-col sm:flex-row gap-3">
              <button className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center justify-center">
                <MessageCircle className="w-4 h-4 mr-2" />
                Ζωντανή Συνομιλία
              </button>
              <button className="px-4 py-2 bg-white text-blue-600 border border-blue-300 rounded-md hover:bg-blue-50 transition-colors flex items-center justify-center">
                <Mail className="w-4 h-4 mr-2" />
                Email Υποστήριξη
              </button>
            </div>
            
            {/* Support Hours */}
            <div className="mt-4 pt-4 border-t border-blue-200">
              <div className="flex items-center text-sm text-blue-600">
                <Clock className="w-4 h-4 mr-2" />
                <span>Ώρες Υποστήριξης: Δευτέρα - Παρασκευή, 9:00 - 18:00</span>
              </div>
            </div>
          </div>
      </div>
    </AuthenticatedLayout>
  );
}
