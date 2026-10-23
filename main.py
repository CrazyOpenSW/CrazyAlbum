import os
import subprocess
import sys
import socket
import webbrowser
import zipfile
from pathlib import Path

def get_free_port():
    """Finds a free port on the system."""
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.bind(('', 0))
        return s.getsockname()[1]

def ensure_ffmpeg_binaries():
    """
    Checks if FFmpeg binaries exist in website/ffmpeg/bin.
    If missing, attempts to extract them from zip files in website/ffmpeg or website/ffmpeg/bin.
    """
    if getattr(sys, 'frozen', False):
        base_dir = Path(sys.executable).parent
    else:
        base_dir = Path(os.path.dirname(os.path.abspath(__file__)))

    ffmpeg_base_dir = base_dir / "website" / "ffmpeg"
    bin_dir = ffmpeg_base_dir / "bin"
    required_files = ["ffmpeg.exe", "ffplay.exe", "ffprobe.exe"]

    if not bin_dir.exists():
        bin_dir.mkdir(parents=True, exist_ok=True)

    needs_extraction = False
    for exe in required_files:
        if not (bin_dir / exe).exists():
            needs_extraction = True
            break

    if needs_extraction:
        print("\n[FFmpeg] Verifying binaries...")
        for exe in required_files:
            exe_path = bin_dir / exe
            if not exe_path.exists():
                zip_name = exe.replace(".exe", ".zip")

                # CERCA IN DUE LUOGHI: cartella ffmpeg E cartella ffmpeg/bin
                possible_zip_paths = [
                    ffmpeg_base_dir / zip_name,
                    bin_dir / zip_name
                ]

                found_zip = None
                for path in possible_zip_paths:
                    if path.exists():
                        found_zip = path
                        break

                if found_zip:
                    try:
                        print(f"Extracting {found_zip.name}...")
                        with zipfile.ZipFile(found_zip, 'r') as zip_ref:
                            zip_ref.extractall(bin_dir)
                        if exe_path.exists():
                            print(f" [OK] {exe} restored.")
                    except Exception as e:
                        print(f" [ERROR] Could not extract {found_zip.name}: {e}")
                else:
                    print(f" [WARNING] Source {zip_name} not found in ffmpeg/ or ffmpeg/bin/")
        print("FFmpeg check complete.\n")
    else:
        print("[FFmpeg] All binaries present.\n")

def main():
    if getattr(sys, 'frozen', False):
        base_dir = Path(sys.executable).parent
    else:
        base_dir = Path(os.path.dirname(os.path.abspath(__file__)))
    website_dir = base_dir / "website"
    php_local_path = base_dir / "php" / "php.exe"

    # --- AUTO-RESTORE FFmpeg BINARIES ---
    try:
        ensure_ffmpeg_binaries()
    except Exception as e:
        print(f"Warning: FFmpeg auto-restore encountered an error: {e}")

    if not website_dir.exists():
        print(f"Error: The folder '{website_dir}' was not found.")
        input("Press Enter to exit...")
        sys.exit(1)

    php_exe = None
    if php_local_path.exists():
        php_exe = str(php_local_path)
        print(f"Using portable PHP: {php_exe}")
    else:
        try:
            subprocess.run(["php", "-v"], capture_output=True, check=True)
            php_exe = "php"
            print("Using system PHP installation.")
        except (subprocess.CalledProcessError, FileNotFoundError):
            print("==========================================================")
            print("❌ ERROR: PHP NOT FOUND")
            print("==========================================================")
            print("\nThis software is designed to be portable.")
            print("To fix this, please follow these steps:")
            print(f"1. Download PHP (Non Thread Safe) from windows.php.net")
            print(f"2. Create a folder named 'php' in: {base_dir}")
            print(f"3. Extract the PHP files into that folder so that")
            print(f"   {php_local_path} exists.")
            print("\n==========================================================")
            input("\nPress Enter to exit...")
            sys.exit(1)

    port = get_free_port()
    host = "127.0.0.1"
    url = f"http://{host}:{port}"

    print("\n========================================")
    print("   ServerPHPWithYou - Portable Server   ")
    print("========================================")
    print(f"Serving files from: {website_dir}")
    print(f"Server running at: {url}")
    print("Press Ctrl+C to stop the server.")
    print("========================================\n")

    webbrowser.open(f"{url}/website/index.php")

    try:
        # Use -d to dynamically set the extension_dir so it works on USB sticks (portable)
        ext_dir = str(base_dir / "php" / "ext")
        subprocess.run([
            php_exe,
            "-S", f"{host}:{port}",
            "-t", str(base_dir),
            "-d", f"extension_dir={ext_dir}",
            "-d", "extension=exif"
        ], check=True)
    except KeyboardInterrupt:
        print("\n\nServer stopped by user.")
    except Exception as e:
        print(f"\nAn unexpected error occurred: {e}")
        input("Press Enter to exit...")

if __name__ == "__main__":
    main()
