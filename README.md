# 🖼️ CrazyAlbum - Media Explorer

CrazyAlbum is a portable SW, self-hosted media gallery designed to organize and view photos and videos automatically. It features natural sorting, date-based grouping (similar to Google Photos), and automatic thumbnail generation for both images and videos.

## 🌟 Key Advantage: Fully Portable!
The project includes a compiled version: **`CrazyAlbum_v1.0.exe`**. 
This means you can put the entire project folder on a **USB flash drive** and run your photo album on **any Windows PC** without installing Python, PHP, or any other dependencies. Just plug in the drive, run the `.exe`, and your gallery is ready!

---

## 🚀 Features

- **Automatic Organization**: Scans your folders and groups media by date.
- **Smart Sorting**: Uses natural case-insensitive sorting (e.g., `image2.jpg` comes before `image10.jpg`).
- **Video Support**: Full support for common video formats with automatic thumbnail extraction using FFmpeg.
- **Responsive Viewer**: Full-screen gallery with manual navigation, automatic slideshow, and a filmstrip preview.
- **Breadcrumb Navigation**: Easy navigation through nested subfolders.


---

## 📂 Project Structure

```text
CrazyAlbum/
├── php/                # ⚙️ PHP Runtime (Required to execute the gallery logic)
├── main.py              # The Python web server
└── website/             # The frontend application
    ├── index.php        # The main gallery logic and UI
    ├── album/           # 👈 PLACE YOUR PHOTOS AND VIDEOS HERE
    │   ├── vacation/
    │   │   └── photo1.jpg
    │   └── family/
    │       └── video1.mp4
    ├── thumbs/          # Automatically generated thumbnails (cached)
    └── ffmpeg/          # FFmpeg binaries for video thumbnailing
```

---

## 🛠️ How it Works

### 1. `main.py` (The Engine)
Since PHP cannot run on its own, `main.py` acts as the bridge. It starts a local web server that serves the files inside the `website` folder and executes the PHP code, allowing you to access the gallery via your web browser (usually at `http://localhost:8000`).

### 2. `website/index.php` (The Gallery)
This is the heart of the application. It performs the following steps:
- **Scanning**: It recursively scans the `album/` directory for supported image and video formats.
- **Date Extraction**: It tries to find dates in the filenames (YYYYMMDD) or falls back to the file's creation date.
- **Thumbnailing**: 
    - For **images**, it creates a cached copy in `thumbs/`.
    - For **videos**, it uses **FFmpeg** to capture a frame at the 1-second mark, creating a `.jpg` preview so you can see what's in the video without opening it.
- **Grouping**: It sorts all media and groups them by date for a clean, chronological browsing experience.

### 3. The Album Experience
- **Navigation**: Use the breadcrumbs at the top to move between folders.
- **Viewer**: Click any thumbnail to open the full-screen viewer.
- **Filmstrip**: While in full-screen, a bottom filmstrip allows you to quickly jump between media.
- **Slideshow**: The "Avvia Presentazione" button starts an automatic slideshow of the current folder.

---

## 📖 Setup & Usage

### 1. Install Requirements
Ensure you have **Python 3** installed on your system.

### 2. Add Your Media
Place your photos and videos inside the `website/album/` folder. You can create as many subfolders as you like to keep your media organized.

### 3. Run the Server
Open a terminal in the root directory and run:
```bash
python main.py
```

### 4. Access the Gallery
Open your browser and go to:
`http://localhost:8000` (or the address shown in the terminal).

---

## ⚙️ Technical Details
- **Supported Images**: `.jpg`, `.jpeg`, `.png`, `.gif`, `.webp`
- **Supported Videos**: `.mp4`, `.mkv`, `.mov`, `.avi`, `.wmv`
- **Dependencies**: FFmpeg (included as a zip in `website/ffmpeg/` for Windows). 
**Important**: You must unzip the FFmpeg files into the `website/ffmpeg/bin/` folder for video thumbnails to work.
