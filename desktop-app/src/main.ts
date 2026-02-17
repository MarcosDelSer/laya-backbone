import { app, BrowserWindow, session, ipcMain, IpcMainEvent, IpcMainInvokeEvent } from 'electron';
import path from 'path';
import { autoUpdater } from 'electron-updater';

// Parent-portal development URL
const PARENT_PORTAL_URL = 'http://localhost:3000';

// Store mainWindow reference for IPC and auto-update events
let mainWindow: BrowserWindow | null = null;

/**
 * Create the main application window
 */
function createWindow(): void {
  mainWindow = new BrowserWindow({
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
    mainWindow = null;
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

  // =====================================================
  // IPC HANDLERS for preload API
  // =====================================================

  // Handle invoke requests (returns a value)
  ipcMain.handle('get-version', (): string => app.getVersion());
  ipcMain.handle('get-platform', (): NodeJS.Platform => process.platform);
  ipcMain.handle('window-is-maximized', (event: IpcMainInvokeEvent): boolean => {
    const win = BrowserWindow.fromWebContents(event.sender);
    return win ? win.isMaximized() : false;
  });

  // Handle send requests (fire-and-forget)
  ipcMain.on('window-minimize', (event: IpcMainEvent): void => {
    const win = BrowserWindow.fromWebContents(event.sender);
    if (win) win.minimize();
  });

  ipcMain.on('window-maximize', (event: IpcMainEvent): void => {
    const win = BrowserWindow.fromWebContents(event.sender);
    if (win) {
      win.isMaximized() ? win.unmaximize() : win.maximize();
    }
  });

  ipcMain.on('window-close', (event: IpcMainEvent): void => {
    const win = BrowserWindow.fromWebContents(event.sender);
    if (win) win.close();
  });

  ipcMain.on('install-update', (): void => {
    autoUpdater.quitAndInstall();
  });

  // =====================================================
  // AUTO-UPDATE CONFIGURATION
  // =====================================================

  // Check for updates and notify user when available
  autoUpdater.checkForUpdatesAndNotify();

  // Forward update events to renderer process
  autoUpdater.on('update-available', (info): void => {
    if (mainWindow) {
      mainWindow.webContents.send('update-available', info);
    }
  });

  autoUpdater.on('update-downloaded', (info): void => {
    if (mainWindow) {
      mainWindow.webContents.send('update-downloaded', info);
    }
  });

  // macOS: Re-create window when dock icon is clicked and no windows exist
  app.on('activate', (): void => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

// Quit when all windows are closed (except on macOS)
app.on('window-all-closed', (): void => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});
