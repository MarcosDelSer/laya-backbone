# Android App Setup & Learnings (Teacher App)

This document captures the environment setup, build fixes, and learnings from getting the LAYA Teacher App (React Native 0.78) building and running on macOS with Android Studio and an emulator.

**Last updated:** 2026-02-15  
**App:** `teacher-app` (React Native 0.78, Hermes, Firebase)

---

## 1. Environment Setup

### 1.1 Prerequisites

- **Android Studio** – Installed (provides JDK and SDK manager).
- **Android SDK** – Can be installed via Android Studio’s SDK Manager, or:
  - `platform-tools` was copied from Downloads into `~/Library/Android/sdk/platform-tools`.
  - Command-line tools were installed via download and unzip into `~/Library/Android/sdk/cmdline-tools/latest`.

### 1.2 Java

- **Use Android Studio’s bundled JDK** (no separate Java 17 install required):
  - Path: `/Applications/Android Studio.app/Contents/jbr/Contents/Home`
  - Version: JDK 21 (compatible with React Native 0.78 and AGP 8.x).

### 1.3 SDK Components (sdkmanager)

From `ANDROID_HOME/cmdline-tools/latest/bin`:

```bash
# Accept licenses (required once)
yes | sdkmanager --licenses

# Install platform, build-tools, system image, emulator, NDK
sdkmanager "platforms;android-34" "build-tools;35.0.0" \
  "system-images;android-34;google_apis;arm64-v8a" "emulator" "ndk;26.1.10909125"
```

- **Note:** `sdkmanager` may print `test: : integer expression expected` (script bug with JDK 21); it still runs. Use `JAVA_HOME` pointing at the Android Studio JBR.

### 1.4 Environment Variables

Add to `~/.zshrc` (or equivalent):

```bash
# Android SDK
export ANDROID_HOME=$HOME/Library/Android/sdk
export JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home"
export PATH=$ANDROID_HOME/emulator:$ANDROID_HOME/platform-tools:$ANDROID_HOME/cmdline-tools/latest/bin:$PATH
```

Reload: `source ~/.zshrc`.

### 1.5 Emulator (AVD)

```bash
# Create AVD (Pixel 6, API 34, arm64 Google APIs)
avdmanager create avd -n LAYA_Test -k "system-images;android-34;google_apis;arm64-v8a" -d "pixel_6" --force

# Start (recommended: auto GPU; avoid host if you see QEMU hangs)
emulator -avd LAYA_Test -no-audio -no-boot-anim -gpu auto
```

- **GPU:** `-gpu auto` is most reliable. `-gpu host` can cause “QEMU2 CPU1 thread” hangs on some Macs. `-gpu swiftshader_indirect` works but is slow and can make `adb shell` time out.
- **Locks:** If you see “Another emulator instance is running”, kill QEMU/emulator processes and remove `~/.android/avd/LAYA_Test.avd/*.lock`, then start again.

### 1.6 Project local.properties

Ensure `teacher-app/android/local.properties` exists:

```properties
sdk.dir=/Users/Koldan/Library/Android/sdk
```

(Use your actual `ANDROID_HOME` path.)

---

## 2. Build Configuration (Gradle & React Native 0.78)

### 2.1 settings.gradle

- **Plugin resolution:** `pluginManagement { includeBuild("../node_modules/@react-native/gradle-plugin") }` makes `com.facebook.react.settings` available.
- **App-level plugin:** A **top-level** `includeBuild("../node_modules/@react-native/gradle-plugin")` (after `include ':app'`) is required so that `com.facebook.react` is found in `app/build.gradle`. Without it you get “Plugin with id 'com.facebook.react' not found”.
- **Configuration cache:** Disabled in `gradle.properties` (see below) because RN’s autolinking runs external processes during configuration.

Relevant snippet:

```groovy
pluginManagement {
    includeBuild("../node_modules/@react-native/gradle-plugin")
    repositories { google(); mavenCentral(); gradlePluginPortal(); }
}
plugins { id("com.facebook.react.settings") }
extensions.configure(com.facebook.react.ReactSettingsExtension) { ex ->
    ex.autolinkLibrariesFromCommand()
}
rootProject.name = 'TeacherApp'
include ':app'
includeBuild("../node_modules/@react-native/gradle-plugin")   // needed for app plugin
```

### 2.2 build.gradle (project)

- Add React Native Gradle plugin to **buildscript** classpath so the app can apply `com.facebook.react`:

