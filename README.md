# FileHub Explorer

A modern, fast, and lightweight self-hosted web file explorer and local network file manager. Built with pure PHP, vanilla JavaScript, and modern CSS with zero external frameworks or heavy dependencies.

![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)
![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![No Dependencies](https://img.shields.io/badge/dependencies-zero-brightgreen)

---

## ✨ Features

- **🚀 Zero Dependencies & Plug-and-Play**:
  - No `node_modules`, no Composer, no build steps. Just drop it into any PHP-capable web server and it runs immediately.
- **🎨 Modern Responsive UI**:
  - Dark Mode and Light Mode with system preference detection and manual toggle.
  - Switchable **Grid View** and **List View**.
  - Fully responsive across desktop, tablet, and mobile screens (tested down to small 390px/440px viewports).
- **📂 File Exploration & Navigation**:
  - Interactive breadcrumb navigation.
  - Sorting by Name (A-Z, Z-A), File Size, and Date Modified.
  - Instant live filter chips by file categories: Folders, PDFs, Images, Archives (ZIP/TAR), Text/Code, Audio, and Video.
  - Quick keyboard search (press `/` to search anytime).
- **📤 Resilient Chunked File Uploads**:
  - Drag-and-drop or file picker upload support for files up to 50 MB (configurable).
  - Slice-based chunked uploading handles large files smoothly without hitting web server memory limits or script timeouts.
- **👁️ In-Browser Preview Modal**:
  - Interactive file previews for PDFs, Images (JPEG, PNG, WebP, GIF, SVG), Audio, Video, and Text/Code files.
  - Direct download actions for all file formats.
- **📋 Shared Network Clipboard**:
  - Built-in shared text clipboard to copy and paste links, notes, or code snippets across different devices on your local Wi-Fi / LAN network.
- **🔒 Privacy & Git Safety**:
  - Pre-configured `.gitignore` ensures your files inside `resource/` and shared clipboard data are never pushed to version control.

---

## 📋 Requirements

- **PHP**: 8.0 or higher
- **PHP Extensions**: `fileinfo`, `json` (both standard in modern PHP distributions)
- **Web Server**: Any web server (Apache, Nginx, Caddy, or PHP's built-in CLI server)

---

## 🚀 Quick Start

### Option 1: PHP Built-in Server (Easiest)

1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-username/filehub-explorer.git
   cd filehub-explorer
   ```

2. **Start the local server:**
   ```bash
   # Listen on all network interfaces to access from phone or laptop on same Wi-Fi
   php -S 0.0.0.0:8000
   ```

3. **Open in browser:**
   - On your computer: `http://localhost:8000`
   - From other devices on your Wi-Fi: `http://<your-computer-ip>:8000`

---

### Option 2: Apache or Nginx

1. Clone or copy the folder into your web root directory (e.g. `/var/www/html/filehub` or `htdocs/filehub`).
2. Ensure the web server user (`www-data`, `_www`, or `apache`) has read and write permissions on the `resource/` directory:
   ```bash
   chmod 755 resource
   ```
3. Open `http://localhost/filehub` in your web browser.

---

## 📁 Project Structure

```text
├── index.php             # Core PHP backend (routing, file scanning, chunk upload handler)
├── app.js                # Frontend client app (previews, search, chunked uploader, clipboard)
├── style.css             # Modern styling & design system with Dark/Light mode
├── resource/             # Storage folder where files and folders are saved (git-ignored)
│   └── .gitkeep          # Keeps the directory structure intact in git
├── .clipboard_data.json  # Local network clipboard storage file (git-ignored)
├── .user.ini             # Custom PHP upload size configuration
├── .gitignore            # Protects private files and sensitive data
├── LICENSE               # MIT License
└── README.md             # Project documentation
```

---

## ⚙️ Configuration & Customization

### Changing the Storage Directory
By default, files are stored in the `./resource` folder. You can configure this in `index.php`:
```php
define('RESOURCE_DIR', __DIR__ . '/resource');
```

### Adjusting Maximum Upload Size
The default maximum single file upload limit is set to 50 MB in `index.php`:
```php
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50 MB
```
If you increase this, ensure your PHP configuration (`php.ini` or `.user.ini`) has suitable limits:
```ini
upload_max_filesize = 64M
post_max_size = 64M
```

---

## 🛡️ Security Considerations

- **Path Traversal Protection**: Directory traversal attacks (e.g. `../`) are strictly blocked using `realpath()` path validation to guarantee access remains confined to the `resource/` directory.
- **Hidden File Filtering**: Files and folders prefixed with `.` (such as `.DS_Store` or `.git`) are hidden by default from web listings.
- **Safe Public Git Repository**: User files placed inside `resource/` and text saved in `.clipboard_data.json` are excluded via `.gitignore`.

---

## 📄 License

This project is open-source and licensed under the [MIT License](LICENSE).
