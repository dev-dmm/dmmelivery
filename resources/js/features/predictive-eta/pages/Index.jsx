import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { 
  Brain, 
  Clock, 
  AlertTriangle, 
  TrendingUp, 
  RefreshCw,
  Eye,
  Zap
} from 'lucide-react';

const PredictiveEtaIndex = ({ predictiveEtas, stats }) => {
  const [isUpdating, setIsUpdating] = useState(false);

  const handleUpdateAll = async () => {
    setIsUpdating(true);
    try {
      await fetch('/predictive-eta/update-all', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
      });
      window.location.reload();
    } catch (error) {
      console.error('Error updating predictive ETAs:', error);
    } finally {
      setIsUpdating(false);
    }
  };

  const getRiskColor = (level) => {
    switch (level) {
      case 'low': return 'bg-green-100 text-green-800';
      case 'medium': return 'bg-yellow-100 text-yellow-800';
      case 'high': return 'bg-orange-100 text-orange-800';
      case 'critical': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getRiskIcon = (level) => {
    switch (level) {
      case 'low': return '✅';
      case 'medium': return '⚠️';
      case 'high': return '🚨';
      case 'critical': return '🔴';
      default: return '❓';
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('el-GR', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  return (
    <AuthenticatedLayout>
      <Head title="Προγνωστικά ETA" />
      
      <div className="py-12">
        <div className="space-y-6">
          {/* Header */}
          <div className="mb-8">
            <div className="flex items-center justify-between">
              <div>
                <h1 className="text-3xl font-bold text-gray-900 flex items-center">
                  <Brain className="w-8 h-8 mr-3 text-blue-600" />
                  Προγνωστικά ETA
                </h1>
                <p className="mt-2 text-gray-600">
                  Προβλέψεις παράδοσης με AI και ανάλυση κινδύνου καθυστέρησης
                </p>
              </div>
              <Button 
                onClick={handleUpdateAll}
                disabled={isUpdating}
                className="flex items-center"
              >
                <RefreshCw className={`w-4 h-4 mr-2 ${isUpdating ? 'animate-spin' : ''}`} />
                {isUpdating ? 'Ενημέρωση...' : 'Ενημέρωση Όλων'}
              </Button>
            </div>
          </div>

          {/* Stats Cards */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Σύνολο Προβλέψεων</CardTitle>
                <Brain className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stats.total_predictions}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Υψηλός Κίνδυνος</CardTitle>
                <AlertTriangle className="h-4 w-4 text-orange-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-orange-600">{stats.high_risk_predictions}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Χαμηλή Εμπιστοσύνη</CardTitle>
                <TrendingUp className="h-4 w-4 text-red-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-red-600">{stats.low_confidence_predictions}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Μέση Εμπιστοσύνη</CardTitle>
                <Zap className="h-4 w-4 text-blue-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-blue-600">
                  {Math.round(stats.avg_confidence * 100)}%
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Predictive ETAs List */}
          <Card>
            <CardHeader>
              <CardTitle>Ανάλυση Προγνωστικών ETA</CardTitle>
              <CardDescription>
                Προβλέψεις παράδοσης με AI και αξιολόγηση κινδύνου
              </CardDescription>
            </CardHeader>
            <CardContent>
              {predictiveEtas.data.length > 0 ? (
                <div className="space-y-4">
                  {predictiveEtas.data.map((eta) => (
                    <div key={eta.id} className="border rounded-lg p-4 hover:bg-gray-50">
                      <div className="flex items-center justify-between">
                        <div className="flex-1">
                          <div className="flex items-center space-x-4">
                            <div>
                              <p className="font-medium text-gray-900">
                                {eta.shipment?.tracking_number || 'Unknown'}
                              </p>
                              <p className="text-sm text-gray-500">
                                {eta.shipment?.customer?.name || 'Άγνωστος Πελάτης'}
                              </p>
                            </div>
                            <div className="flex items-center space-x-2">
                              <Badge className={getRiskColor(eta.delay_risk_level)}>
                                {getRiskIcon(eta.delay_risk_level)} {eta.delay_risk_level}
                              </Badge>
                              <span className="text-sm text-gray-500">
                                {Math.round(eta.confidence_score * 100)}% εμπιστοσύνη
                              </span>
                            </div>
                          </div>
                          
                          <div className="mt-3 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                              <p className="text-xs text-gray-500 mb-1">Αρχικό ETA</p>
                              <p className="text-sm text-gray-900">
                                {formatDate(eta.original_eta)}
                              </p>
                            </div>
                            <div>
                              <p className="text-xs text-gray-500 mb-1">Προβλεπόμενο ETA</p>
                              <p className="text-sm text-gray-900">
                                {formatDate(eta.predicted_eta)}
                              </p>
                            </div>
                            <div>
                              <p className="text-xs text-gray-500 mb-1">Τελευταία Ενημέρωση</p>
                              <p className="text-sm text-gray-900">
                                {formatDate(eta.last_updated_at)}
                              </p>
                            </div>
                          </div>

                          {eta.delay_factors && Object.keys(eta.delay_factors).length > 0 && (
                            <div className="mt-3">
                              <p className="text-xs text-gray-500 mb-2">Παράγοντες Καθυστέρησης:</p>
                              <div className="flex flex-wrap gap-2">
                                {Object.entries(eta.delay_factors).map(([factor, value]) => (
                                  <Badge key={factor} variant="outline">
                                    {factor}: {Math.round(value * 100)}%
                                  </Badge>
                                ))}
                              </div>
                            </div>
                          )}
                        </div>
                        
                        <div className="flex items-center space-x-2">
                          <Link
                            href={`/predictive-eta/${eta.id}`}
                            className="inline-flex items-center px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                          >
                            <Eye className="w-4 h-4 mr-1" />
                            Προβολή Λεπτομερειών
                          </Link>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-12">
                  <Brain className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                  <p className="text-lg font-medium text-gray-900 mb-2">Δεν Υπάρχουν Προγνωστικά ETA</p>
                  <p className="text-gray-500 mb-4">
                    Δημιουργήστε προβλέψεις παράδοσης με AI για τις αποστολές σας
                  </p>
                  <Button onClick={handleUpdateAll} disabled={isUpdating}>
                    <RefreshCw className={`w-4 h-4 mr-2 ${isUpdating ? 'animate-spin' : ''}`} />
                    Δημιουργία Προβλέψεων
                  </Button>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
};

export default PredictiveEtaIndex;