```groovy
dependencies {
    classpath("com.android.tools.build:gradle:8.6.0")
    classpath("org.jetbrains.kotlin:kotlin-gradle-plugin:$kotlinVersion")
    classpath("com.facebook.react:react-native-gradle-plugin")  // resolved via includeBuild
    classpath("com.google.gms:google-services:4.4.2")
}
```

### 2.3 app/build.gradle

- Apply plugins unconditionally (no `if (reactNativeModulesExist)` around `com.facebook.react`).
- Remove the legacy autolinking line that points to a file that no longer exists in RN 0.78:

  **Remove:**
  ```groovy
  apply from: file("../../node_modules/@react-native-community/cli-platform-android/native_modules.gradle")
  applyNativeModulesAppBuildGradle(project)
  ```
  Autolinking is handled by the React Native Gradle plugin.

### 2.4 gradle.properties

- **Configuration cache:** Must be off for React Native autolinking:

```properties
org.gradle.configuration-cache=false
```

### 2.5 dependencyResolutionManagement (settings.gradle)

- Use `PREFER_PROJECT` so that the React Native plugin can add its repositories without conflicts:

```groovy
repositoriesMode.set(RepositoriesMode.PREFER_PROJECT)
```

---

## 3. Manifest & Native Fixes

### 3.1 Firebase manifest merger

Conflicts with `react-native-firebase_messaging` (e.g. `default_notification_channel_id`, `default_notification_color`) were resolved by adding `tools:replace` in `AndroidManifest.xml`:

- Declare the tools namespace: `xmlns:tools="http://schemas.android.com/tools"`.
- On the conflicting `<meta-data>` elements, add e.g. `tools:replace="android:value"` or `tools:replace="android:resource"` as suggested by the merger error.

### 3.2 SoLoader and merged native libraries (RN 0.78)

React Native 0.78 merges several JNI libs into `libreactnative.so`. The app was crashing with:

```text
UnsatisfiedLinkError: dlopen failed: library "libreact_featureflagsjni.so" not found
```

- **Fix:** Initialize SoLoader with the **merged SO mapping** in `MainApplication.kt`:

```kotlin
import com.facebook.react.soloader.OpenSourceMergedSoMapping
import com.facebook.soloader.SoLoader

override fun onCreate() {
    super.onCreate()
    SoLoader.init(this, OpenSourceMergedSoMapping)  // not SoLoader.init(this, false)
    // ...
}
```

- **Reason:** `OpenSourceMergedSoMapping` maps names like `react_featureflagsjni` to the merged `reactnative` SO, so loading “libreact_featureflagsjni.so” actually loads the correct symbols from `libreactnative.so`.

### 3.3 BootReceiver

The manifest declared a `BootReceiver` for `BOOT_COMPLETED`, but the class was missing, causing:

```text
ClassNotFoundException: Didn't find class "com.teacherapp.BootReceiver"
```

- **Fix:** Add `android/app/src/main/java/com/teacherapp/BootReceiver.kt` implementing `BroadcastReceiver` and handling `Intent.ACTION_BOOT_COMPLETED` (can be a no-op for now; later used to reschedule notifications).

---

## 4. Metro & CLI

### 4.1 @react-native-community/cli version

- `npx react-native start` failed with:  
  `Cannot read properties of undefined (reading 'handle')` in `connect/index.js`.
- **Cause:** `@react-native-community/cli@20.1.1` (and its `cli-server-api`) do not export `indexPageMiddleware` in the way the React Native 0.78 `community-cli-plugin` expects; the middleware is `undefined` and gets passed to `serverApp.use()`.
- **Fix:** Use a compatible CLI version so that `cli-server-api` exposes `indexPageMiddleware`:

```bash
cd teacher-app
npm install @react-native-community/cli@15.1.3 --save-dev
```

- After a clean `rm -rf node_modules package-lock.json && npm install`, ensure `@react-native-community/cli-server-api` is 15.x (e.g. 15.1.3), not 20.x.

### 4.2 Starting Metro

```bash
cd teacher-app
npm start
# or: npx react-native start --port 8081
```

Then in another terminal, with the emulator running:

```bash
adb reverse tcp:8081 tcp:8081
```

---

## 5. Running the App

### 5.1 Build APK (no device required)

```bash
cd teacher-app/android
./gradlew app:assembleDebug
```

Outputs:

- `app/build/outputs/apk/debug/app-arm64-v8a-debug.apk`
- `app/build/outputs/apk/debug/app-universal-debug.apk` (all ABIs; larger).

### 5.2 Install and launch (emulator already running)

