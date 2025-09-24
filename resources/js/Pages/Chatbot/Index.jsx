import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { 
  MessageSquare, 
  Bot, 
  User, 
  Clock,
  CheckCircle,
  AlertTriangle,
  TrendingUp,
  Users,
  MessageCircle
} from 'lucide-react';

const ChatbotIndex = ({ sessions, stats }) => {
  const [isStarting, setIsStarting] = useState(false);
  const [isEscalating, setIsEscalating] = useState({});

  const handleStartNewSession = async () => {
    setIsStarting(true);
    try {
      const response = await fetch('/chatbot/sessions', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
          customer_id: null, // Anonymous session
          language: 'en'
        })
      });
      
      if (response.ok) {
        const result = await response.json();
        if (result.success) {
          alert('✅ New chat session started!');
          window.location.href = `/chatbot/chat/${result.data.session.id}`;
        } else {
          throw new Error(result.message || 'Failed to start new session');
        }
      } else {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to start new session');
      }
    } catch (error) {
      console.error('Error starting new session:', error);
      alert('❌ Error starting new session. Please try again.');
    } finally {
      setIsStarting(false);
    }
  };

  const handleEscalateSession = async (sessionId) => {
    setIsEscalating(prev => ({ ...prev, [sessionId]: true }));
    try {
      const response = await fetch(`/chatbot/sessions/${sessionId}/escalate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        }
      });
      
      if (response.ok) {
        alert('✅ Session escalated to human agent!');
        window.location.reload();
      } else {
        throw new Error('Failed to escalate session');
      }
    } catch (error) {
      console.error('Error escalating session:', error);
      alert('❌ Error escalating session. Please try again.');
    } finally {
      setIsEscalating(prev => ({ ...prev, [sessionId]: false }));
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'active': return 'bg-green-100 text-green-800';
      case 'resolved': return 'bg-blue-100 text-blue-800';
      case 'escalated': return 'bg-orange-100 text-orange-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'active': return '🟢';
      case 'resolved': return '✅';
      case 'escalated': return '🚨';
      default: return '❓';
    }
  };

  const getSatisfactionColor = (level) => {
    switch (level) {
      case 'satisfied': return 'bg-green-100 text-green-800';
      case 'neutral': return 'bg-yellow-100 text-yellow-800';
      case 'dissatisfied': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
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

  const formatDuration = (duration) => {
    if (!duration) return 'N/A';
    return duration;
  };

  return (
    <AuthenticatedLayout>
      <Head title="AI Chatbot" />
      
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          {/* Header */}
          <div className="mb-8">
            <div className="flex items-center justify-between">
              <div>
                <h1 className="text-3xl font-bold text-gray-900 flex items-center">
                  <Bot className="w-8 h-8 mr-3 text-blue-600" />
                  AI Chatbot
                </h1>
                <p className="mt-2 text-gray-600">
                  Intelligent customer support with AI-powered responses
                </p>
              </div>
              <Button 
                onClick={handleStartNewSession}
                disabled={isStarting}
                className="flex items-center"
              >
                <MessageSquare className="w-4 h-4 mr-2" />
                {isStarting ? 'Starting...' : 'Start New Session'}
              </Button>
            </div>
          </div>

          {/* Stats Cards */}
          <div className="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total Sessions</CardTitle>
                <MessageCircle className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stats.total_sessions}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Active</CardTitle>
                <MessageSquare className="h-4 w-4 text-green-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-green-600">{stats.active_sessions}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Resolved</CardTitle>
                <CheckCircle className="h-4 w-4 text-blue-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-blue-600">{stats.resolved_sessions}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Escalated</CardTitle>
                <AlertTriangle className="h-4 w-4 text-orange-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-orange-600">{stats.escalated_sessions}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Avg Satisfaction</CardTitle>
                <TrendingUp className="h-4 w-4 text-purple-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-purple-600">
                  {stats.avg_satisfaction ? Math.round(stats.avg_satisfaction * 20) : 0}%
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Chat Sessions List */}
          <Card>
            <CardHeader>
              <CardTitle>Recent Chat Sessions</CardTitle>
              <CardDescription>
                AI-powered customer support conversations
              </CardDescription>
            </CardHeader>
            <CardContent>
              {sessions.data.length > 0 ? (
                <div className="space-y-4">
                  {sessions.data.map((session) => (
                    <div key={session.id} className="border rounded-lg p-4 hover:bg-gray-50">
                      <div className="flex items-center justify-between">
                        <div className="flex-1">
                          <div className="flex items-center space-x-4">
                            <div>
                              <p className="font-medium text-gray-900">
                                Session {session.session_id?.slice(0, 8) || 'Unknown'}
                              </p>
                              <p className="text-sm text-gray-500">
                                {session.customer?.name || 'Anonymous Customer'} • {session.language?.toUpperCase() || 'EN'}
                              </p>
                            </div>
                            <div className="flex items-center space-x-2">
                              <Badge className={getStatusColor(session.status)}>
                                {getStatusIcon(session.status)} {session.status}
                              </Badge>
                              {session.satisfaction_level && session.satisfaction_level !== 'not_rated' && (
                                <Badge className={getSatisfactionColor(session.satisfaction_level)}>
                                  {session.satisfaction_level}
                                </Badge>
                              )}
                            </div>
                          </div>
                          
                          <div className="mt-3 grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                              <p className="text-xs text-gray-500 mb-1">Duration</p>
                              <p className="text-sm text-gray-900">
                                {formatDuration(session.duration)}
                              </p>
                            </div>
                            <div>
                              <p className="text-xs text-gray-500 mb-1">Last Activity</p>
                              <p className="text-sm text-gray-900">
                                {formatDate(session.last_activity_at)}
                              </p>
                            </div>
                            <div>
                              <p className="text-xs text-gray-500 mb-1">Messages</p>
                              <p className="text-sm text-gray-900">
                                {session.messages?.length || 0} messages
                              </p>
                            </div>
                            <div>
                              <p className="text-xs text-gray-500 mb-1">Satisfaction</p>
                              <p className="text-sm text-gray-900">
                                {session.satisfaction_rating ? `${session.satisfaction_rating}/5` : 'Not rated'}
                              </p>
                            </div>
                          </div>

                          {session.messages && session.messages.length > 0 && (
                            <div className="mt-3">
                              <p className="text-xs text-gray-500 mb-2">Last Message:</p>
                              <div className="bg-gray-50 rounded p-2">
                                <div className="flex items-center space-x-2">
                                  <span className="text-xs text-gray-500">
                                    {session.messages[session.messages.length - 1].sender_display_name}:
                                  </span>
                                  <span className="text-sm text-gray-700">
                                    {session.messages[session.messages.length - 1].message?.substring(0, 100)}
                                    {session.messages[session.messages.length - 1].message?.length > 100 ? '...' : ''}
                                  </span>
                                </div>
                              </div>
                            </div>
                          )}
                        </div>
                        
                        <div className="flex items-center space-x-2">
                          {session.status === 'active' && (
                            <Button
                              onClick={() => handleEscalateSession(session.id)}
                              disabled={isEscalating[session.id]}
                              variant="outline"
                              size="sm"
                              className="text-orange-600 border-orange-300 hover:bg-orange-50"
                            >
                              <AlertTriangle className="w-4 h-4 mr-1" />
                              {isEscalating[session.id] ? 'Escalating...' : 'Escalate'}
                            </Button>
                          )}
                          
                          <Link
                            href={`/chatbot/chat/${session.id}`}
                            className="inline-flex items-center px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                          >
                            <MessageSquare className="w-4 h-4 mr-1" />
                            View Chat
                          </Link>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-12">
                  <Bot className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                  <p className="text-lg font-medium text-gray-900 mb-2">No Chat Sessions</p>
                  <p className="text-gray-500 mb-4">
                    Start a conversation with your AI assistant
                  </p>
                  <Button onClick={handleStartNewSession} disabled={isStarting}>
                    <MessageSquare className="w-4 h-4 mr-2" />
                    {isStarting ? 'Starting...' : 'Start New Session'}
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

export default ChatbotIndex;
