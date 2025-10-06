import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { 
  FileText, 
  ArrowRight, 
  CheckCircle, 
  Plus,
  User,
  MapPin,
  Package,
  Truck,
  CreditCard,
  CheckCircle2,
  AlertCircle,
  Clock
} from 'lucide-react';

export default function ShipmentsCreateFirst() {
  const steps = [
    {
      number: 1,
      title: 'Στοιχεία Αποστολέα',
      description: 'Συμπληρώστε τα στοιχεία του αποστολέα',
      icon: <User className="w-5 h-5" />,
      details: [
        'Ονοματεπώνυμο ή Επωνυμία Εταιρείας',
        'Διεύθυνση αποστολέα',
        'Τηλέφωνο επικοινωνίας',
        'Email για ενημερώσεις'
      ]
    },
    {
      number: 2,
      title: 'Στοιχεία Παραλήπτη',
      description: 'Προσθέστε τα στοιχεία του παραλήπτη',
      icon: <MapPin className="w-5 h-5" />,
      details: [
        'Ονοματεπώνυμο παραλήπτη',
        'Διεύθυνση παράδοσης',
        'Τηλέφωνο παραλήπτη',
        'Σημειώσεις παράδοσης (προαιρετικά)'
      ]
    },
    {
      number: 3,
      title: 'Προϊόν & Υπηρεσίες',
      description: 'Επιλέξτε courier και υπηρεσίες',
      icon: <Package className="w-5 h-5" />,
      details: [
        'Περιγραφή προϊόντος',
        'Βάρος και διαστάσεις',
        'Επιλογή courier',
        'Τύπος υπηρεσίας (ταχυδρομείο, express, κλπ)'
      ]
    },
    {
      number: 4,
      title: 'Επιβεβαίωση & Πληρωμή',
      description: 'Ελέγξτε και επιβεβαιώστε την αποστολή',
      icon: <CreditCard className="w-5 h-5" />,
      details: [
        'Έλεγχος όλων των στοιχείων',
        'Υπολογισμός κόστους',
        'Επιλογή τρόπου πληρωμής',
        'Αποστολή παραγγελίας'
      ]
    }
  ];

  const tips = [
    {
      title: 'Σωστά Στοιχεία',
      description: 'Βεβαιωθείτε ότι όλα τα στοιχεία είναι σωστά',
      icon: <CheckCircle2 className="w-4 h-4" />,
      color: 'text-green-600'
    },
    {
      title: 'Ασφάλεια',
      description: 'Χρησιμοποιήστε ασφαλή τρόπους πληρωμής',
      icon: <AlertCircle className="w-4 h-4" />,
      color: 'text-yellow-600'
    },
    {
      title: 'Ενημερώσεις',
      description: 'Θα λαμβάνετε ενημερώσεις για την κατάσταση',
      icon: <Clock className="w-4 h-4" />,
      color: 'text-blue-600'
    }
  ];

  const quickActions = [
    {
      title: 'Δημιουργία Αποστολής',
      description: 'Ξεκινήστε τη δημιουργία της πρώτης σας αποστολής',
      href: '/shipments/create',
      icon: <Plus className="w-4 h-4" />
    },
    {
      title: 'Προβολή Αποστολών',
      description: 'Δείτε όλες τις αποστολές σας',
      href: '/shipments',
      icon: <FileText className="w-4 h-4" />
    },
    {
      title: 'Βοήθεια Αποστολών',
      description: 'Μάθετε περισσότερα για τις αποστολές',
      href: '/help/shipments',
      icon: <FileText className="w-4 h-4" />
    }
  ];

  return (
    <AuthenticatedLayout>
      <Head title="Δημιουργία Πρώτης Αποστολής - Βοήθεια" />

      <div className="py-6">
        {/* Header */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <div className="flex items-center">
            <div className="bg-green-100 rounded-full p-3 mr-4">
              <FileText className="w-6 h-6 text-green-600" />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Δημιουργία Πρώτης Αποστολής</h1>
              <p className="text-gray-600 mt-1">Οδηγός βήμα-βήμα για τη δημιουργία της πρώτης σας αποστολής</p>
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
                    <div className="bg-green-100 rounded-lg p-2 mr-3">
                      {action.icon}
                    </div>
                    <div>
                      <h3 className="font-medium text-gray-900">{action.title}</h3>
                      <p className="text-sm text-gray-500 mt-1">{action.description}</p>
                    </div>
                  </div>
                  <ArrowRight className="w-4 h-4 text-gray-400 group-hover:text-green-600 transition-colors" />
                </div>
              </Link>
            ))}
          </div>
        </div>

        {/* Steps */}
        <div className="space-y-6 mb-8">
          {steps.map((step, index) => (
            <div key={index} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <div className="flex items-start">
                <div className="flex-shrink-0">
                  <div className="bg-green-100 rounded-full p-3 mr-4">
                    {step.icon}
                  </div>
                </div>
                <div className="flex-1">
                  <div className="flex items-center mb-3">
                    <span className="bg-green-600 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold mr-3">
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

        {/* Tips */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Σημαντικές Συμβουλές</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {tips.map((tip, index) => (
              <div key={index} className="p-4 border border-gray-200 rounded-lg">
                <div className="flex items-center mb-2">
                  <div className={`${tip.color} mr-2`}>
                    {tip.icon}
                  </div>
                  <h3 className="font-medium text-gray-900">{tip.title}</h3>
                </div>
                <p className="text-sm text-gray-600">{tip.description}</p>
              </div>
            ))}
          </div>
        </div>

        {/* Final Tips */}
        <div className="bg-green-50 border border-green-200 rounded-lg p-6">
          <h3 className="text-lg font-semibold text-green-800 mb-3">💡 Συμβουλές για Πρώτη Αποστολή</h3>
          <ul className="space-y-2 text-green-700">
            <li className="flex items-start">
              <CheckCircle className="w-4 h-4 text-green-600 mr-2 mt-0.5 flex-shrink-0" />
              <span>Ελέγξτε διπλά όλα τα στοιχεία πριν την αποστολή</span>
            </li>
            <li className="flex items-start">
              <CheckCircle className="w-4 h-4 text-green-600 mr-2 mt-0.5 flex-shrink-0" />
              <span>Επιλέξτε τον κατάλληλο courier για την περιοχή σας</span>
            </li>
            <li className="flex items-start">
              <CheckCircle className="w-4 h-4 text-green-600 mr-2 mt-0.5 flex-shrink-0" />
              <span>Χρησιμοποιήστε περιγραφικές ετικέτες για τα προϊόντα</span>
            </li>
            <li className="flex items-start">
              <CheckCircle className="w-4 h-4 text-green-600 mr-2 mt-0.5 flex-shrink-0" />
              <span>Κρατήστε αρχείο του αριθμού παρακολούθησης</span>
            </li>
          </ul>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
