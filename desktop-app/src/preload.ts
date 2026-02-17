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

import { contextBridge, ipcRenderer, IpcRendererEvent } from 'electron';

/**
 * Type definition for update information
 */
interface UpdateInfo {
  version: string;
  releaseDate?: string;
  releaseNotes?: string;
  [key: string]: unknown;
}

/**
 * Type definition for callbacks used in event subscriptions
 */
type UpdateCallback = (info: UpdateInfo) => void;
type UnsubscribeFunction = () => void;

/**
 * Electron API interface exposed to the renderer process
 */
interface ElectronAPI {
  getVersion: () => Promise<string>;
  minimize: () => void;
  maximize: () => void;
  close: () => void;
  isMaximized: () => Promise<boolean>;
  getPlatform: () => Promise<string>;
  onUpdateAvailable: (callback: UpdateCallback) => UnsubscribeFunction;
  onUpdateDownloaded: (callback: UpdateCallback) => UnsubscribeFunction;
  installUpdate: () => void;
}

/**
 * Electron API exposed to the renderer process via window.electronAPI
 *
 * Each function wraps a specific IPC channel, ensuring the renderer
 * can only access whitelisted functionality.
 */
const electronAPI: ElectronAPI = {
  /**
   * Get the application version
   * @returns {Promise<string>} The app version from package.json
   */
  getVersion: (): Promise<string> => ipcRenderer.invoke('get-version'),

  /**
   * Minimize the application window
   */
  minimize: (): void => {
    ipcRenderer.send('window-minimize');
  },

  /**
   * Maximize or restore the application window
   */
  maximize: (): void => {
    ipcRenderer.send('window-maximize');
  },

  /**
   * Close the application window
   */
  close: (): void => {
    ipcRenderer.send('window-close');
  },

  /**
   * Check if the window is maximized
   * @returns {Promise<boolean>} Whether the window is maximized
   */
  isMaximized: (): Promise<boolean> => ipcRenderer.invoke('window-is-maximized'),

  /**
   * Get platform information
   * @returns {Promise<string>} The operating system platform
   */
  getPlatform: (): Promise<string> => ipcRenderer.invoke('get-platform'),

  /**
   * Subscribe to update available events
   * @param {UpdateCallback} callback - Called when an update is available
   * @returns {UnsubscribeFunction} Unsubscribe function
   */
  onUpdateAvailable: (callback: UpdateCallback): UnsubscribeFunction => {
    const subscription = (_event: IpcRendererEvent, info: UpdateInfo): void => {
      callback(info);
    };
    ipcRenderer.on('update-available', subscription);
    return (): void => {
      ipcRenderer.removeListener('update-available', subscription);
    };
  },

  /**
   * Subscribe to update downloaded events
   * @param {UpdateCallback} callback - Called when an update has been downloaded
   * @returns {UnsubscribeFunction} Unsubscribe function
   */
  onUpdateDownloaded: (callback: UpdateCallback): UnsubscribeFunction => {
    const subscription = (_event: IpcRendererEvent, info: UpdateInfo): void => {
      callback(info);
    };
    ipcRenderer.on('update-downloaded', subscription);
    return (): void => {
      ipcRenderer.removeListener('update-downloaded', subscription);
    };
  },

  /**
   * Install a downloaded update and restart the app
   */
  installUpdate: (): void => {
    ipcRenderer.send('install-update');
  }
};

// Expose the API to the renderer process
contextBridge.exposeInMainWorld('electronAPI', electronAPI);
