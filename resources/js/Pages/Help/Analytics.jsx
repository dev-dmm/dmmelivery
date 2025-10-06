import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { 
  Lightbulb, 
  ArrowRight, 
  CheckCircle, 
  BarChart3,
  TrendingUp,
  PieChart,
  LineChart,
  Download,
  Filter,
  Calendar,
  Target
} from 'lucide-react';

export default function Analytics() {
  const analyticsTypes = [
    {
      title: 'Στατιστικά Αποστολών',
      description: 'Συνολικές αποστολές, παραδοτές και επιτυχία',
      icon: <BarChart3 className="w-5 h-5" />,
      metrics: [
        'Συνολικός αριθμός αποστολών',
        'Ποσοστό επιτυχίας',
        'Μέσος χρόνος παράδοσης',
        'Αποστολές ανά ημέρα/εβδομάδα'
      ]
    },
    {
      title: 'Απόδοση Courier',
      description: 'Ανάλυση της απόδοσης των διαφορετικών courier',
      icon: <TrendingUp className="w-5 h-5" />,
      metrics: [
        'Σύγκριση επιτυχίας courier',
        'Μέσος χρόνος παράδοσης ανά courier',
        'Κόστος ανά αποστολή',
        'Αξιολόγηση υπηρεσιών'
      ]
    },
    {
      title: 'Γεωγραφική Ανάλυση',
      description: 'Αποστολές και απόδοση ανά περιοχή',
      icon: <PieChart className="w-5 h-5" />,
      metrics: [
        'Αποστολές ανά πόλη/περιοχή',
        'Μέσος χρόνος παράδοσης ανά περιοχή',
        'Δημοφιλείς προορισμοί',
        'Ανάλυση κόστους ανά περιοχή'
      ]
    },
    {
      title: 'Χρονικές Τάσεις',
      description: 'Ανάλυση τάσεων και προβλέψεις',
      icon: <LineChart className="w-5 h-5" />,
      metrics: [
        'Μηνιαίες/εβδομαδιαίες τάσεις',
        'Προβλέψεις μελλοντικών αποστολών',
        'Ανάλυση εποχικότητας',
        'Σύγκριση με προηγούμενες περιόδους'
      ]
    }
  ];

  const dashboardFeatures = [
    {
      title: 'Κεντρικός Dashboard',
      description: 'Συνολική εικόνα της επιχείρησής σας',
      features: [
        'KPI κάρτες με βασικά μετρικά',
        'Γραφήματα σε πραγματικό χρόνο',
        'Ειδοποιήσεις και προειδοποιήσεις',
        'Πρόσφατες αποστολές και δραστηριότητα'
      ]
    },
    {
      title: 'Προηγμένα Αναλυτικά',
      description: 'Λεπτομερείς αναλύσεις και αναφορές',
      features: [
        'Διαδραστικά γραφήματα και φίλτρα',
        'Εξαγωγή δεδομένων σε Excel/PDF',
        'Προσαρμοσμένες αναφορές',
        'Σύγκριση περιόδων και μετρικών'
      ]
    },
    {
      title: 'Παρακολούθηση σε Πραγματικό Χρόνο',
      description: 'Ζωντανή ενημέρωση δεδομένων',
      features: [
        'Ενημερώσεις σε πραγματικό χρόνο',
        'Ειδοποιήσεις για αλλαγές κατάστασης',
        'Ζωντανά στατιστικά',
        'Αυτόματες αναφορές'
      ]
    }
  ];

  const quickActions = [
    {
      title: 'Προβολή Dashboard',
      description: 'Δείτε το κεντρικό dashboard',
      href: '/dashboard',
      icon: <BarChart3 className="w-4 h-4" />
    },
    {
      title: 'Προηγμένα Αναλυτικά',
      description: 'Εξερευνήστε λεπτομερείς αναλύσεις',
      href: '/analytics/advanced',
      icon: <LineChart className="w-4 h-4" />
    },
    {
      title: 'Εξαγωγή Αναφορών',
      description: 'Εξάγετε δεδομένα και αναφορές',
      href: '/analytics/export',
      icon: <Download className="w-4 h-4" />
    }
  ];

  return (
    <AuthenticatedLayout>
      <Head title="Αναλύσεις - Βοήθεια" />

      <div className="py-6">
          {/* Header */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div className="flex items-center">
              <div className="bg-purple-100 rounded-full p-3 mr-4">
                <Lightbulb className="w-6 h-6 text-purple-600" />
              </div>
              <div>
                <h1 className="text-2xl font-bold text-gray-900">Αναλύσεις & Μετρικά</h1>
                <p className="text-gray-600 mt-1">Κατανόηση των αναλυτικών στοιχείων και μετρικών</p>
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
                      <div className="bg-purple-100 rounded-lg p-2 mr-3">
                        {action.icon}
                      </div>
                      <div>
                        <h3 className="font-medium text-gray-900">{action.title}</h3>
                        <p className="text-sm text-gray-500 mt-1">{action.description}</p>
                      </div>
                    </div>
                    <ArrowRight className="w-4 h-4 text-gray-400 group-hover:text-purple-600 transition-colors" />
                  </div>
                </Link>
              ))}
            </div>
          </div>

          {/* Analytics Types */}
          <div className="space-y-6 mb-8">
            {analyticsTypes.map((type, index) => (
              <div key={index} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div className="flex items-start">
                  <div className="flex-shrink-0">
                    <div className="bg-purple-100 rounded-full p-3 mr-4">
                      {type.icon}
                    </div>
                  </div>
                  <div className="flex-1">
                    <h3 className="text-lg font-semibold text-gray-900 mb-2">{type.title}</h3>
                    <p className="text-gray-600 mb-4">{type.description}</p>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                      {type.metrics.map((metric, metricIndex) => (
                        <div key={metricIndex} className="flex items-center text-sm text-gray-700">
                          <CheckCircle className="w-4 h-4 text-green-500 mr-2 flex-shrink-0" />
                          {metric}
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Dashboard Features */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Δυνατότητες Dashboard</h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              {dashboardFeatures.map((feature, index) => (
                <div key={index} className="p-4 border border-gray-200 rounded-lg">
                  <div className="flex items-center mb-3">
                    <div className="bg-blue-100 rounded-lg p-2 mr-3">
                      {feature.title === 'Κεντρικός Dashboard' && <BarChart3 className="w-5 h-5 text-blue-600" />}
                      {feature.title === 'Προηγμένα Αναλυτικά' && <LineChart className="w-5 h-5 text-blue-600" />}
                      {feature.title === 'Παρακολούθηση σε Πραγματικό Χρόνο' && <Target className="w-5 h-5 text-blue-600" />}
                    </div>
                    <h3 className="font-semibold text-gray-900">{feature.title}</h3>
                  </div>
                  <p className="text-sm text-gray-600 mb-3">{feature.description}</p>
                  <ul className="space-y-1">
                    {feature.features.map((item, itemIndex) => (
                      <li key={itemIndex} className="flex items-center text-sm text-gray-700">
                        <CheckCircle className="w-3 h-3 text-green-500 mr-2 flex-shrink-0" />
                        {item}
                      </li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
          </div>

          {/* Tips */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 className="text-lg font-semibold text-blue-800 mb-3">💡 Συμβουλές για Αναλύσεις</h3>
            <ul className="space-y-2 text-blue-700">
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Ελέγχετε τα αναλυτικά στοιχεία τακτικά για να εντοπίσετε τάσεις</span>
              </li>
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Χρησιμοποιήστε φίλτρα για να εστιάσετε σε συγκεκριμένες περιόδους</span>
              </li>
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Εξάγετε αναφορές για να μοιραστείτε δεδομένα με την ομάδα σας</span>
              </li>
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Ρυθμίστε ειδοποιήσεις για σημαντικές αλλαγές στα μετρικά</span>
              </li>
            </ul>
          </div>
      </div>
    </AuthenticatedLayout>
  );
}
