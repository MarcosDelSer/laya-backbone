const { app, BrowserWindow } = require('electron');
const path = require('path');

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
      // Security settings - will be enhanced in phase 2
      nodeIntegration: false,
      contextIsolation: true
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
  createWindow();

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
