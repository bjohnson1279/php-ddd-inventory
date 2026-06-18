import React, { useEffect, useState, useRef } from 'react';
import client from '../api/client';

interface Notification {
  id: string;
  tenant_id: string;
  title: string;
  message: string;
  type: 'info' | 'success' | 'warning' | 'error';
  is_read: boolean;
  created_at: string;
}

export default function NotificationBell() {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  const fetchNotifications = async () => {
    try {
      const res = await client.get('/notifications');
      if (res && res.notifications) {
        setNotifications(res.notifications);
      }
    } catch (e) {
      console.error('Failed to fetch notifications', e);
    }
  };

  useEffect(() => {
    fetchNotifications();

    const token = localStorage.getItem('token');
    if (!token) return;

    // Connect to SSE stream
    const eventSource = new EventSource(`/api/notifications/subscribe?token=${token}`);

    eventSource.addEventListener('notification', (e: any) => {
      try {
        const notif = JSON.parse(e.data) as Notification;
        setNotifications((prev) => {
          // Avoid duplicate notifications
          if (prev.some((n) => n.id === notif.id)) return prev;
          return [notif, ...prev];
        });
      } catch (err) {
        console.error('Failed to parse SSE notification', err);
      }
    });

    eventSource.addEventListener('error', (e) => {
      console.log('SSE connection error, reconnecting...', e);
    });

    return () => {
      eventSource.close();
    };
  }, []);

  // Close dropdown on outside click
  useEffect(() => {
    function handleOutsideClick(e: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setIsOpen(false);
      }
    }
    document.addEventListener('mousedown', handleOutsideClick);
    return () => document.removeEventListener('mousedown', handleOutsideClick);
  }, []);

  const unreadCount = notifications.filter((n) => !n.is_read).length;

  const handleMarkAsRead = async (id: string) => {
    try {
      await client.post(`/notifications/${id}/read`);
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, is_read: true } : n))
      );
    } catch (e) {
      console.error(e);
    }
  };

  const handleMarkAllAsRead = async () => {
    try {
      await client.post('/notifications/read-all');
      setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
    } catch (e) {
      console.error(e);
    }
  };

  const getTypeStyle = (type: Notification['type']) => {
    switch (type) {
      case 'success': return { color: '#10B981', bg: 'rgba(16, 185, 129, 0.1)' };
      case 'warning': return { color: '#F59E0B', bg: 'rgba(245, 158, 11, 0.1)' };
      case 'error': return { color: '#EF4444', bg: 'rgba(239, 68, 68, 0.1)' };
      default: return { color: '#3B82F6', bg: 'rgba(59, 130, 246, 0.1)' };
    }
  };

  return (
    <div className="notification-bell-container" ref={dropdownRef}>
      <button 
        className="btn-secondary notification-bell-button" 
        onClick={() => setIsOpen(!isOpen)}
        style={{ position: 'relative', padding: '0' }}
        aria-label={unreadCount > 0 ? `Notifications, ${unreadCount} unread` : 'Notifications, no unread messages'}
        aria-expanded={isOpen}
      >
        <span aria-hidden="true">🔔</span>
        {unreadCount > 0 && (
          <span className="notification-badge" aria-hidden="true">{unreadCount}</span>
        )}
      </button>

      {isOpen && (
        <div className="notification-dropdown">
          <div className="notification-header">
            <h3>Notifications</h3>
            {unreadCount > 0 && (
              <button className="btn-link" onClick={handleMarkAllAsRead}>
                Mark all as read
              </button>
            )}
          </div>
          <div className="notification-list">
            {notifications.length === 0 ? (
              <div className="notification-empty">No notifications yet.</div>
            ) : (
              notifications.map((n) => {
                const style = getTypeStyle(n.type);
                return (
                  <div 
                    key={n.id} 
                    className={`notification-item ${n.is_read ? 'read' : 'unread'}`}
                    onClick={() => !n.is_read && handleMarkAsRead(n.id)}
                    onKeyDown={n.is_read ? undefined : (e) => {
                      if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        handleMarkAsRead(n.id);
                      }
                    }}
                    tabIndex={n.is_read ? undefined : 0}
                    role={n.is_read ? undefined : 'button'}
                    aria-label={n.is_read ? undefined : `${n.title}: ${n.message} at ${new Date(n.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}. Unread`}
                  >
                    <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'flex-start' }}>
                      <span 
                        className="notification-type-dot" 
                        style={{ backgroundColor: style.color }}
                      />
                      <div style={{ flex: 1 }}>
                        <h4 className="notification-item-title">{n.title}</h4>
                        <p className="notification-item-message">{n.message}</p>
                        <span className="notification-item-time">
                          {new Date(n.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </span>
                      </div>
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </div>
      )}
    </div>
  );
}
