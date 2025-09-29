# blockcontentprotection
Prestashop 1.7/8.1 copy guard - disable : image - video - right click - f12 - right click

# protect.js

A lightweight JavaScript protection script to prevent content copying, right-click, dev tools access, screenshot, and video downloading on your website.

## Features

✅ The script provides the following protective features:

- Disable right-click context menu  
- Block browser developer tools (F12, Ctrl+Shift+I, etc.)  
- Prevent screenshots via PrintScreen key  
- Disable video downloading options  
- Make images and videos undraggable  
- Clear text selection after double-click  
- Block copying via keyboard shortcuts or context menu

## Usage

1. Add the `protect.js` file to your project directory.
2. Include it in your HTML file
 BCP_DISABLE_RIGHTCLICK = true;
 BCP_DISABLE_DEVTOOLS = true;
 BCP_DISABLE_SCREENSHOT = true;
 BCP_DISABLE_VIDEO_DOWNLOAD = true;
 BCP_DISABLE_image_DOWNLOAD = true;



# protect.js

یک اسکریپت محافظتی ساده برای جلوگیری از کپی، راست کلیک، ابزارهای توسعه‌دهنده و دانلود ویدیو در صفحات وب.

## ویژگی‌ها

🔒 این اسکریپت قابلیت‌های امنیتی زیر را فراهم می‌کند:

- جلوگیری از راست کلیک (context menu)
- جلوگیری از استفاده از ابزارهای توسعه (مانند F12، Ctrl+Shift+I و ...)
- جلوگیری از اسکرین‌شات با کلید PrintScreen
- غیرفعال کردن گزینه دانلود ویدیو
- غیر قابل درگ بودن تصاویر و ویدیوها
- جلوگیری از انتخاب متن پس از دوبار کلیک
- جلوگیری از کپی کردن محتوا با Ctrl+C یا راست کلیک

## نحوه استفاده

فایل zip  دانلود و از طریق پرستاشاپ نصب کنید و جاهای که دوست دارید غیر فعال کنید روی حالت فعال قرار بدید


```html
<script src="js/protect.js"></script>
