import React, { useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Separator } from '@/Components/ui/separator';
import { 
  ArrowLeft, 
  Clock, 
  MapPin, 
  TrendingUp, 
  AlertTriangle, 
  CheckCircle, 
  Cloud, 
  Car, 
  Route,
  Brain,
  RefreshCw,
  Calendar,
  Target,
  BarChart3
} from 'lucide-react';

export default function PredictiveEtaShow({ predictiveEta }) {
  const { auth } = usePage().props;
  const [isRefreshing, setIsRefreshing] = useState(false);

  const getRiskColor = (level) => {
    switch (level) {
      case 'low': return 'bg-green-100 text-green-800 border-green-200';
      case 'medium': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
      case 'high': return 'bg-orange-100 text-orange-800 border-orange-200';
      case 'critical': return 'bg-red-100 text-red-800 border-red-200';
      default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  const getRiskIcon = (level) => {
    switch (level) {
      case 'low': return <CheckCircle className="w-4 h-4" />;
      case 'medium': return <AlertTriangle className="w-4 h-4" />;
      case 'high': return <AlertTriangle className="w-4 h-4" />;
      case 'critical': return <AlertTriangle className="w-4 h-4" />;
      default: return <Clock className="w-4 h-4" />;
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'Δεν είναι διαθέσιμο';
    return new Date(dateString).toLocaleString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const formatConfidence = (score) => {
    return `${Math.round(score * 100)}%`;
  };

  const formatImpact = (score) => {
    return `${Math.round(score * 100)}%`;
  };

  const handleRefresh = async () => {
    setIsRefreshing(true);
    // Add refresh logic here if needed
    setTimeout(() => setIsRefreshing(false), 1000);
  };

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <Link
              href="/predictive-eta"
              className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700"
            >
              <ArrowLeft className="w-4 h-4 mr-2" />
              Επιστροφή στις Προβλέψεις
            </Link>
            <div>
              <h2 className="text-xl font-semibold text-gray-900">
                Λεπτομέρειες Προγνωστικού ETA
              </h2>
              <p className="text-sm text-gray-500">
                Αποστολή: {predictiveEta.shipment?.tracking_number}
              </p>
            </div>
          </div>
          <Button onClick={handleRefresh} disabled={isRefreshing} variant="outline">
            <RefreshCw className={`w-4 h-4 mr-2 ${isRefreshing ? 'animate-spin' : ''}`} />
            Ανανέωση
          </Button>
        </div>
      }
    >
      <Head title="Λεπτομέρειες Προγνωστικού ETA" />

      <div className="py-6">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            {/* Main Prediction Card */}
            <div className="lg:col-span-2">
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center space-x-2">
                    <Brain className="w-5 h-5 text-blue-600" />
                    <span>Ανάλυση Προβλέψεων AI</span>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-6">
                  
                  {/* Prediction Overview */}
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <div className="flex items-center space-x-2">
                        <Calendar className="w-4 h-4 text-gray-500" />
                        <span className="text-sm font-medium text-gray-700">Προβλεπόμενο ETA</span>
                      </div>
                      <p className="text-lg font-semibold text-gray-900">
                        {formatDate(predictiveEta.predicted_eta)}
                      </p>
                    </div>
                    
                    <div className="space-y-2">
                      <div className="flex items-center space-x-2">
                        <Target className="w-4 h-4 text-gray-500" />
                        <span className="text-sm font-medium text-gray-700">Εμπιστοσύνη</span>
                      </div>
                      <p className="text-lg font-semibold text-gray-900">
                        {formatConfidence(predictiveEta.confidence_score)}
                      </p>
                    </div>
                  </div>

                  <Separator />

                  {/* Risk Assessment */}
                  <div className="space-y-4">
                    <h3 className="text-lg font-medium text-gray-900">Αξιολόγηση Κινδύνου</h3>
                    <div className="flex items-center space-x-3">
                      <Badge className={`${getRiskColor(predictiveEta.delay_risk_level)} border`}>
                        <div className="flex items-center space-x-1">
                          {getRiskIcon(predictiveEta.delay_risk_level)}
                          <span className="capitalize">{predictiveEta.delay_risk_level} Κίνδυνος</span>
                        </div>
                      </Badge>
                      <span className="text-sm text-gray-600">
                        {predictiveEta.has_significant_delay ? 'Αναμένεται σημαντική καθυστέρηση' : 'Δεν αναμένονται σημαντικές καθυστερήσεις'}
                      </span>
                    </div>
                  </div>

                  <Separator />

                  {/* Delay Factors */}
                  <div className="space-y-4">
                    <h3 className="text-lg font-medium text-gray-900">Ανάλυση Επίδρασης Καθυστέρησης</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div className="space-y-3">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center space-x-2">
                            <Cloud className="w-4 h-4 text-blue-500" />
                            <span className="text-sm font-medium">Επίδραση Καιρού</span>
                          </div>
                          <span className="text-sm font-semibold">{formatImpact(predictiveEta.weather_impact)}</span>
                        </div>
                        <div className="w-full bg-gray-200 rounded-full h-2">
                          <div 
                            className="bg-blue-500 h-2 rounded-full" 
                            style={{ width: `${predictiveEta.weather_impact * 100}%` }}
                          ></div>
                        </div>
                      </div>

                      <div className="space-y-3">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center space-x-2">
                            <Car className="w-4 h-4 text-orange-500" />
                            <span className="text-sm font-medium">Επίδραση Κυκλοφορίας</span>
                          </div>
                          <span className="text-sm font-semibold">{formatImpact(predictiveEta.traffic_impact)}</span>
                        </div>
                        <div className="w-full bg-gray-200 rounded-full h-2">
                          <div 
                            className="bg-orange-500 h-2 rounded-full" 
                            style={{ width: `${predictiveEta.traffic_impact * 100}%` }}
                          ></div>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Route Suggestions */}
                  {predictiveEta.route_optimization_suggestions && predictiveEta.route_optimization_suggestions.length > 0 && (
                    <>
                      <Separator />
                      <div className="space-y-4">
                        <h3 className="text-lg font-medium text-gray-900">Βελτιστοποίηση Διαδρομής</h3>
                        <div className="space-y-2">
                          {predictiveEta.route_optimization_suggestions.map((suggestion, index) => (
                            <div key={index} className="flex items-start space-x-2 p-3 bg-blue-50 rounded-lg">
                              <Route className="w-4 h-4 text-blue-600 mt-0.5" />
                              <span className="text-sm text-blue-800">{suggestion}</span>
                            </div>
                          ))}
                        </div>
                      </div>
                    </>
                  )}

                  {/* Delay Explanation */}
                  <Separator />
                  <div className="space-y-2">
                    <h3 className="text-lg font-medium text-gray-900">Σύνοψη Ανάλυσης</h3>
                    <p className="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                      {predictiveEta.delay_explanation}
                    </p>
                  </div>
                </CardContent>
              </Card>
            </div>

            {/* Sidebar */}
            <div className="space-y-6">
              
              {/* Shipment Info */}
              <Card>
                <CardHeader>
                  <CardTitle className="text-lg">Πληροφορίες Αποστολής</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-2">
                    <div className="flex items-center space-x-2">
                      <MapPin className="w-4 h-4 text-gray-500" />
                      <span className="text-sm font-medium text-gray-700">Αριθμός Παρακολούθησης</span>
                    </div>
                    <p className="font-mono text-sm bg-gray-100 px-2 py-1 rounded">
                      {predictiveEta.shipment?.tracking_number}
                    </p>
                  </div>

                  <div className="space-y-2">
                    <span className="text-sm font-medium text-gray-700">Κατάσταση</span>
                    <Badge variant="outline" className="capitalize">
                      {predictiveEta.shipment?.status}
                    </Badge>
                  </div>

                  <div className="space-y-2">
                    <span className="text-sm font-medium text-gray-700">Πελάτης</span>
                    <p className="text-sm text-gray-900">{predictiveEta.shipment?.customer}</p>
                  </div>

                  <div className="space-y-2">
                    <span className="text-sm font-medium text-gray-700">Μεταφορέας</span>
                    <p className="text-sm text-gray-900">{predictiveEta.shipment?.courier}</p>
                  </div>
                </CardContent>
              </Card>

              {/* Historical Accuracy */}
              <Card>
                <CardHeader>
                  <CardTitle className="text-lg flex items-center space-x-2">
                    <BarChart3 className="w-5 h-5" />
                    <span>Απόδοση Μοντέλου</span>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-2">
                    <div className="flex items-center justify-between">
                      <span className="text-sm font-medium text-gray-700">Ιστορική Ακρίβεια</span>
                      <span className="text-sm font-semibold">{formatConfidence(predictiveEta.historical_accuracy)}</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div 
                        className="bg-green-500 h-2 rounded-full" 
                        style={{ width: `${predictiveEta.historical_accuracy * 100}%` }}
                      ></div>
                    </div>
                  </div>

                  <div className="text-xs text-gray-500">
                    Βασισμένο σε {predictiveEta.historical_accuracy * 100}% ακρίβεια παρόμοιων αποστολών
                  </div>
                </CardContent>
              </Card>

              {/* Last Updated */}
              <Card>
                <CardHeader>
                  <CardTitle className="text-lg">Τελευταία Ενημέρωση</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="flex items-center space-x-2">
                    <Clock className="w-4 h-4 text-gray-500" />
                    <span className="text-sm text-gray-600">
                      {formatDate(predictiveEta.last_updated_at)}
                    </span>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
