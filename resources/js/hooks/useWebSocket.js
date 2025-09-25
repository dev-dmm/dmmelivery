import { useEffect, useRef, useState, useCallback } from 'react';
import Pusher from 'pusher-js';

export const useWebSocket = (tenantId, userId) => {
  const [isConnected, setIsConnected] = useState(false);
  const [lastMessage, setLastMessage] = useState(null);
  const pusherRef = useRef(null);
  const channelsRef = useRef({});

  const initializePusher = useCallback(() => {
    if (pusherRef.current) {
      pusherRef.current.disconnect();
    }

    // Check if Pusher configuration is available
    const pusherKey = import.meta.env.PUSHER_APP_ID;
    const pusherCluster = import.meta.env.PUSHER_APP_CLUSTER;

    if (!pusherKey || !pusherCluster) {
      console.warn('Pusher configuration not found. WebSocket features will be disabled.');
      setIsConnected(false);
      return null;
    }

    const pusher = new Pusher(pusherKey, {
      cluster: pusherCluster,
      encrypted: true,
      authEndpoint: '/api/websocket/authenticate',
      auth: {
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        },
      },
    });

    pusherRef.current = pusher;

    pusher.connection.bind('connected', () => {
      setIsConnected(true);
      console.log('WebSocket connected');
    });

    pusher.connection.bind('disconnected', () => {
      setIsConnected(false);
      console.log('WebSocket disconnected');
    });

    pusher.connection.bind('error', (error) => {
      console.error('WebSocket error:', error);
      setIsConnected(false);
    });

    return pusher;
  }, []);

  const subscribeToChannel = useCallback((channelName, eventName, callback) => {
    if (!pusherRef.current) return;

    const channel = pusherRef.current.subscribe(channelName);
    channelsRef.current[channelName] = channel;

    channel.bind(eventName, (data) => {
      setLastMessage({ event: eventName, data, timestamp: new Date() });
      callback(data);
    });

    console.log(`Subscribed to ${channelName}:${eventName}`);
  }, []);

  const unsubscribeFromChannel = useCallback((channelName) => {
    if (pusherRef.current && channelsRef.current[channelName]) {
      pusherRef.current.unsubscribe(channelName);
      delete channelsRef.current[channelName];
      console.log(`Unsubscribed from ${channelName}`);
    }
  }, []);

  const subscribeToShipmentUpdates = useCallback((callback) => {
    const channelName = `tenant_${tenantId}`;
    subscribeToChannel(channelName, 'shipment.updated', callback);
  }, [tenantId, subscribeToChannel]);

  const subscribeToNewShipments = useCallback((callback) => {
    const channelName = `tenant_${tenantId}`;
    subscribeToChannel(channelName, 'shipment.created', callback);
  }, [tenantId, subscribeToChannel]);

  const subscribeToShipmentDelivered = useCallback((callback) => {
    const channelName = `tenant_${tenantId}`;
    subscribeToChannel(channelName, 'shipment.delivered', callback);
  }, [tenantId, subscribeToChannel]);

  const subscribeToAlerts = useCallback((callback) => {
    const channelName = `tenant_${tenantId}`;
    subscribeToChannel(channelName, 'alert.triggered', callback);
  }, [tenantId, subscribeToChannel]);

  const subscribeToDashboardUpdates = useCallback((callback) => {
    const channelName = `tenant_${tenantId}`;
    subscribeToChannel(channelName, 'dashboard.updated', callback);
  }, [tenantId, subscribeToChannel]);

  const subscribeToSystemNotifications = useCallback((callback) => {
    const channelName = `tenant_${tenantId}`;
    subscribeToChannel(channelName, 'system.notification', callback);
  }, [tenantId, subscribeToChannel]);

  const subscribeToUserUpdates = useCallback((callback) => {
    const channelName = `user_${userId}`;
    subscribeToChannel(channelName, 'user.update', callback);
  }, [userId, subscribeToChannel]);

  useEffect(() => {
    if (tenantId && userId) {
      const pusher = initializePusher();
      if (!pusher) {
        console.warn('WebSocket initialization failed. Real-time features will be disabled.');
      }
    }

    return () => {
      if (pusherRef.current) {
        pusherRef.current.disconnect();
      }
    };
  }, [tenantId, userId, initializePusher]);

  return {
    isConnected,
    lastMessage,
    subscribeToChannel,
    unsubscribeFromChannel,
    subscribeToShipmentUpdates,
    subscribeToNewShipments,
    subscribeToShipmentDelivered,
    subscribeToAlerts,
    subscribeToDashboardUpdates,
    subscribeToSystemNotifications,
    subscribeToUserUpdates,
  };
};

export default useWebSocket;