```bash
export ANDROID_HOME=~/Library/Android/sdk
export PATH=$ANDROID_HOME/platform-tools:$PATH

adb install -r teacher-app/android/app/build/outputs/apk/debug/app-arm64-v8a-debug.apk
adb reverse tcp:8081 tcp:8081
adb shell am start -n com.laya.teacherapp/com.teacherapp.MainActivity
```

### 5.3 One-command run script

`teacher-app/run-android-emulator.sh` installs the debug APK and launches the app (emulator must be running). Make executable and run:

```bash
chmod +x teacher-app/run-android-emulator.sh
./teacher-app/run-android-emulator.sh
```

### 5.4 Full dev flow (Metro + run)

```bash
# Terminal 1: start emulator
emulator -avd LAYA_Test -no-audio -no-boot-anim -gpu auto

# Terminal 2: Metro
cd teacher-app && npm start

# Terminal 3: build, install, launch (or use run-android-emulator.sh)
cd teacher-app && npx react-native run-android
```

---

## 6. Troubleshooting

| Symptom | What to check / do |
|--------|---------------------|
| “Plugin with id 'com.facebook.react' not found” | Add top-level `includeBuild("../node_modules/@react-native/gradle-plugin")` in `settings.gradle` (after `include ':app'`). Ensure project `build.gradle` has `classpath("com.facebook.react:react-native-gradle-plugin")`. |
| “libreact_featureflagsjni.so not found” | Use `SoLoader.init(this, OpenSourceMergedSoMapping)` in `MainApplication.kt`. |
| “ClassNotFoundException: BootReceiver” | Add `BootReceiver.kt` in `com.teacherapp` and handle `ACTION_BOOT_COMPLETED`. |
| Manifest merger errors (Firebase meta-data) | Add `xmlns:tools` and `tools:replace` on the conflicting `<meta-data>` elements. |
| Metro: “Cannot read properties of undefined (reading 'handle')” | Pin `@react-native-community/cli@15.1.3` (and matching cli-server-api); avoid 20.x for RN 0.78. |
| “native_modules.gradle” does not exist | Remove `apply from: ... native_modules.gradle` and `applyNativeModulesAppBuildGradle`; RN 0.78 uses the Gradle plugin for autolinking. |
| Configuration cache / external process | Set `org.gradle.configuration-cache=false` in `gradle.properties`. |
| Emulator “Another instance running” | Kill all `qemu-system*`, `emulator`, `crashpad`, `netsimd`; remove `~/.android/avd/LAYA_Test.avd/*.lock`. |
| Emulator very slow or adb shell hangs | Prefer `-gpu auto`. Avoid `-gpu swiftshader_indirect` for daily use; use `-gpu host` only if it doesn’t hang. |
| Install from IDE/CLI times out | Install the arm64 APK only (`app-arm64-v8a-debug.apk`) for faster installs; use universal only when needed. |

---

## 7. File / Config Summary

| Item | Location / change |
|------|--------------------|
| Env vars | `~/.zshrc`: `ANDROID_HOME`, `JAVA_HOME`, `PATH` |
| SDK path | `~/Library/Android/sdk` |
| local.properties | `teacher-app/android/local.properties`: `sdk.dir=...` |
| settings.gradle | `includeBuild` in `pluginManagement` + top-level `includeBuild` after `include ':app'` |
| build.gradle (project) | `classpath("com.facebook.react:react-native-gradle-plugin")` |
| app/build.gradle | Unconditional `apply plugin: "com.facebook.react"`; remove `native_modules.gradle` |
| gradle.properties | `org.gradle.configuration-cache=false`; `repositoriesMode` → `PREFER_PROJECT` in settings |
| MainApplication.kt | `SoLoader.init(this, OpenSourceMergedSoMapping)` |
| BootReceiver.kt | New file: `com.teacherapp.BootReceiver` |
| AndroidManifest.xml | `xmlns:tools`; `tools:replace` on Firebase `<meta-data>` |
| package.json (teacher-app) | `@react-native-community/cli@15.1.3` in devDependencies |
| run script | `teacher-app/run-android-emulator.sh` |

---

## 8. References

- React Native 0.78 Gradle plugin: `node_modules/@react-native/gradle-plugin/` (settings-plugin, react-native-gradle-plugin).
- Merged SO mapping: `node_modules/react-native/ReactAndroid/src/main/java/com/facebook/react/soloader/OpenSourceMergedSoMapping.kt`.
- Emulator: `$ANDROID_HOME/emulator/emulator -avd LAYA_Test -no-audio -no-boot-anim -gpu auto`.
