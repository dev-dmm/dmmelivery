import { useState, useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { 
  MessageSquare, 
  Bot, 
  User, 
  Send,
  ArrowLeft,
  AlertTriangle,
  CheckCircle,
  Clock
} from 'lucide-react';

const ChatbotChat = ({ session, messages = [] }) => {
  const [message, setMessage] = useState('');
  const [isSending, setIsSending] = useState(false);
  const [chatMessages, setChatMessages] = useState(messages);
  const messagesEndRef = useRef(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
  };

  useEffect(() => {
    scrollToBottom();
  }, [chatMessages]);

  const handleSendMessage = async (e) => {
    e.preventDefault();
    if (!message.trim() || isSending) return;

    const userMessage = {
      id: Date.now(),
      message: message.trim(),
      sender_type: 'customer',
      sender_display_name: 'You',
      message_type: 'text',
      is_ai_generated: false,
      created_at: new Date().toISOString(),
    };

    setChatMessages(prev => [...prev, userMessage]);
    setMessage('');
    setIsSending(true);

    try {
      const response = await fetch(`/chatbot/sessions/${session.id}/messages`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
          message: userMessage.message,
          customer_id: session.customer?.id || null
        })
      });

      if (response.ok) {
        const result = await response.json();
        if (result.success && result.data.ai_response) {
          const aiMessage = {
            id: result.data.ai_response.id,
            message: result.data.ai_response.message,
            sender_type: 'ai',
            sender_display_name: 'AI Assistant',
            message_type: result.data.ai_response.message_type,
            is_ai_generated: true,
            confidence_score: result.data.ai_response.confidence_score,
            intent: result.data.ai_response.intent,
            created_at: result.data.ai_response.created_at,
          };
          setChatMessages(prev => [...prev, aiMessage]);
        }
      } else {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to send message');
      }
    } catch (error) {
      console.error('Error sending message:', error);
      const errorMessage = {
        id: Date.now() + 1,
        message: `❌ Error: ${error.message}`,
        sender_type: 'system',
        sender_display_name: 'System',
        message_type: 'error',
        is_ai_generated: false,
        created_at: new Date().toISOString(),
      };
      setChatMessages(prev => [...prev, errorMessage]);
    } finally {
      setIsSending(false);
    }
  };

  const getMessageIcon = (senderType) => {
    switch (senderType) {
      case 'customer': return <User className="w-4 h-4" />;
      case 'ai': return <Bot className="w-4 h-4" />;
      case 'system': return <AlertTriangle className="w-4 h-4" />;
      default: return <MessageSquare className="w-4 h-4" />;
    }
  };

  const getMessageColor = (senderType) => {
    switch (senderType) {
      case 'customer': return 'bg-blue-100 text-blue-800';
      case 'ai': return 'bg-green-100 text-green-800';
      case 'system': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleTimeString('el-GR', {
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  return (
    <AuthenticatedLayout>
      <Head title={`Chat Session - ${session.session_id?.slice(0, 8)}`} />
      
      <div className="h-screen flex flex-col">
        {/* Header */}
        <div className="bg-white border-b border-gray-200 px-6 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <Link
                href="/chatbot"
                className="inline-flex items-center text-gray-600 hover:text-gray-900"
              >
                <ArrowLeft className="w-4 h-4 mr-2" />
                Back to Sessions
              </Link>
              <div>
                <h1 className="text-xl font-semibold text-gray-900 flex items-center">
                  <Bot className="w-6 h-6 mr-2 text-blue-600" />
                  AI Chatbot
                </h1>
                <p className="text-sm text-gray-500">
                  Session {session.session_id?.slice(0, 8)} • {session.language?.toUpperCase()}
                </p>
              </div>
            </div>
            <div className="flex items-center space-x-2">
              <Badge className={session.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}>
                {session.status === 'active' ? <CheckCircle className="w-3 h-3 mr-1" /> : <Clock className="w-3 h-3 mr-1" />}
                {session.status}
              </Badge>
            </div>
          </div>
        </div>

        {/* Messages */}
        <div className="flex-1 overflow-y-auto p-6 space-y-4">
          {chatMessages.length === 0 ? (
            <div className="text-center py-12">
              <Bot className="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <p className="text-lg font-medium text-gray-900 mb-2">Start a Conversation</p>
              <p className="text-gray-500">Ask me anything about your shipments, deliveries, or tracking!</p>
            </div>
          ) : (
            chatMessages.map((msg) => (
              <div key={msg.id} className={`flex ${msg.sender_type === 'customer' ? 'justify-end' : 'justify-start'}`}>
                <div className={`max-w-xs lg:max-w-md px-4 py-2 rounded-lg ${
                  msg.sender_type === 'customer' 
                    ? 'bg-blue-600 text-white' 
                    : msg.sender_type === 'ai'
                    ? 'bg-gray-100 text-gray-900'
                    : 'bg-red-100 text-red-900'
                }`}>
                  <div className="flex items-center space-x-2 mb-1">
                    {getMessageIcon(msg.sender_type)}
                    <span className="text-xs font-medium">{msg.sender_display_name}</span>
                    <span className="text-xs opacity-75">{formatDate(msg.created_at)}</span>
                  </div>
                  <p className="text-sm">{msg.message}</p>
                  {msg.confidence_score && (
                    <div className="mt-1 text-xs opacity-75">
                      Confidence: {Math.round(msg.confidence_score * 100)}%
                    </div>
                  )}
                </div>
              </div>
            ))
          )}
          {isSending && (
            <div className="flex justify-start">
              <div className="bg-gray-100 text-gray-900 px-4 py-2 rounded-lg">
                <div className="flex items-center space-x-2">
                  <Bot className="w-4 h-4" />
                  <span className="text-sm">AI is thinking...</span>
                </div>
              </div>
            </div>
          )}
          <div ref={messagesEndRef} />
        </div>

        {/* Message Input */}
        <div className="bg-white border-t border-gray-200 px-6 py-4">
          <form onSubmit={handleSendMessage} className="flex space-x-4">
            <input
              type="text"
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              placeholder="Type your message here..."
              className="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              disabled={isSending}
            />
            <Button 
              type="submit" 
              disabled={!message.trim() || isSending}
              className="flex items-center"
            >
              <Send className="w-4 h-4 mr-2" />
              {isSending ? 'Sending...' : 'Send'}
            </Button>
          </form>
        </div>
      </div>
    </AuthenticatedLayout>
  );
};

export default ChatbotChat;

