import React from 'react';
import { cn } from '@/utils/cn';

/**
 * DeliveryScoreBadge - Displays customer delivery score with color coding
 * 
 * @param {number} score - The delivery score (can be positive or negative)
 * @param {string} className - Additional CSS classes
 * @param {boolean} showLabel - Whether to show "Score: " label (default: true)
 * @param {string} size - Badge size: 'sm' | 'md' | 'lg' (default: 'md')
 * @param {string} successRateRange - Success rate range string (e.g., "95% - 100%" or "Not enough data yet")
 * @param {boolean} showSuccessRate - Whether to show success rate below badge (default: true)
 * @param {object} globalScore - Global score object with score, score_status, has_enough_data, etc.
 * @param {boolean} canViewGlobalScores - Whether tenant has permission to view global scores
 */
export default function DeliveryScoreBadge({ 
  score = 0, 
  className = '', 
  showLabel = true,
  size = 'md',
  successRateRange = null,
  showSuccessRate = true,
  globalScore = null,
  canViewGlobalScores = false
}) {
  // Determine badge color and status based on score
  const getScoreInfo = (score) => {
    if (score >= 5) {
      return {
        color: 'bg-green-100 text-green-800 border-green-300',
        icon: '✓',
        label: 'Αξιόπιστος',
        variant: 'excellent'
      };
    } else if (score >= 2) {
      return {
        color: 'bg-blue-100 text-blue-800 border-blue-300',
        icon: '✓',
        label: 'Καλός',
        variant: 'good'
      };
    } else if (score >= 0) {
      return {
        color: 'bg-gray-100 text-gray-800 border-gray-300',
        icon: '○',
        label: 'Ουδέτερος',
        variant: 'neutral'
      };
    } else if (score >= -2) {
      return {
        color: 'bg-yellow-100 text-yellow-800 border-yellow-300',
        icon: '⚠',
        label: 'Προσοχή',
        variant: 'warning'
      };
    } else {
      return {
        color: 'bg-red-100 text-red-800 border-red-300',
        icon: '✗',
        label: 'Υψηλός Κίνδυνος',
        variant: 'danger'
      };
    }
  };

  const scoreInfo = getScoreInfo(score);
  
  const sizeClasses = {
    sm: 'px-1.5 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-sm',
    lg: 'px-3 py-1.5 text-base'
  };

  const displayScore = score > 0 ? `+${score}` : score;

  // Get global score info if available
  const getGlobalScoreInfo = (globalScore) => {
    if (!globalScore || globalScore.score === null || !globalScore.has_enough_data) {
      return null;
    }
    
    const globalScoreValue = globalScore.score;
    if (globalScoreValue >= 5) {
      return {
        color: 'bg-green-100 text-green-800 border-green-300',
        icon: '✓',
        label: 'Αξιόπιστος',
      };
    } else if (globalScoreValue >= 2) {
      return {
        color: 'bg-blue-100 text-blue-800 border-blue-300',
        icon: '✓',
        label: 'Καλός',
      };
    } else if (globalScoreValue >= 0) {
      return {
        color: 'bg-gray-100 text-gray-800 border-gray-300',
        icon: '○',
        label: 'Ουδέτερος',
      };
    } else if (globalScoreValue >= -2) {
      return {
        color: 'bg-yellow-100 text-yellow-800 border-yellow-300',
        icon: '⚠',
        label: 'Προσοχή',
      };
    } else {
      return {
        color: 'bg-red-100 text-red-800 border-red-300',
        icon: '✗',
        label: 'Υψηλός Κίνδυνος',
      };
    }
  };

  const globalScoreInfo = globalScore ? getGlobalScoreInfo(globalScore) : null;
  const globalDisplayScore = globalScore && globalScore.score !== null 
    ? (globalScore.score > 0 ? `+${globalScore.score}` : globalScore.score)
    : null;

  return (
    <div className="inline-flex flex-col items-start gap-1.5">
      {/* Tenant-specific score */}
      <div className="inline-flex flex-col items-start gap-0.5">
        <span
          className={cn(
            'inline-flex items-center gap-1.5 rounded-full border font-semibold',
            scoreInfo.color,
            sizeClasses[size],
            className
          )}
          title={`Delivery Score (this store): ${score} - ${scoreInfo.label}`}
        >
          <span className="text-xs">{scoreInfo.icon}</span>
          {showLabel && <span className="text-xs opacity-75">Score:</span>}
          <span className="font-bold">{displayScore}</span>
        </span>
        {showSuccessRate && successRateRange && (
          <span 
            className={cn(
              'text-[10px] leading-tight',
              successRateRange === 'Not enough data yet' 
                ? 'text-gray-500 italic' 
                : 'text-gray-600 opacity-70'
            )}
          >
            {successRateRange === 'Not enough data yet' ? (
              <span>❕ {successRateRange}</span>
            ) : (
              <span>Delivery success: {successRateRange}</span>
            )}
          </span>
        )}
      </div>

      {/* Global score (if available and permitted) */}
      {canViewGlobalScores && globalScore && (
        <div className="inline-flex flex-col items-start gap-0.5 border-t border-gray-200 pt-1.5 mt-0.5">
          {globalScoreInfo ? (
            <>
              <span
                className={cn(
                  'inline-flex items-center gap-1.5 rounded-full border font-semibold',
                  globalScoreInfo.color,
                  sizeClasses[size]
                )}
                title={`Global Delivery Score (all stores): ${globalScore.score} - ${globalScoreInfo.label}`}
              >
                <span className="text-xs">{globalScoreInfo.icon}</span>
                <span className="text-xs opacity-75">Global:</span>
                <span className="font-bold">{globalDisplayScore}</span>
              </span>
              {globalScore.success_percentage !== null && (
                <span className="text-[10px] leading-tight text-gray-600 opacity-70">
                  Global success: {Math.round(globalScore.success_percentage)}% ({globalScore.completed_shipments} shipments)
                </span>
              )}
            </>
          ) : (
            <span 
              className="text-[10px] leading-tight text-gray-500 italic"
              title="Χρειάζονται ≥3 ολοκληρωμένες αποστολές για global score"
            >
              ❕ Global score: Not enough data yet
            </span>
          )}
        </div>
      )}
      
      {canViewGlobalScores && !globalScore && (
        <div className="text-[10px] leading-tight text-gray-500 italic border-t border-gray-200 pt-1.5 mt-0.5">
          Global score: Hidden due to privacy
        </div>
      )}
    </div>
  );
}

