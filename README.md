# Block Content Protection for WordPress

A comprehensive content protection plugin for WordPress, designed to prevent content theft, screenshots, screen recording, and unauthorized use.

**Developed by:** Mohammad Babaei - [adschi.com](https://adschi.com)

---

## 🆕 New in Version 1.4.0

- **Mobile Screenshot Blocking**: Attempts to block screenshots on mobile devices (Android)
- **Video Screen Recording Protection**: When screen recording is detected, videos turn black
- **Enhanced Detection**: Multiple methods to detect and prevent screen capture
- **Custom Alerts**: Separate alert messages for screenshots and recording attempts

---

## Features

This plugin provides a robust set of features to protect your website's content:

### Basic Protection
-   **Disable Right-Click**: Prevents users from opening the context menu.
-   **Block Developer Tools**: Blocks access to browser developer tools (F12, Ctrl+Shift+I, etc.).
-   **Disable Copying**: Disables keyboard shortcuts (like Ctrl+C) and other methods of copying.
-   **Block Text Selection**: Prevents users from selecting text on your pages.
-   **Disable Image Dragging**: Makes images undraggable.
-   **Disable Video Download**: Removes download options from video players.

### Advanced Protection
-   **Disable Screenshot Shortcuts**: Blocks PrintScreen and macOS screenshot shortcuts (Cmd+Shift+3/4).
-   **Mobile Screenshot Block**: Attempts to prevent screenshots on mobile devices using multiple techniques.
-   **Video Screen Recording Block**: Detects screen recording and turns videos black to protect content.
-   **Enhanced Screen Protection**: Adds protective CSS layers and detection mechanisms.

### Customization
-   **IP Whitelist**: Exclude specific IP addresses from all protections.
-   **Page Exclusions**: Exclude specific posts or pages by ID.
-   **Custom Alert Messages**: Customize messages shown to users when they attempt restricted actions.

---

## How to Use

1.  Download the `block-content-protection` folder as a `.zip` file.
2.  Log in to your WordPress admin dashboard.
3.  Navigate to **Plugins > Add New**.
4.  Click **Upload Plugin** and select the downloaded `.zip` file.
5.  After installation, click **Activate**.
6.  Configure the settings by navigating to **Settings > Content Protection**.
7.  Enable the protection features you need and customize alert messages.
8.  Save your settings.

---

## Important Notes

### ⚠️ Technical Limitations

**Please understand these important points:**

1. **No Protection is 100% Foolproof**: 
   - Users can take photos of the screen with another device
   - Advanced users can use external screen capture tools
   - Some browsers may not support all protection methods

2. **Mobile Screenshot Protection**:
   - Works better on Android devices
   - iOS has limited support for screenshot blocking
   - Some Android versions may bypass these restrictions

3. **Video Recording Protection**:
   - Detects common recording methods
   - Cannot prevent all hardware-based recording
   - May affect user experience

4. **Best Practices**:
   - Use multiple protection layers together
   - Don't rely solely on technical measures
   - Consider watermarking sensitive content
   - Use proper copyright notices

### 🎯 Recommended Settings

For maximum protection, enable:
- ✅ Disable Right Click
- ✅ Disable Developer Tools
- ✅ Disable Copy
- ✅ Disable Text Selection
- ✅ Disable Screenshot Shortcuts
- ✅ Mobile Screenshot Block
- ✅ Video Screen Recording Block
- ✅ Enhanced Screen Protection

---

## How It Works

### Screenshot Protection
1. **Keyboard Blocking**: Intercepts PrintScreen and screenshot shortcuts
2. **Mobile Detection**: Monitors touch gestures and visibility changes
3. **Blur Effect**: Applies blur when screen capture is suspected
4. **Alert System**: Warns users that screenshots are disabled

### Video Protection
1. **Recording Detection**: Monitors for screen recording APIs
2. **Black Screen**: Applies filter to turn videos black when recording detected
3. **Continuous Monitoring**: Checks for recording throughout playback
4. **Multiple Layers**: Uses CSS filters and JavaScript detection

### Additional Security
- **DevTools Detection**: Monitors browser size changes to detect DevTools
- **Iframe Protection**: Prevents embedding in other sites
- **Print Blocking**: Disables printing via CSS
- **Clipboard Clearing**: Attempts to clear clipboard after screenshot attempts

---

## File Structure

```
block-content-protection/
├── block-content-protection.php  (Main plugin file)
├── css/
│   └── protect.css              (Protection styles)
├── js/
│   └── protect.js               (Protection scripts)
└── languages/
    └── block-content-protection.pot
```

---

## Compatibility

- **WordPress**: 5.0 or higher
- **PHP**: 7.0 or higher
- **Browsers**: Chrome, Firefox, Safari, Edge (latest versions)
- **Mobile**: Android 5.0+, iOS 13+ (limited support)

---

## Troubleshooting

### Videos turn black even without recording
- This can happen due to browser privacy settings
- Try disabling "Video Screen Recording Block" temporarily
- Check if you're on the whitelist

### Mobile screenshots still work
- iOS has very limited screenshot blocking capabilities
- Some Android devices bypass these restrictions
- Consider adding visible watermarks as additional protection

### Performance Issues
- Disable "Enhanced Screen Protection" if site feels slow
- The plugin uses real-time monitoring which may impact performance
- Consider excluding high-traffic pages

---

## Support & Updates

For support, bug reports, or feature requests:
- Visit: [adschi.com](https://adschi.com)
- Version: 1.4.0
- Last Updated: 2025

---

## License

This plugin is licensed under the MIT License. See LICENSE file for details.

Copyright (c) 2025 Mohammad Babaei

---
---

# پلاگین محافظت از محتوا برای وردپرس

یک پلاگین جامع برای محافظت از محتوای وب‌سایت‌های وردپرسی، طراحی‌شده برای جلوگیری از سرقت محتوا، اسکرین‌شات، ضبط صفحه و استفاده غیرمجاز.

**توسعه‌دهنده:** محمد بابایی - [adschi.com](https://adschi.com)

---

## 🆕 جدید در نسخه 1.4.0

- **مسدود کردن اسکرین‌شات موبایل**: تلاش برای جلوگیری از اسکرین‌شات در دستگاه‌های موبایل (اندروید)
- **محافظت ویدئو از ضبط صفحه**: هنگام تشخیص ضبط صفحه، ویدئوها سیاه می‌شوند
- **تشخیص پیشرفته**: روش‌های متعدد برای شناسایی و جلوگیری از ضبط صفحه
- **هشدارهای سفارشی**: پیام‌های جداگانه برای اسکرین‌شات و تلاش برای ضبط

---

## ویژگی‌ها

این پلاگین مجموعه‌ای از قابلیت‌های قدرتمند را برای حفاظت از محتوای وب‌سایت شما فراهم می‌کند:

### محافظت پایه
-   **غیرفعال کردن راست کلیک**: جلوگیری از باز شدن منوی راست کلیک
-   **مسدود کردن ابزارهای توسعه‌دهنده**: مسدود کردن دسترسی به ابزارهای توسعه‌دهنده مرورگر
-   **جلوگیری از کپی کردن**: غیرفعال کردن کلیدهای میانبر کپی (مانند Ctrl+C)
-   **جلوگیری از انتخاب متن**: غیرفعال کردن قابلیت انتخاب متن
-   **غیرفعال کردن کشیدن تصویر**: جلوگیری از کشیدن تصاویر
-   **غیرفعال کردن دانلود ویدئو**: حذف گزینه دانلود از پخش‌کننده‌های ویدئو

### محافظت پیشرفته
-   **مسدود کردن کلیدهای اسکرین‌شات**: مسدود کردن PrintScreen و میانبرهای اسکرین‌شات مک
-   **مسدود کردن اسکرین‌شات موبایل**: تلاش برای جلوگیری از اسکرین‌شات با روش‌های متعدد
-   **محافظت ویدئو از ضبط صفحه**: تشخیص ضبط صفحه و سیاه کردن ویدئوها
-   **محافظت پیشرفته صفحه**: افزودن لایه‌های محافظ CSS و مکانیزم‌های تشخیص

### سفارشی‌سازی
-   **لیست سفید IP**: حذف آدرس‌های IP خاص از تمام محافظت‌ها
-   **حذف صفحات**: حذف پست‌ها یا صفحات خاص با شناسه
-   **پیام‌های سفارشی**: شخصی‌سازی پیام‌های نمایش داده شده به کاربران

---

## نحوه استفاده

۱. پوشه `block-content-protection` را به صورت فایل `.zip` دانلود کنید
۲. وارد پنل مدیریت وردپرس خود شوید
۳. به بخش **افزونه‌ها > افزودن** بروید
۴. روی **بارگذاری افزونه** کلیک کرده و فایل `.zip` را انتخاب کنید
۵. پس از نصب، روی **فعال کردن** کلیک کنید
۶. به بخش **تنظیمات > Content Protection** بروید
۷. قابلیت‌های مورد نظر را فعال کرده و پیام‌های هشدار را سفارشی کنید
۸. تنظیمات را ذخیره کنید

---

## نکات مهم

### ⚠️ محدودیت‌های فنی

**لطفاً این نکات مهم را درک کنید:**

1. **هیچ محافظتی ۱۰۰٪ قطعی نیست**:
   - کاربران می‌توانند با دستگاه دیگری از صفحه عکس بگیرند
   - کاربران پیشرفته می‌توانند از ابزارهای خارجی استفاده کنند
   - برخی مرورگرها ممکن است از همه روش‌های محافظتی پشتیبانی نکنند

2. **محافظت اسکرین‌شات موبایل**:
   - در دستگاه‌های اندروید بهتر کار می‌کند
   - iOS پشتیبانی محدودی دارد
   - برخی نسخه‌های اندروید ممکن است این محدودیت‌ها را دور بزنند

3. **محافظت ضبط ویدئو**:
   - روش‌های رایج ضبط را تشخیص می‌دهد
   - نمی‌تواند از تمام روش‌های سخت‌افزاری جلوگیری کند
   - ممکن است بر تجربه کاربری تأثیر بگذارد

4. **بهترین روش‌ها**:
   - از چند لایه محافظتی با هم استفاده کنید
   - فقط به اقدامات فنی تکیه نکنید
   - واترمارک روی محتوای حساس اضافه کنید
   - از اعلان‌های کپی‌رایت مناسب استفاده کنید

### 🎯 تنظیمات پیشنهادی

برای حداکثر محافظت، فعال کنید:
- ✅ غیرفعال کردن راست کلیک
- ✅ غیرفعال کردن ابزارهای توسعه‌دهنده
- ✅ غیرفعال کردن کپی
- ✅ غیرفعال کردن انتخاب متن
- ✅ غیرفعال کردن میانبرهای اسکرین‌شات
- ✅ مسدود کردن اسکرین‌شات موبایل
- ✅ محافظت ویدئو از ضبط صفحه
- ✅ محافظت پیشرفته صفحه

---

## نحوه کار

### محافظت اسکرین‌شات
1. **مسدود کردن صفحه‌کلید**: رهگیری PrintScreen و میانبرهای اسکرین‌شات
2. **تشخیص موبایل**: نظارت بر حرکات لمسی و تغییرات دید
3. **افکت بلور**: اعمال بلور هنگام شک به ضبط صفحه
4. **سیستم هشدار**: هشدار به کاربران که اسکرین‌شات غیرفعال است

### محافظت ویدئو
1. **تشخیص ضبط**: نظارت بر APIهای ضبط صفحه
2. **صفحه سیاه**: اعمال فیلتر برای سیاه کردن ویدئوها هنگام تشخیص ضبط
3. **نظارت مداوم**: بررسی ضبط در طول پخش
4. **لایه‌های متعدد**: استفاده از فیلترهای CSS و تشخیص JavaScript

### امنیت اضافی
- **تشخیص DevTools**: نظارت بر تغییرات اندازه مرورگر
- **محافظت Iframe**: جلوگیری از جاسازی در سایت‌های دیگر
- **مسدود کردن پرینت**: غیرفعال کردن چاپ از طریق CSS
- **پاک کردن کلیپ‌بورد**: تلاش برای پاک کردن کلیپ‌بورد پس از تلاش برای اسکرین‌شات

---

## پشتیبانی و به‌روزرسانی

برای پشتیبانی، گزارش باگ یا درخواست ویژگی:
- وب‌سایت: [adschi.com](https://adschi.com)
- نسخه: 1.4.0
- آخرین به‌روزرسانی: ۲۰۲۵

---

## مجوز

این پلاگین تحت مجوز MIT منتشر شده است.

Copyright (c) 2025 Mohammad Babaei
