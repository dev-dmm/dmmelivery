import React, { useState, useEffect } from 'react';
import { 
  HelpCircle, 
  X, 
  ChevronRight, 
  ChevronLeft, 
  CheckCircle, 
  Lightbulb,
  BookOpen,
  MessageCircle,
  Video,
  FileText,
  ArrowRight
} from 'lucide-react';

export default function OnboardingHelp({ 
  isNewUser = false,
  onClose = null,
  showHelpButton = true 
}) {
  const [isOpen, setIsOpen] = useState(false);
  const [currentStep, setCurrentStep] = useState(0);
  const [completedSteps, setCompletedSteps] = useState(new Set());
  const [showTooltips, setShowTooltips] = useState(false);

  useEffect(() => {
    // Auto-show for new users
    if (isNewUser) {
      setIsOpen(true);
    }
  }, [isNewUser]);

  const onboardingSteps = [
    {
      id: 'welcome',
      title: 'Καλώς ήρθατε στο Dashboard!',
      content: 'Αυτός είναι ο κεντρικός πίνακας ελέγχου για την παρακολούθηση των αποστολών σας.',
      target: '.dashboard-header',
      action: 'Επόμενο'
    },
    {
      id: 'stats',
      title: 'Στατιστικά Κάρτες',
      content: 'Αυτές οι κάρτες δείχνουν τα βασικά μετρικά. Κάντε κλικ για λεπτομερή στοιχεία.',
      target: '.stat-cards',
      action: 'Επόμενο'
    },
    {
      id: 'charts',
      title: 'Γραφήματα & Αναλύσεις',
      content: 'Εδώ βλέπετε γραφικά αναλύσεις των αποστολών σας και τάσεις απόδοσης.',
      target: '.charts-section',
      action: 'Επόμενο'
    },
    {
      id: 'recent',
      title: 'Πρόσφατες Αποστολές',
      content: 'Παρακολουθήστε τις τελευταίες αποστολές και τις καταστάσεις τους.',
      target: '.recent-shipments',
      action: 'Επόμενο'
    },
    {
      id: 'alerts',
      title: 'Ειδοποιήσεις',
      content: 'Εδώ θα δείτε κρίσιμες ειδοποιήσεις και προειδοποιήσεις για τις αποστολές σας.',
      target: '.alerts-section',
      action: 'Ολοκλήρωση'
    }
  ];

  const helpTopics = [
    {
      id: 'getting-started',
      title: 'Ξεκινώντας',
      icon: <BookOpen className="w-5 h-5" />,
      content: 'Βασικές οδηγίες για τη χρήση του συστήματος',
      href: '/help/getting-started'
    },
    {
      id: 'shipments',
      title: 'Διαχείριση Αποστολών',
      icon: <FileText className="w-5 h-5" />,
      content: 'Πώς να δημιουργείτε και να παρακολουθείτε αποστολές',
      href: '/help/shipments'
    },
    {
      id: 'analytics',
      title: 'Αναλύσεις',
      icon: <Lightbulb className="w-5 h-5" />,
      content: 'Κατανόηση των αναλυτικών στοιχείων και μετρικών',
      href: '/help/analytics'
    },
    {
      id: 'notifications',
      title: 'Ειδοποιήσεις',
      icon: <MessageCircle className="w-5 h-5" />,
      content: 'Ρύθμιση και διαχείριση ειδοποιήσεων',
      href: '/help/notifications'
    }
  ];

  const quickActions = [
    {
      title: 'Δημιουργία Νέας Αποστολής',
      description: 'Προσθέστε μια νέα αποστολή στο σύστημα',
      action: () => window.location.href = '/shipments/create',
      icon: <ArrowRight className="w-4 h-4" />
    },
    {
      title: 'Προβολή Όλων των Αποστολών',
      description: 'Δείτε όλες τις αποστολές σας',
      action: () => window.location.href = '/shipments',
      icon: <ArrowRight className="w-4 h-4" />
    },
    {
      title: 'Ρυθμίσεις Ειδοποιήσεων',
      description: 'Διαμορφώστε τις ειδοποιήσεις σας',
      action: () => window.location.href = '/alerts',
      icon: <ArrowRight className="w-4 h-4" />
    }
  ];

  const handleNextStep = () => {
    if (currentStep < onboardingSteps.length - 1) {
      setCompletedSteps(prev => new Set([...prev, currentStep]));
      setCurrentStep(currentStep + 1);
    } else {
      setCompletedSteps(prev => new Set([...prev, currentStep]));
      setIsOpen(false);
      if (onClose) onClose();
    }
  };

  const handlePrevStep = () => {
    if (currentStep > 0) {
      setCurrentStep(currentStep - 1);
    }
  };

  const handleSkipTutorial = () => {
    setIsOpen(false);
    if (onClose) onClose();
  };

  const handleCompleteStep = (stepId) => {
    setCompletedSteps(prev => new Set([...prev, stepId]));
  };

  if (!isOpen && !showHelpButton) return null;

  return (
    <>
      {/* Help Button */}
      {showHelpButton && !isOpen && (
        <div className="fixed bottom-6 right-6 z-40">
          <button
            onClick={() => setIsOpen(true)}
            className="bg-blue-600 text-white p-3 rounded-full shadow-lg hover:bg-blue-700 transition-colors"
          >
            <HelpCircle className="w-6 h-6" />
          </button>
        </div>
      )}

      {/* Help Modal */}
      {isOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            {/* Header */}
            <div className="flex items-center justify-between p-6 border-b border-gray-200">
              <div className="flex items-center">
                <HelpCircle className="w-6 h-6 text-blue-600 mr-3" />
                <div>
                  <h2 className="text-xl font-semibold text-gray-900">
                    {isNewUser ? 'Οδηγός Πρώτων Βημάτων' : 'Βοήθεια & Υποστήριξη'}
                  </h2>
                  <p className="text-sm text-gray-500">
                    {isNewUser ? 'Ας εξερευνήσουμε το dashboard μαζί' : 'Βρείτε απαντήσεις και βοήθεια'}
                  </p>
                </div>
              </div>
              <button
                onClick={() => setIsOpen(false)}
                className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Content */}
            <div className="p-6 overflow-y-auto max-h-[70vh]">
              {isNewUser ? (
                /* Onboarding Tutorial */
                <div className="space-y-6">
                  {/* Progress */}
                  <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                      {onboardingSteps.map((_, index) => (
                        <div
                          key={index}
                          className={`w-3 h-3 rounded-full ${
                            index <= currentStep ? 'bg-blue-600' : 'bg-gray-300'
                          }`}
                        />
                      ))}
                    </div>
                    <span className="text-sm text-gray-500">
                      Βήμα {currentStep + 1} από {onboardingSteps.length}
                    </span>
                  </div>

                  {/* Current Step */}
                  <div className="text-center">
                    <h3 className="text-2xl font-semibold text-gray-900 mb-4">
                      {onboardingSteps[currentStep].title}
                    </h3>
                    <p className="text-lg text-gray-600 mb-6">
                      {onboardingSteps[currentStep].content}
                    </p>
                    
                    {/* Step Illustration */}
                    <div className="bg-gray-50 rounded-lg p-8 mb-6">
                      <div className="text-6xl mb-4">📊</div>
                      <p className="text-gray-500">
                        {currentStep === 0 && 'Καλώς ήρθατε στο σύστημα διαχείρισης αποστολών!'}
                        {currentStep === 1 && 'Κάντε κλικ στις κάρτες για λεπτομερή στοιχεία'}
                        {currentStep === 2 && 'Αναλύστε τα γραφήματα για καλύτερη κατανόηση'}
                        {currentStep === 3 && 'Παρακολουθήστε τις πρόσφατες αποστολές σας'}
                        {currentStep === 4 && 'Διαχειριστείτε τις ειδοποιήσεις και προειδοποιήσεις'}
                      </p>
                    </div>
                  </div>

                  {/* Navigation */}
                  <div className="flex items-center justify-between">
                    <button
                      onClick={handlePrevStep}
                      disabled={currentStep === 0}
                      className="flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <ChevronLeft className="w-4 h-4 mr-2" />
                      Προηγούμενο
                    </button>

                    <div className="flex space-x-3">
                      <button
                        onClick={handleSkipTutorial}
                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                      >
                        Παράλειψη
                      </button>
                      <button
                        onClick={handleNextStep}
                        className="flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
                      >
                        {onboardingSteps[currentStep].action}
                        <ChevronRight className="w-4 h-4 ml-2" />
                      </button>
                    </div>
                  </div>
                </div>
              ) : (
                /* Help Topics */
                <div className="space-y-6">
                  {/* Quick Actions */}
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Γρήγορες Ενέργειες</h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                      {quickActions.map((action, index) => (
                        <button
                          key={index}
                          onClick={action.action}
                          className="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors text-left"
                        >
                          <div className="flex items-center justify-between">
                            <div>
                              <h4 className="font-medium text-gray-900">{action.title}</h4>
                              <p className="text-sm text-gray-500 mt-1">{action.description}</p>
                            </div>
                            {action.icon}
                          </div>
                        </button>
                      ))}
                    </div>
                  </div>

                  {/* Help Topics */}
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Θέματα Βοήθειας</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      {helpTopics.map((topic) => (
                        <a
                          key={topic.id}
                          href={topic.href}
                          className="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors group"
                        >
                          <div className="flex items-center mb-2">
                            {topic.icon}
                            <h4 className="font-medium text-gray-900 ml-3">{topic.title}</h4>
                            <ArrowRight className="w-4 h-4 text-gray-400 ml-auto group-hover:text-blue-600 transition-colors" />
                          </div>
                          <p className="text-sm text-gray-500">{topic.content}</p>
                        </a>
                      ))}
                    </div>
                  </div>

                  {/* Contact Support */}
                  <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div className="flex items-center mb-2">
                      <MessageCircle className="w-5 h-5 text-blue-600 mr-2" />
                      <h4 className="font-medium text-blue-900">Χρειάζεστε Περισσότερη Βοήθεια;</h4>
                    </div>
                    <p className="text-sm text-blue-700 mb-3">
                      Επικοινωνήστε με την ομάδα υποστήριξης για άμεση βοήθεια.
                    </p>
                    <div className="flex space-x-3">
                      <button className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors">
                        Ζωντανή Συνομιλία
                      </button>
                      <button className="px-4 py-2 bg-white text-blue-600 text-sm border border-blue-300 rounded-md hover:bg-blue-50 transition-colors">
                        Email Υποστήριξη
                      </button>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Tooltips Toggle */}
      {!isNewUser && (
        <div className="fixed bottom-6 left-6 z-40">
          <button
            onClick={() => setShowTooltips(!showTooltips)}
            className={`p-3 rounded-full shadow-lg transition-colors ${
              showTooltips ? 'bg-green-600 text-white' : 'bg-gray-600 text-white'
            }`}
          >
            <Lightbulb className="w-5 h-5" />
          </button>
        </div>
      )}
    </>
  );
}
