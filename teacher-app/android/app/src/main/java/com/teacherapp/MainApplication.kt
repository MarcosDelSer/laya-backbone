/*
 * LAYA Teacher App - MainApplication
 *
 * Application class for React Native integration.
 * Initializes React Native, native modules, and Firebase services.
 */
package com.teacherapp

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.res.Configuration
import android.os.Build
import com.facebook.react.PackageList
import com.facebook.react.ReactApplication
import com.facebook.react.ReactHost
import com.facebook.react.ReactNativeHost
import com.facebook.react.ReactPackage
import com.facebook.react.defaults.DefaultNewArchitectureEntryPoint.load
import com.facebook.react.defaults.DefaultReactHost.getDefaultReactHost
import com.facebook.react.defaults.DefaultReactNativeHost
import com.facebook.soloader.SoLoader

/**
 * MainApplication is the Application class for the LAYA Teacher app.
 *
 * This class initializes all the necessary native components for React Native
 * to work correctly, including:
 * - SoLoader for loading native libraries
 * - React Native host configuration
 * - Native module package registration (via autolinking)
 * - Firebase Cloud Messaging notification channels
 *
 * The class implements ReactApplication to provide the ReactNativeHost
 * to React Native components throughout the app.
 */
class MainApplication : Application(), ReactApplication {

    /**
     * ReactNativeHost configuration for the application.
     *
     * This host manages the React Native runtime lifecycle, including:
     * - JavaScript bundle loading
     * - Native module registration
     * - Development menu and debugging features
     * - Hermes JavaScript engine initialization
     */
    override val reactNativeHost: ReactNativeHost =
        object : DefaultReactNativeHost(this) {
            /**
             * Returns the list of React Native packages to register.
             *
             * Uses autolinking via PackageList to automatically include all
             * native modules that are properly configured in package.json
             * and build.gradle. This includes Firebase, camera, permissions,
             * and other native modules.
             *
             * @return List of ReactPackage instances
             */
            override fun getPackages(): List<ReactPackage> =
                PackageList(this).packages.apply {
                    // Packages that cannot be autolinked yet can be added manually here:
                    // add(MyReactNativePackage())
                }

            /**
             * Returns the name of the main JavaScript module entry point.
             *
             * This is the file that contains the AppRegistry.registerComponent call.
             * For most React Native apps, this is "index" (referring to index.js).
             *
             * @return The JS bundle entry point module name
             */
            override fun getJSMainModuleName(): String = "index"

            /**
             * Returns whether the app should use the Hermes JavaScript engine.
             *
             * Hermes is the recommended engine for React Native, providing:
             * - Faster app startup time
             * - Reduced memory usage
             * - Smaller app size
             * - Better performance overall
             *
             * @return true to use Hermes, false to use JSC
             */
            override val isHermesEnabled: Boolean
                get() = BuildConfig.IS_HERMES_ENABLED

            /**
             * Returns whether the New Architecture (Fabric + TurboModules) is enabled.
             *
             * The New Architecture provides:
             * - Fabric: New rendering system with concurrent features
             * - TurboModules: Lazy-loaded native modules for better startup time
             * - Codegen: Type-safe bridge between JS and native code
             *
             * @return true if new architecture is enabled
             */
            override val isNewArchEnabled: Boolean
                get() = BuildConfig.IS_NEW_ARCHITECTURE_ENABLED

            /**
             * Returns whether dev mode features should be enabled.
             *
             * When true, enables:
             * - Developer menu (shake to access)
             * - Live reloading
             * - Remote JS debugging
             * - Performance profiling
             *
             * This should be false for production builds.
             *
             * @return true if dev mode is enabled
             */
            override fun getUseDeveloperSupport(): Boolean = BuildConfig.DEBUG
        }

    /**
     * ReactHost for the new architecture.
     *
     * This is used when the New Architecture is enabled, providing
     * the React host implementation for Fabric and TurboModules.
     */
    override val reactHost: ReactHost
        get() = getDefaultReactHost(applicationContext, reactNativeHost)

    /**
     * Called when the application is first created.
     *
     * This is where we initialize all the native components needed
     * for React Native to work correctly.
     */
    override fun onCreate() {
        super.onCreate()

        // Initialize SoLoader, which is required for loading native libraries
        // used by React Native (Hermes, Yoga, etc.)
        SoLoader.init(this, false)

        // If the new architecture is enabled, load the native entry point
        if (BuildConfig.IS_NEW_ARCHITECTURE_ENABLED) {
            // Load the native entry point for the new architecture
            load()
        }

        // Create notification channels for Android 8.0+ (API 26+)
        createNotificationChannels()
    }

    /**
     * Called when the device configuration changes.
     *
     * This notifies React Native of configuration changes like
     * orientation, locale, or font scale changes.
     *
     * @param newConfig The new device configuration
     */
    override fun onConfigurationChanged(newConfig: Configuration) {
        super.onConfigurationChanged(newConfig)
        // Note: React Native handles configuration changes automatically
        // through ReactRootView. This override is here for any future
        // custom handling that may be needed.
    }

    /**
     * Creates notification channels for Firebase Cloud Messaging.
     *
     * Android 8.0+ (API 26+) requires notification channels for displaying
     * notifications. This method creates the channels used by the app:
     *
     * - General notifications: For daily reports, activity updates, etc.
     * - Incident alerts: High-priority channel for urgent notifications
     */
    private fun createNotificationChannels() {
        // Notification channels are only supported on Android 8.0+ (API 26+)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val notificationManager = getSystemService(NotificationManager::class.java)

            // General notification channel
            // Used for: daily updates, activity logs, meal notifications, etc.
            val generalChannel = NotificationChannel(
                CHANNEL_GENERAL,
                "LAYA Teacher Notifications",
                NotificationManager.IMPORTANCE_DEFAULT
            ).apply {
                description = "General notifications from LAYA Teacher app"
                enableVibration(true)
                setShowBadge(true)
            }

            // Incident alert channel with high priority
            // Used for: incident reports, urgent updates from parents/admin
            val incidentChannel = NotificationChannel(
                CHANNEL_INCIDENTS,
                "Incident Alerts",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Urgent notifications for incidents requiring immediate attention"
                enableVibration(true)
                enableLights(true)
                setShowBadge(true)
            }

            // Register the channels with the system
            notificationManager.createNotificationChannels(listOf(
                generalChannel,
                incidentChannel
            ))
        }
    }

    companion object {
        /**
         * Notification channel ID for general notifications.
         * This matches the ID configured in AndroidManifest.xml for FCM.
         */
        const val CHANNEL_GENERAL = "laya_teacher_notifications"

        /**
         * Notification channel ID for high-priority incident alerts.
         */
        const val CHANNEL_INCIDENTS = "laya_teacher_incidents"
    }
}
