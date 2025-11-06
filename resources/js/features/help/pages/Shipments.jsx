import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { 
  FileText, 
  ArrowRight, 
  CheckCircle, 
  Plus,
  Search,
  Eye,
  Edit,
  Trash2,
  Package,
  Truck,
  Clock,
  AlertTriangle
} from 'lucide-react';

export default function Shipments() {
  const sections = [
    {
      title: 'Δημιουργία Νέας Αποστολής',
      icon: <Plus className="w-5 h-5" />,
      steps: [
        'Συμπληρώστε τα στοιχεία του αποστολέα',
        'Προσθέστε τα στοιχεία του παραλήπτη',
        'Επιλέξτε τον courier και τις υπηρεσίες',
        'Προσθέστε περιγραφή του προϊόντος',
        'Επιβεβαιώστε και αποθηκεύστε την αποστολή'
      ]
    },
    {
      title: 'Παρακολούθηση Αποστολής',
      icon: <Eye className="w-5 h-5" />,
      steps: [
        'Χρησιμοποιήστε τον αριθμό παρακολούθησης',
        'Προβάλετε την τρέχουσα κατάσταση',
        'Δείτε το ιστορικό ενημερώσεων',
        'Παρακολουθήστε την τοποθεσία σε πραγματικό χρόνο'
      ]
    },
    {
      title: 'Διαχείριση Αποστολών',
      icon: <Edit className="w-5 h-5" />,
      steps: [
        'Επεξεργαστείτε τις πληροφορίες αποστολής',
        'Αλλάξτε την κατάσταση της αποστολής',
        'Προσθέστε σχόλια και σημειώσεις',
        'Ακυρώστε ή επαναπρογραμματίστε αποστολές'
      ]
    }
  ];

  const statuses = [
    {
      status: 'pending',
      label: 'Εκκρεμής',
      description: 'Η αποστολή έχει δημιουργηθεί αλλά δεν έχει παραληφθεί ακόμα',
      icon: <Clock className="w-4 h-4" />,
      color: 'text-yellow-600'
    },
    {
      status: 'picked_up',
      label: 'Παραλήφθηκε',
      description: 'Η αποστολή έχει παραληφθεί από τον courier',
      icon: <Package className="w-4 h-4" />,
      color: 'text-blue-600'
    },
    {
      status: 'in_transit',
      label: 'Σε Μεταφορά',
      description: 'Η αποστολή βρίσκεται στο δρόμο προς τον παραλήπτη',
      icon: <Truck className="w-4 h-4" />,
      color: 'text-indigo-600'
    },
    {
      status: 'out_for_delivery',
      label: 'Σε Παράδοση',
      description: 'Η αποστολή είναι στο δρόμο για παράδοση',
      icon: <Truck className="w-4 h-4" />,
      color: 'text-purple-600'
    },
    {
      status: 'delivered',
      label: 'Παραδομένη',
      description: 'Η αποστολή έχει παραδοθεί επιτυχώς',
      icon: <CheckCircle className="w-4 h-4" />,
      color: 'text-green-600'
    },
    {
      status: 'failed',
      label: 'Αποτυχημένη',
      description: 'Η αποστολή δεν μπόρεσε να παραδοθεί',
      icon: <AlertTriangle className="w-4 h-4" />,
      color: 'text-red-600'
    }
  ];

  const quickActions = [
    {
      title: 'Δημιουργία Νέας Αποστολής',
      description: 'Προσθέστε μια νέα αποστολή στο σύστημα',
      href: '/shipments/create',
      icon: <Plus className="w-4 h-4" />
    },
    {
      title: 'Προβολή Όλων των Αποστολών',
      description: 'Δείτε όλες τις αποστολές σας',
      href: '/shipments',
      icon: <Search className="w-4 h-4" />
    },
    {
      title: 'Αναζήτηση Αποστολής',
      description: 'Βρείτε μια συγκεκριμένη αποστολή',
      href: '/shipments/search',
      icon: <Search className="w-4 h-4" />
    }
  ];

  return (
    <AuthenticatedLayout>
      <Head title="Διαχείριση Αποστολών - Βοήθεια" />

      <div className="py-6">
          {/* Header */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div className="flex items-center">
              <div className="bg-blue-100 rounded-full p-3 mr-4">
                <FileText className="w-6 h-6 text-blue-600" />
              </div>
              <div>
                <h1 className="text-2xl font-bold text-gray-900">Διαχείριση Αποστολών</h1>
                <p className="text-gray-600 mt-1">Πώς να δημιουργείτε και να παρακολουθείτε αποστολές</p>
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

          {/* Sections */}
          <div className="space-y-6 mb-8">
            {sections.map((section, index) => (
              <div key={index} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div className="flex items-start">
                  <div className="flex-shrink-0">
                    <div className="bg-blue-100 rounded-full p-3 mr-4">
                      {section.icon}
                    </div>
                  </div>
                  <div className="flex-1">
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">{section.title}</h3>
                    <ol className="space-y-2">
                      {section.steps.map((step, stepIndex) => (
                        <li key={stepIndex} className="flex items-start text-sm text-gray-700">
                          <span className="bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold mr-3 mt-0.5 flex-shrink-0">
                            {stepIndex + 1}
                          </span>
                          {step}
                        </li>
                      ))}
                    </ol>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Status Guide */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Καταστάσεις Αποστολών</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {statuses.map((status, index) => (
                <div key={index} className="p-4 border border-gray-200 rounded-lg">
                  <div className="flex items-center mb-2">
                    <div className={`${status.color} mr-2`}>
                      {status.icon}
                    </div>
                    <h3 className="font-medium text-gray-900">{status.label}</h3>
                  </div>
                  <p className="text-sm text-gray-600">{status.description}</p>
                </div>
              ))}
            </div>
          </div>

          {/* Tips */}
          <div className="bg-green-50 border border-green-200 rounded-lg p-6">
            <h3 className="text-lg font-semibold text-green-800 mb-3">💡 Συμβουλές για Αποστολές</h3>
            <ul className="space-y-2 text-green-700">
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-green-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Ελέγξτε πάντα τα στοιχεία του παραλήπτη πριν την αποστολή</span>
              </li>
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-green-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Χρησιμοποιήστε περιγραφικές ετικέτες για τα προϊόντα</span>
              </li>
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-green-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Ενημερώστε τους πελάτες σας για την κατάσταση των αποστολών</span>
              </li>
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-green-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Κρατήστε αρχείο όλων των αποστολών για αναφορά</span>
              </li>
            </ul>
          </div>
      </div>
    </AuthenticatedLayout>
  );
}
