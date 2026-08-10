# Topdata Error Monitor SW6

![Plugin Icon](src/Resources/config/plugin.png)

This plugin intercept unhandled PHP exceptions and errors on your production environment, saving them to a custom, lightweight, indexed table (`tdem_error_log`). It groups duplicate exceptions automatically via a signature-based hashing algorithm.

## Features
- **Real-Time Database Tracking**: Groups identical occurrences automatically to save database storage.
- **Spike Notification Alerting**: Instantly emails administrators when a threshold of errors is logged within a configurable time-frame (e.g., 50 errors within 15 minutes). Includes a safety cooldown limit to prevent inbox floods.
- **Daily Summary Digests**: Generates and emails a compiled, clean HTML summary table of the past 24 hours' unhandled errors via a standard Scheduled Task.
- **Self-Cleaning Routine**: Keeps things light by purging error log records older than 30 days automatically.

## Requirements
- Shopware `6.7.*`
- PHP `8.2` or higher

## Configuration
Go to your **Shopware Administration > Extensions > My Extensions > Topdata Error Monitor SW6 > Configuration** to configure:
1. **Notification Email**: Target address for daily digests and warning alerts.
2. **Spike Alerts Toggle**: Toggle immediate spike warnings.
3. **Spike Window Thresholds**: Customize warning frequency limits and evaluation intervals.
4. **Alert Cooldown**: Minimize alert fatigue by limiting back-to-back notification emails.

## Testing Your Setup
You can test the monitoring and alerting mechanisms directly from the terminal:

- **Log a Test Exception:**
  ```bash
  bin/console topdata:error-monitor:test --throw
  ```
- **Trigger a Test Report Dispatch:**
  ```bash
  bin/console topdata:error-monitor:test --send-summary
  ```

## License
MIT