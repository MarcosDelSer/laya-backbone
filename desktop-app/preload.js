/**
 * Preload script for LAYA Desktop App
 *
 * This script runs in an isolated context with access to Node.js APIs.
 * It uses contextBridge to safely expose only specific, controlled functions
 * to the renderer process. This is a critical security boundary.
 *
 * SECURITY: NEVER expose the entire ipcRenderer module to the renderer.
 * Only expose specific functions that wrap specific IPC channels.
 */

const { contextBridge, ipcRenderer } = require('electron');

/**
 * Electron API exposed to the renderer process via window.electronAPI
 *
 * Each function wraps a specific IPC channel, ensuring the renderer
 * can only access whitelisted functionality.
 */
contextBridge.exposeInMainWorld('electronAPI', {
  /**
   * Get the application version
   * @returns {Promise<string>} The app version from package.json
   */
  getVersion: () => ipcRenderer.invoke('get-version'),

  /**
   * Minimize the application window
   */
  minimize: () => ipcRenderer.send('window-minimize'),

  /**
   * Maximize or restore the application window
   */
  maximize: () => ipcRenderer.send('window-maximize'),

  /**
   * Close the application window
   */
  close: () => ipcRenderer.send('window-close'),

  /**
   * Check if the window is maximized
   * @returns {Promise<boolean>} Whether the window is maximized
   */
  isMaximized: () => ipcRenderer.invoke('window-is-maximized'),

  /**
   * Get platform information
   * @returns {Promise<string>} The operating system platform
   */
  getPlatform: () => ipcRenderer.invoke('get-platform'),

  /**
   * Subscribe to update available events
   * @param {Function} callback - Called when an update is available
   * @returns {Function} Unsubscribe function
   */
  onUpdateAvailable: (callback) => {
    const subscription = (_event, info) => callback(info);
    ipcRenderer.on('update-available', subscription);
    return () => ipcRenderer.removeListener('update-available', subscription);
  },

  /**
   * Subscribe to update downloaded events
   * @param {Function} callback - Called when an update has been downloaded
   * @returns {Function} Unsubscribe function
   */
  onUpdateDownloaded: (callback) => {
    const subscription = (_event, info) => callback(info);
    ipcRenderer.on('update-downloaded', subscription);
    return () => ipcRenderer.removeListener('update-downloaded', subscription);
  },

  /**
   * Install a downloaded update and restart the app
   */
  installUpdate: () => ipcRenderer.send('install-update')
});
