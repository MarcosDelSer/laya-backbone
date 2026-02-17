/*
 * LAYA Teacher App - BootReceiver
 *
 * Receives BOOT_COMPLETED to reschedule notifications after device reboot.
 */
package com.teacherapp

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent?) {
        if (intent?.action != Intent.ACTION_BOOT_COMPLETED) return
        // Reschedule notifications / alarms here if needed after reboot
    }
}
