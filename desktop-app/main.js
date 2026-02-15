const { app, BrowserWindow, session } = require('electron');
const path = require('path');
const { autoUpdater } = require('electron-updater');

// Parent-portal development URL
const PARENT_PORTAL_URL = 'http://localhost:3000';

/**
 * Create the main application window
 */
function createWindow() {
  const mainWindow = new BrowserWindow({
    width: 1200,
    height: 800,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      // Security settings - prevent renderer from accessing Node.js APIs directly
      nodeIntegration: false,
      contextIsolation: true,
      sandbox: true
    }
  });

  // Load the parent-portal interface
  mainWindow.loadURL(PARENT_PORTAL_URL);

  // Handle window closed
  mainWindow.on('closed', () => {
    // Dereference the window object
  });
}

// App ready event
app.whenReady().then(() => {
  // Configure Content Security Policy headers for security
  session.defaultSession.webRequest.onHeadersReceived((details, callback) => {
    callback({
      responseHeaders: {
        ...details.responseHeaders,
        'Content-Security-Policy': [
          "default-src 'self'; " +
          "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " +
          "style-src 'self' 'unsafe-inline'; " +
          "img-src 'self' data: https:; " +
          "font-src 'self' data:; " +
          "connect-src 'self' http://localhost:* ws://localhost:*"
        ]
      }
    });
  });

  createWindow();

  // Check for updates and notify user when available
  autoUpdater.checkForUpdatesAndNotify();

  // macOS: Re-create window when dock icon is clicked and no windows exist
  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

// Quit when all windows are closed (except on macOS)
app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});
