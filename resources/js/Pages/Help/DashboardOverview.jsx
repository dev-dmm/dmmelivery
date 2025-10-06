import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { 
  BarChart3, 
  ArrowRight, 
  CheckCircle, 
  TrendingUp,
  PieChart,
  LineChart,
  Activity,
  Target,
  Clock,
  Users,
  Package,
  Truck,
  AlertCircle,
  CheckCircle2,
  Eye
} from 'lucide-react';

export default function DashboardOverview() {
  const dashboardSections = [
    {
      title: 'Κεντρικός Dashboard',
      description: 'Συνολική εικόνα της επιχείρησής σας',
      icon: <BarChart3 className="w-5 h-5" />,
      features: [
        'KPI κάρτες με βασικά μετρικά',
        'Γραφήματα σε πραγματικό χρόνο',
        'Ειδοποιήσεις και προειδοποιήσεις',
        'Πρόσφατες αποστολές και δραστηριότητα'
      ]
    },
    {
      title: 'Αναλυτικά Στοιχεία',
      description: 'Λεπτομερείς αναλύσεις και αναφορές',
      icon: <TrendingUp className="w-5 h-5" />,
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
      icon: <Activity className="w-5 h-5" />,
      features: [
        'Ενημερώσεις σε πραγματικό χρόνο',
        'Ειδοποιήσεις για αλλαγές κατάστασης',
        'Ζωντανά στατιστικά',
        'Αυτόματες αναφορές'
      ]
    }
  ];

  const kpiCards = [
    {
      title: 'Συνολικές Αποστολές',
      description: 'Αριθμός όλων των αποστολών',
      icon: <Package className="w-5 h-5" />,
      color: 'bg-blue-50 border-blue-200'
    },
    {
      title: 'Επιτυχημένες Παραδόσεις',
      description: 'Ποσοστό επιτυχίας αποστολών',
      icon: <CheckCircle2 className="w-5 h-5" />,
      color: 'bg-green-50 border-green-200'
    },
    {
      title: 'Μέσος Χρόνος Παράδοσης',
      description: 'Μέσος όρος ημερών παράδοσης',
      icon: <Clock className="w-5 h-5" />,
      color: 'bg-yellow-50 border-yellow-200'
    },
    {
      title: 'Ενεργοί Courier',
      description: 'Αριθμός ενεργών courier',
      icon: <Truck className="w-5 h-5" />,
      color: 'bg-purple-50 border-purple-200'
    }
  ];

  const chartTypes = [
    {
      title: 'Γραφήματα Αποστολών',
      description: 'Αποστολές ανά ημέρα/εβδομάδα/μήνα',
      icon: <LineChart className="w-5 h-5" />,
      features: [
        'Μηνιαίες τάσεις αποστολών',
        'Εβδομαδιαία σύγκριση',
        'Εποχικότητα αποστολών',
        'Προβλέψεις μελλοντικών αποστολών'
      ]
    },
    {
      title: 'Απόδοση Courier',
      description: 'Σύγκριση απόδοσης διαφορετικών courier',
      icon: <PieChart className="w-5 h-5" />,
      features: [
        'Σύγκριση επιτυχίας courier',
        'Μέσος χρόνος παράδοσης ανά courier',
        'Κόστος ανά αποστολή',
        'Αξιολόγηση υπηρεσιών'
      ]
    },
    {
      title: 'Γεωγραφική Ανάλυση',
      description: 'Αποστολές και απόδοση ανά περιοχή',
      icon: <Target className="w-5 h-5" />,
      features: [
        'Αποστολές ανά πόλη/περιοχή',
        'Μέσος χρόνος παράδοσης ανά περιοχή',
        'Δημοφιλείς προορισμοί',
        'Ανάλυση κόστους ανά περιοχή'
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
      title: 'Αναλυτικά Στοιχεία',
      description: 'Δείτε όλα τα αναλυτικά στοιχεία',
      href: '/analytics',
      icon: <TrendingUp className="w-4 h-4" />
    }
  ];

  return (
    <AuthenticatedLayout>
      <Head title="Κατανόηση Dashboard - Βοήθεια" />

      <div className="py-6">
        {/* Header */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <div className="flex items-center">
            <div className="bg-blue-100 rounded-full p-3 mr-4">
              <BarChart3 className="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Κατανόηση Dashboard</h1>
              <p className="text-gray-600 mt-1">Εξερευνήστε όλες τις δυνατότητες του dashboard</p>
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

        {/* Dashboard Sections */}
        <div className="space-y-6 mb-8">
          {dashboardSections.map((section, index) => (
            <div key={index} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <div className="flex items-start">
                <div className="flex-shrink-0">
                  <div className="bg-blue-100 rounded-full p-3 mr-4">
                    {section.icon}
                  </div>
                </div>
                <div className="flex-1">
                  <h3 className="text-lg font-semibold text-gray-900 mb-2">{section.title}</h3>
                  <p className="text-gray-600 mb-4">{section.description}</p>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                    {section.features.map((feature, featureIndex) => (
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

        {/* KPI Cards */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">KPI Κάρτες</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {kpiCards.map((card, index) => (
              <div key={index} className={`p-4 border rounded-lg ${card.color}`}>
                <div className="flex items-center mb-2">
                  <div className="bg-white rounded-lg p-2 mr-3">
                    {card.icon}
                  </div>
                  <h3 className="font-semibold text-gray-900">{card.title}</h3>
                </div>
                <p className="text-sm text-gray-600">{card.description}</p>
              </div>
            ))}
          </div>
        </div>

        {/* Chart Types */}
        <div className="space-y-6 mb-8">
          {chartTypes.map((chart, index) => (
            <div key={index} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <div className="flex items-start">
                <div className="flex-shrink-0">
                  <div className="bg-blue-100 rounded-full p-3 mr-4">
                    {chart.icon}
                  </div>
                </div>
                <div className="flex-1">
                  <h3 className="text-lg font-semibold text-gray-900 mb-2">{chart.title}</h3>
                  <p className="text-gray-600 mb-4">{chart.description}</p>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                    {chart.features.map((feature, featureIndex) => (
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

        {/* Navigation Tips */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Πλοήγηση Dashboard</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="p-4 border border-gray-200 rounded-lg">
              <div className="flex items-center mb-2">
                <Eye className="w-4 h-4 text-blue-600 mr-2" />
                <h3 className="font-medium text-gray-900">Προβολή Στοιχείων</h3>
              </div>
              <p className="text-sm text-gray-600">Χρησιμοποιήστε τα φίλτρα για να εστιάσετε σε συγκεκριμένες περιόδους</p>
            </div>
            <div className="p-4 border border-gray-200 rounded-lg">
              <div className="flex items-center mb-2">
                <AlertCircle className="w-4 h-4 text-yellow-600 mr-2" />
                <h3 className="font-medium text-gray-900">Ειδοποιήσεις</h3>
              </div>
              <p className="text-sm text-gray-600">Παρακολουθήστε τις ειδοποιήσεις για σημαντικές αλλαγές</p>
            </div>
          </div>
        </div>

        {/* Tips */}
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
          <h3 className="text-lg font-semibold text-blue-800 mb-3">💡 Συμβουλές για Dashboard</h3>
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
