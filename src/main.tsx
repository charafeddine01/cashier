import React from 'react';
import ReactDOM from 'react-dom/client';
import { App } from './App';
import { ErrorBoundary } from './components/common/ErrorBoundary';
import './styles/index.css';

// Manage Service Worker for dev and production
if (typeof window !== 'undefined' && 'serviceWorker' in navigator) {
  const isLocalhost = Boolean(
    window.location.hostname === 'localhost' ||
    window.location.hostname === '127.0.0.1' ||
    window.location.hostname === '0.0.0.0' ||
    window.location.hostname === '[::1]'
  );

  if (isLocalhost) {
    // Unregister any active service worker during local development to avoid stale cached assets
    navigator.serviceWorker.getRegistrations().then((registrations) => {
      for (const reg of registrations) {
        reg.unregister().then((unregistered) => {
          if (unregistered) {
            console.log('[OmniPOS SW] Unregistered local service worker on localhost');
          }
        });
      }
    });

    // Clear service worker caches if present
    if ('caches' in window) {
      caches.keys().then((keys) => {
        for (const key of keys) {
          caches.delete(key);
        }
      });
    }
  } else if (window.location.protocol.startsWith('http')) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js').catch((err) => {
        console.log('[OmniPOS SW] Registration skipped:', err);
      });
    });
  }
}

ReactDOM.createRoot(document.getElementById('root') as HTMLElement).render(
  <React.StrictMode>
    <ErrorBoundary>
      <App />
    </ErrorBoundary>
  </React.StrictMode>
);
