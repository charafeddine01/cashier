# OmniPOS Pro - Android APK & iOS Mobile App Build Guide
**Transform the Web POS into Native Android & iOS Applications via Capacitor.js**

---

## 1. Prerequisites
- Node.js 18+ & npm
- **For Android:** Android Studio & Android SDK (API level 24+)
- **For iOS:** macOS with Xcode 15+ and CocoaPods

---

## 2. Add Native Mobile Platforms

Run the following commands in the `omnipos-pro` project root:

```bash
# 1. Install Capacitor CLI & Core libraries
npm install @capacitor/core @capacitor/cli @capacitor/android @capacitor/ios

# 2. Build the optimized static assets into dist/
npm run build

# 3. Initialize Android platform
npx cap add android

# 4. Initialize iOS platform
npx cap add ios

# 5. Sync web assets and plugins to native projects
npx cap sync
```

---

## 3. Building Android APK / AAB (Google Play)

### Open in Android Studio
```bash
npx cap open android
```

### Steps in Android Studio:
1. Wait for Gradle sync to complete.
2. Under `AndroidManifest.xml`, the following hardware permissions are enabled:
   - `android.permission.INTERNET`
   - `android.permission.BLUETOOTH` & `android.permission.BLUETOOTH_CONNECT` (For Bluetooth receipt printers and barcode scanners)
   - `android.permission.CAMERA` (For camera barcode scanner)
3. To generate a debug APK:
   - Menu: **Build** > **Build Bundle(s) / APK(s)** > **Build APK(s)**.
   - APK output location: `android/app/build/outputs/apk/debug/app-debug.apk`.
4. To generate a signed release APK / AAB:
   - Menu: **Build** > **Generate Signed Bundle / APK...**
   - Follow keystore signing prompts to generate `app-release.aab` for Google Play Store upload.

---

## 4. Building iOS IPA (Apple App Store)

### Open in Xcode
```bash
npx cap open ios
```

### Steps in Xcode:
1. Select your Development Team under **Signing & Capabilities**.
2. Select your target device or generic iOS Device.
3. Menu: **Product** > **Archive**.
4. Once archiving is complete, open the **Organizer** window and click **Distribute App** to upload to App Store Connect or export as an enterprise `.ipa`.

---

## 5. Offline & Native Hardware Capabilities in Mobile
- **Offline Sync:** Uses the built-in IndexedDB outbox. If mobile loses 4G/5G connection in a retail vehicle or food truck, sales are saved locally and synced automatically once connection returns.
- **Thermal Printing:** Supports Bluetooth ESC/POS printers and network receipt printers over port 9100.
- **Barcode Scanning:** Supports HID Bluetooth barcode guns as standard keyboard input, as well as the device camera scanner.
