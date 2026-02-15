/*
 * LAYA Parent App - MainActivity
 *
 * Main entry point activity for the React Native application.
 * Handles the initial launch and React Native lifecycle integration.
 */
package com.parentapp

import android.os.Bundle
import com.facebook.react.ReactActivity
import com.facebook.react.ReactActivityDelegate
import com.facebook.react.defaults.DefaultNewArchitectureEntryPoint.fabricEnabled
import com.facebook.react.defaults.DefaultReactActivityDelegate

/**
 * MainActivity is the entry point for the LAYA Parent Android app.
 *
 * This activity extends ReactActivity which provides the integration between
 * Android's activity lifecycle and React Native's JavaScript runtime.
 *
 * Key responsibilities:
 * - Initialize the React Native JavaScript runtime
 * - Handle deep linking from the laya-parent:// scheme
 * - Manage the React Native bundle loading
 * - Handle configuration changes (orientation, keyboard, etc.)
 */
class MainActivity : ReactActivity() {

    /**
     * Returns the name of the main component registered from JavaScript.
     * This is used to schedule rendering of the component.
     *
     * The component name must match the one registered in index.js using
     * AppRegistry.registerComponent('ParentApp', ...)
     */
    override fun getMainComponentName(): String = "ParentApp"

    /**
     * Returns the instance of the [ReactActivityDelegate].
     *
     * We use [DefaultReactActivityDelegate] which provides the default
     * implementation including support for Fabric (the new rendering system)
     * when enabled via BuildConfig.IS_NEW_ARCHITECTURE_ENABLED.
     *
     * @return The ReactActivityDelegate for this activity
     */
    override fun createReactActivityDelegate(): ReactActivityDelegate =
        DefaultReactActivityDelegate(this, mainComponentName, fabricEnabled)

    /**
     * Called when the activity is first created.
     *
     * This is where we perform any one-time initialization that needs
     * to happen before the React Native JavaScript code starts running.
     *
     * @param savedInstanceState If the activity is being re-initialized after
     *     previously being shut down, this contains the data it most recently
     *     supplied in onSaveInstanceState. Otherwise it is null.
     */
    override fun onCreate(savedInstanceState: Bundle?) {
        // Call the parent onCreate with null to avoid issues with
        // React Native's state restoration which can conflict with
        // Android's state restoration mechanism
        super.onCreate(null)
    }
}
