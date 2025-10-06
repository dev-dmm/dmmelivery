import React, { useState } from 'react';
import { 
  CheckSquare, 
  Square, 
  Download, 
  Upload, 
  Trash2, 
  Edit, 
  Send, 
  AlertTriangle,
  CheckCircle,
  Clock,
  X
} from 'lucide-react';

export default function BatchActions({ 
  items = [], 
  onSelectionChange = null,
  onBulkAction = null,
  availableActions = [],
  maxSelections = 100 
}) {
  const [selectedItems, setSelectedItems] = useState(new Set());
  const [showActions, setShowActions] = useState(false);
  const [actionType, setActionType] = useState('');
  const [actionParams, setActionParams] = useState({});

  const handleSelectAll = () => {
    if (selectedItems.size === items.length) {
      setSelectedItems(new Set());
    } else {
      const allIds = items.slice(0, maxSelections).map(item => item.id);
      setSelectedItems(new Set(allIds));
    }
  };

  const handleSelectItem = (itemId) => {
    const newSelection = new Set(selectedItems);
    if (newSelection.has(itemId)) {
      newSelection.delete(itemId);
    } else {
      if (newSelection.size < maxSelections) {
        newSelection.add(itemId);
      }
    }
    setSelectedItems(newSelection);
    
    if (onSelectionChange) {
      onSelectionChange(Array.from(newSelection));
    }
  };

  const handleBulkAction = async (action, params = {}) => {
    if (selectedItems.size === 0) return;

    const selectedData = items.filter(item => selectedItems.has(item.id));
    
    if (onBulkAction) {
      await onBulkAction(action, selectedData, params);
    }

    // Clear selection after action
    setSelectedItems(new Set());
    setShowActions(false);
  };

  const getActionIcon = (action) => {
    const icons = {
      export: <Download className="w-4 h-4" />,
      import: <Upload className="w-4 h-4" />,
      delete: <Trash2 className="w-4 h-4" />,
      edit: <Edit className="w-4 h-4" />,
      send: <Send className="w-4 h-4" />,
      update_status: <CheckCircle className="w-4 h-4" />,
      notify: <AlertTriangle className="w-4 h-4" />
    };
    return icons[action] || <Edit className="w-4 h-4" />;
  };

  const getActionLabel = (action) => {
    const labels = {
      export: 'Εξαγωγή',
      import: 'Εισαγωγή',
      delete: 'Διαγραφή',
      edit: 'Επεξεργασία',
      send: 'Αποστολή',
      update_status: 'Ενημέρωση Κατάστασης',
      notify: 'Ειδοποίηση'
    };
    return labels[action] || action;
  };

  if (items.length === 0) return null;

  return (
    <div className="bg-white border border-gray-200 rounded-lg p-4 mb-4">
      {/* Selection Controls */}
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center space-x-4">
          <button
            onClick={handleSelectAll}
            className="flex items-center space-x-2 text-sm font-medium text-gray-700 hover:text-gray-900"
          >
            {selectedItems.size === items.length ? (
              <CheckSquare className="w-4 h-4 text-blue-600" />
            ) : (
              <Square className="w-4 h-4 text-gray-400" />
            )}
            <span>
              {selectedItems.size === items.length ? 'Αποεπιλογή Όλων' : 'Επιλογή Όλων'}
            </span>
          </button>
          
          {selectedItems.size > 0 && (
            <span className="text-sm text-gray-500">
              {selectedItems.size} επιλεγμένα από {items.length}
            </span>
          )}
        </div>

        {selectedItems.size > 0 && (
          <div className="flex items-center space-x-2">
            <button
              onClick={() => setShowActions(!showActions)}
              className="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors"
            >
              Ενέργειες ({selectedItems.size})
            </button>
            <button
              onClick={() => setSelectedItems(new Set())}
              className="p-1 text-gray-400 hover:text-gray-600 transition-colors"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        )}
      </div>

      {/* Batch Actions Panel */}
      {showActions && selectedItems.size > 0 && (
        <div className="border-t border-gray-200 pt-4">
          <h3 className="text-sm font-medium text-gray-900 mb-3">
            Επιλέξτε ενέργεια για {selectedItems.size} στοιχεία:
          </h3>
          
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {availableActions.map((action) => (
              <button
                key={action}
                onClick={() => {
                  setActionType(action);
                  if (action === 'update_status') {
                    setActionParams({ status: 'in_progress' });
                  }
                }}
                className="flex flex-col items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
              >
                {getActionIcon(action)}
                <span className="text-xs font-medium text-gray-700 mt-1">
                  {getActionLabel(action)}
                </span>
              </button>
            ))}
          </div>

          {/* Action Parameters */}
          {actionType && (
            <div className="mt-4 p-4 bg-gray-50 rounded-lg">
              <h4 className="text-sm font-medium text-gray-900 mb-3">
                Παράμετροι για {getActionLabel(actionType)}:
              </h4>
              
              {actionType === 'update_status' && (
                <div className="space-y-3">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Νέα Κατάσταση
                    </label>
                    <select
                      value={actionParams.status || ''}
                      onChange={(e) => setActionParams({ ...actionParams, status: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                      <option value="">Επιλέξτε κατάσταση</option>
                      <option value="pending">Εκκρεμής</option>
                      <option value="in_progress">Σε Εξέλιξη</option>
                      <option value="out_for_delivery">Σε Παράδοση</option>
                      <option value="delivered">Παραδομένη</option>
                      <option value="failed">Αποτυχημένη</option>
                    </select>
                  </div>
                  
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Σχόλιο (προαιρετικό)
                    </label>
                    <textarea
                      value={actionParams.comment || ''}
                      onChange={(e) => setActionParams({ ...actionParams, comment: e.target.value })}
                      placeholder="Προσθέστε σχόλιο για την αλλαγή..."
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      rows="2"
                    />
                  </div>
                </div>
              )}

              {actionType === 'notify' && (
                <div className="space-y-3">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Τύπος Ειδοποίησης
                    </label>
                    <select
                      value={actionParams.notificationType || ''}
                      onChange={(e) => setActionParams({ ...actionParams, notificationType: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                      <option value="">Επιλέξτε τύπο</option>
                      <option value="status_update">Ενημέρωση Κατάστασης</option>
                      <option value="delay_notification">Ειδοποίηση Καθυστέρησης</option>
                      <option value="delivery_confirmation">Επιβεβαίωση Παράδοσης</option>
                    </select>
                  </div>
                  
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Μήνυμα
                    </label>
                    <textarea
                      value={actionParams.message || ''}
                      onChange={(e) => setActionParams({ ...actionParams, message: e.target.value })}
                      placeholder="Προσθέστε μήνυμα..."
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      rows="3"
                    />
                  </div>
                </div>
              )}

              {actionType === 'export' && (
                <div className="space-y-3">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Μορφή Εξαγωγής
                    </label>
                    <select
                      value={actionParams.format || 'csv'}
                      onChange={(e) => setActionParams({ ...actionParams, format: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                      <option value="csv">CSV</option>
                      <option value="excel">Excel</option>
                      <option value="pdf">PDF</option>
                    </select>
                  </div>
                  
                  <div>
                    <label className="flex items-center">
                      <input
                        type="checkbox"
                        checked={actionParams.includeDetails || false}
                        onChange={(e) => setActionParams({ ...actionParams, includeDetails: e.target.checked })}
                        className="mr-2"
                      />
                      <span className="text-sm text-gray-700">Συμπερίληψη λεπτομερειών</span>
                    </label>
                  </div>
                </div>
              )}

              {/* Action Buttons */}
              <div className="flex items-center justify-end space-x-3 mt-4">
                <button
                  onClick={() => {
                    setActionType('');
                    setActionParams({});
                  }}
                  className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                >
                  Ακύρωση
                </button>
                <button
                  onClick={() => handleBulkAction(actionType, actionParams)}
                  className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors"
                >
                  Εκτέλεση Ενέργειας
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Selection Summary */}
      {selectedItems.size > 0 && (
        <div className="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <CheckCircle className="w-4 h-4 text-blue-600 mr-2" />
              <span className="text-sm font-medium text-blue-800">
                {selectedItems.size} στοιχεία επιλέχθηκαν
              </span>
            </div>
            <button
              onClick={() => setSelectedItems(new Set())}
              className="text-sm text-blue-600 hover:text-blue-800"
            >
              Καθαρισμός
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
