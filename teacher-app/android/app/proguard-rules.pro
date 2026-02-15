# LAYA Teacher App - ProGuard Rules
#
# ProGuard configuration for React Native and Firebase integration.
# These rules ensure the release build works correctly with code shrinking
# and obfuscation enabled.

#---------------------------------
# React Native Core Rules
#---------------------------------

# Keep React Native classes that are accessed via reflection
-keep class com.facebook.react.** { *; }
-keep class com.facebook.hermes.** { *; }
-keep class com.facebook.jni.** { *; }

# Keep native methods
-keepclassmembers class * {
    @com.facebook.react.uimanager.annotations.ReactProp <methods>;
    @com.facebook.react.uimanager.annotations.ReactPropGroup <methods>;
}

# Keep React Native module registry
-keepclassmembers class * extends com.facebook.react.bridge.JavaScriptModule { *; }
-keepclassmembers class * extends com.facebook.react.bridge.NativeModule { *; }

-keepclassmembers,includedescriptorclasses class * { native <methods>; }
-keepclassmembers class *  { @com.facebook.react.uimanager.annotations.ReactProp <methods>; }
-keepclassmembers class *  { @com.facebook.react.uimanager.annotations.ReactPropGroup <methods>; }

# React Native Turbo Modules
-keep class com.facebook.react.turbomodule.** { *; }
-keepclassmembers class * extends com.facebook.react.turbomodule.core.interfaces.TurboModule { *; }

# Preserve JavaScript interface methods
-keepclassmembers class * {
    @com.facebook.proguard.annotations.DoNotStrip *;
    @com.facebook.proguard.annotations.KeepGettersAndSetters *;
}

-keep @com.facebook.proguard.annotations.DoNotStrip class *
-keepclassmembers class * {
    @com.facebook.proguard.annotations.DoNotStrip <fields>;
    @com.facebook.proguard.annotations.DoNotStrip <methods>;
}

-keepattributes *Annotation*

#---------------------------------
# Hermes JavaScript Engine Rules
#---------------------------------

# Hermes engine classes
-keep class com.facebook.hermes.unicode.** { *; }
-keep class com.facebook.hermes.intl.** { *; }

#---------------------------------
# Firebase Rules
#---------------------------------

# Firebase Core
-keep class com.google.firebase.** { *; }
-keep class com.google.android.gms.** { *; }

# Firebase Cloud Messaging
-keepclassmembers class com.google.firebase.messaging.FirebaseMessagingService {
    public void onMessageReceived(com.google.firebase.messaging.RemoteMessage);
    public void onNewToken(java.lang.String);
}

# Firebase Analytics
-keepclassmembers class com.google.firebase.analytics.FirebaseAnalytics {
    public void logEvent(java.lang.String, android.os.Bundle);
}

# Keep FirebaseInstanceId (legacy) if used
-keep class com.google.firebase.iid.** { *; }

# Google Play Services
-keep class com.google.android.gms.common.ConnectionResult { *; }
-keep class com.google.android.gms.common.GoogleApiAvailability { *; }

# Firebase Crashlytics (if added later)
-keepattributes SourceFile,LineNumberTable
-keep public class * extends java.lang.Exception

#---------------------------------
# React Native Firebase Rules
#---------------------------------

# @react-native-firebase/app
-keep class io.invertase.firebase.** { *; }
-dontwarn io.invertase.firebase.**

# @react-native-firebase/messaging
-keep class io.invertase.firebase.messaging.** { *; }

#---------------------------------
# React Native Image Picker Rules
#---------------------------------

# react-native-image-picker
-keep class com.imagepicker.** { *; }
-dontwarn com.imagepicker.**

#---------------------------------
# OkHttp Rules (used by React Native networking)
#---------------------------------

-dontwarn okhttp3.**
-dontwarn okio.**
-dontwarn javax.annotation.**
-dontwarn org.conscrypt.**

# Keep OkHttp platform specific code
-keepnames class okhttp3.internal.publicsuffix.PublicSuffixDatabase
-keepclassmembers class * extends com.squareup.okhttp.internal.io.RealConnection {
    *;
}

#---------------------------------
# Kotlin Rules
#---------------------------------

-dontwarn kotlin.**
-keep class kotlin.Metadata { *; }
-keepclassmembers class kotlin.Metadata {
    public <methods>;
}

# Kotlin Coroutines
-keepnames class kotlinx.coroutines.internal.MainDispatcherFactory {}
-keepnames class kotlinx.coroutines.CoroutineExceptionHandler {}
-keepclassmembernames class kotlinx.** {
    volatile <fields>;
}

#---------------------------------
# AndroidX Rules
#---------------------------------

-keep class androidx.** { *; }
-keep interface androidx.** { *; }
-dontwarn androidx.**

# Keep lifecycle components
-keep class androidx.lifecycle.** { *; }
-keep interface androidx.lifecycle.** { *; }

#---------------------------------
# General Android Rules
#---------------------------------

# Keep Parcelables
-keepclassmembers class * implements android.os.Parcelable {
    public static final android.os.Parcelable$Creator *;
}

# Keep Serializable classes
-keepclassmembers class * implements java.io.Serializable {
    static final long serialVersionUID;
    private static final java.io.ObjectStreamField[] serialPersistentFields;
    private void writeObject(java.io.ObjectOutputStream);
    private void readObject(java.io.ObjectInputStream);
    java.lang.Object writeReplace();
    java.lang.Object readResolve();
}

# Keep R classes
-keepclassmembers class **.R$* {
    public static <fields>;
}

# Keep native methods
-keepclasseswithmembernames class * {
    native <methods>;
}

# Keep enums
-keepclassmembers enum * {
    public static **[] values();
    public static ** valueOf(java.lang.String);
}

#---------------------------------
# JavaScript Core Rules (fallback when Hermes disabled)
#---------------------------------

-keep class org.webkit.** { *; }
-dontwarn org.webkit.**

#---------------------------------
# Debugging - Keep source file names and line numbers
#---------------------------------

-keepattributes SourceFile,LineNumberTable

# If you keep line numbers, uncomment this to hide original source file name
#-renamesourcefileattribute SourceFile

#---------------------------------
# Optimization Settings
#---------------------------------

# Don't optimize - React Native may have issues with aggressive optimization
-dontoptimize

# Allow access modification for better obfuscation (optional)
# -allowaccessmodification

# Remove logging in release builds
-assumenosideeffects class android.util.Log {
    public static int v(...);
    public static int d(...);
    public static int i(...);
    public static int w(...);
    public static int e(...);
}
