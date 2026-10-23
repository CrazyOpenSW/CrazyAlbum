<?php
/**
 * Advanced Media Explorer & Album
 * Features: Natural Sorting, Date Grouping (Google Photos style), Manual/Auto Slideshow, Filmstrip Navigation
 */

// --- CONFIGURATION ---
$base_album_dir = 'album';
$thumbs_dir = 'thumbs';
$ffmpeg_path = 'ffmpeg/bin/ffmpeg.exe';

if (!file_exists($thumbs_dir)) {
    mkdir($thumbs_dir, 0777, true);
}

$img_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$vid_exts = ['mp4', 'mkv', 'mov', 'avi', 'wmv'];

$current_rel_path = isset($_GET['dir']) ? $_GET['dir'] : '';
$full_scan_path = $base_album_dir . ($current_rel_path ? DIRECTORY_SEPARATOR . $current_rel_path : '');

if (!is_dir($full_scan_path)) {
    $current_rel_path = '';
    $full_scan_path = $base_album_dir;
}

function generateVideoThumb($video_path, $thumb_path, $ffmpeg_path) {
    if (!file_exists($ffmpeg_path)) {
        file_put_contents('ffmpeg_error.log', date('[Y-m-d H:i:s] ') . "FFmpeg NOT FOUND at $ffmpeg_path" . PHP_EOL, FILE_APPEND);
        return false;
    }
    $cmd = sprintf('"%s" -ss 00:00:01 -i "%s" -vf "scale=300:-1" -vframes 1 "%s" 2>&1', $ffmpeg_path, $video_path, $thumb_path);
    exec($cmd, $output, $return_var);
    if ($return_var !== 0) {
        file_put_contents('ffmpeg_error.log', date('[Y-m-d H:i:s] ') . "Error generating thumb for $video_path: " . implode(' ', $output) . PHP_EOL, FILE_APPEND);
        return false;
    }
    return file_exists($thumb_path);
}

function getFolderThumbnail($dir_path, $img_exts, $vid_exts, $ffmpeg_path, $thumbs_dir) {
    $items = scandir($dir_path);
    $first_media = null;

    // 1. Try to find media in the current directory
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir_path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) continue;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, $img_exts) || in_array($ext, $vid_exts)) {
            $first_media = ['path' => $path, 'type' => in_array($ext, $img_exts) ? 'image' : 'video'];
            break;
        }
    }

    // 2. If no media found, recursively search subdirectories
    if (!$first_media) {
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            $path = $dir_path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $sub_thumb = getFolderThumbnail($path, $img_exts, $vid_exts, $ffmpeg_path, $thumbs_dir);
                if ($sub_thumb) {
                    // We found a thumbnail for a subdirectory.
                    // Since getFolderThumbnail returns the path to the JPG, we can use it.
                    // But for consistency in the cache, we'll create a specific thumb for this folder too.
                    $thumb_name = 'folder_' . md5($dir_path) . '.jpg';
                    $thumb_path = $thumbs_dir . DIRECTORY_SEPARATOR . $thumb_name;
                    if (!file_exists($thumb_path)) {
                        copy($sub_thumb, $thumb_path);
                    }
                    return $thumbs_dir . '/' . $thumb_name;
                }
            }
        }
    }

    if (!$first_media) return null;

    $thumb_name = 'folder_' . md5($dir_path) . '.jpg';
    $thumb_path = $thumbs_dir . DIRECTORY_SEPARATOR . $thumb_name;
    if (!file_exists($thumb_path)) {
        if ($first_media['type'] === 'image') {
            copy($first_media['path'], $thumb_path);
        } else {
            generateVideoThumb($first_media['path'], $thumb_path, $ffmpeg_path);
        }
    }
    return $thumbs_dir . '/' . $thumb_name;
}

// --- DATE EXTRACTION LOGIC ---
function extractDate($filename, $full_path) {
    // 1. Try to extract date from filename with pattern _YYYYMMDD_
    if (preg_match('/_(\d{4})(\d{2})(\d{2})_/', $filename, $matches)) {
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }

    // 2. Try to extract EXIF date (Acquisition Date) for images
    $ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'tif', 'tiff'])) {
        $exif = @exif_read_data($full_path);
        if ($exif && isset($exif['DateTimeOriginal'])) {
            $date = strtotime($exif['DateTimeOriginal']);
            if ($date) return date("Y-m-d", $date);
        }
        if ($exif && isset($exif['DateTime'])) {
            $date = strtotime($exif['DateTime']);
            if ($date) return date("Y-m-d", $date);
        }
    }

    // 3. Fallback to file creation date
    return date("Y-m-d", filectime($full_path));
}

$dirs = [];
$files = [];
$items = scandir($full_scan_path);

foreach ($items as $item) {
    if ($item == '.' || $item == '..') continue;
    $path = $full_scan_path . DIRECTORY_SEPARATOR . $item;
    if (is_dir($path)) {
        $dirs[] = [
            'name' => $item,
            'thumb' => getFolderThumbnail($path, $img_exts, $vid_exts, $ffmpeg_path, $thumbs_dir)
        ];
    } else {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, $img_exts) || in_array($ext, $vid_exts)) {
            $thumb_url = str_replace('\\', '/', $path);
            if (in_array($ext, $vid_exts)) {
                $thumb_name = 'thumb_' . md5($path) . '.jpg';
                $thumb_full_path = $thumbs_dir . DIRECTORY_SEPARATOR . $thumb_name;
                if (!file_exists($thumb_full_path)) {
                    generateVideoThumb($path, $thumb_full_path, $ffmpeg_path);
                }
                $thumb_url = $thumbs_dir . '/' . $thumb_name;
            }
            $files[] = [
                'name' => $item,
                'path' => $path,
                'type' => in_array($ext, $img_exts) ? 'image' : 'video',
                'web_path' => str_replace('\\', '/', $path),
                'thumb_path' => $thumb_url,
                'date' => extractDate($item, $path)
            ];
        }
    }
}

usort($dirs, function($a, $b) { return strnatcasecmp($a['name'], $b['name']); });

usort($files, function($a, $b) {
    $nameA = pathinfo($a['name'], PATHINFO_FILENAME);
    $nameB = pathinfo($b['name'], PATHINFO_FILENAME);
    return strnatcasecmp($nameA, $nameB);
});

$grouped_files = [];
foreach ($files as $file) {
    $grouped_files[$file['date']][] = $file;
}
krsort($grouped_files);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Explorer</title>
    <style>
        :root { --bg: #0f0f0f; --card: #1e1e1e; --text: #efefef; --accent: #3a86ff; }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); margin: 0; overflow-x: hidden; }
        header { padding: 20px; background: #000; display: flex; align-items: center; gap: 20px; sticky: top; z-index: 100; }
        .breadcrumbs { font-size: 14px; color: #aaa; }
        .breadcrumbs a { color: var(--accent); text-decoration: none; }
        .controls { margin-left: auto; display: flex; gap: 10px; }
        .btn { background: var(--accent); color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .btn-sec { background: #444; }
        .container { padding: 20px; }
        .folder-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .folder { aspect-ratio: 1/1; background: var(--card); border-radius: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; text-decoration: none; color: white; border: 1px solid #333; position: relative; overflow: hidden; }
        .folder:hover { background: #2a2a2a; transform: translateY(-5px); }
        .folder img { width: 100%; height: 100%; object-fit: cover; }
        .folder-name { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.7); padding: 5px; text-align: center; font-size: 13px; }
        .folder-default { font-size: 50px; margin-bottom: 10px; }
        .date-group { margin-bottom: 30px; }
        .date-header { font-size: 16px; color: #aaa; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        .date-header::after { content: ""; flex: 1; height: 1px; background: #333; }
        .media-row { display: flex; flex-wrap: wrap; gap: 10px; }
        .media-item { width: 160px; height: 160px; position: relative; border-radius: 8px; overflow: hidden; cursor: pointer; transition: 0.2s; border: 1px solid #333; }
        .media-item:hover { transform: scale(1.05); box-shadow: 0 0 15px var(--accent); }
        .media-item img { width: 100%; height: 100%; object-fit: cover; }
        .media-badge { position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); padding: 2px 6px; border-radius: 4px; font-size: 11px; }
        #viewer { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #000; z-index: 1000; flex-direction: column; }
        #viewer-main { flex: 1; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; width: 100%; height: 100%; }
        #display-area { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
        #viewer-main img, #viewer-main video { max-width: 100%; max-height: 100%; object-fit: contain; display: block; }
        .viewer-nav { position: absolute; top: 0; bottom: 0; width: 15%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 40px; opacity: 0; transition: 0.3s; color: white; }
        .viewer-nav:hover { opacity: 1; background: rgba(255,255,255,0.05); }
        .nav-left { left: 0; } .nav-right { right: 0; }
        .viewer-toolbar { position: absolute; top: 20px; right: 20px; display: flex; gap: 15px; z-index: 1010; }
        .filmstrip { height: 120px; background: rgba(0,0,0,0.8); display: flex; overflow-x: auto; padding: 10px; gap: 10px; align-items: center; border-top: 1px solid #333; }
        .filmstrip-item { height: 100px; aspect-ratio: 1/1; object-fit: cover; cursor: pointer; opacity: 0.6; transition: 0.2s; border: 2px solid transparent; }
        .filmstrip-item.active { opacity: 1; border-color: var(--accent); }
        .filmstrip-item:hover { opacity: 1; }

        /* Data overlay for viewer */
        .date-overlay {
            position: absolute; bottom: 20px; right: 20px;
            background: rgba(0,0,0,0.5); color: #fff;
            padding: 5px 12px; border-radius: 4px;
            font-size: 14px; pointer-events: none;
            z-index: 1005; backdrop-filter: blur(5px);
        }
    </style>
</head>
<body>

<header>
    <div class="breadcrumbs">
        <a href="index.php">Album</a>
        <?php
        $path_parts = explode(DIRECTORY_SEPARATOR, $current_rel_path);
        $accumulated = '';
        foreach ($path_parts as $part) {
            if (!$part) continue;
            $accumulated .= ($accumulated ? DIRECTORY_SEPARATOR : '') . $part;
            echo ' / <a href="?dir=' . urlencode($accumulated) . '">' . htmlspecialchars($part) . '</a>';
        }
        ?>
    </div>
    <div class="controls">
        <?php if (!empty($files)): ?>
            <button class="btn" onclick="startSlideshow()">▶ Avvia Presentazione</button>
            <button class="btn btn-sec" onclick="openViewer(0)">🖼 Vista Manuale</button>
        <?php endif; ?>
    </div>
</header>

<div class="container">
    <?php if (!empty($dirs)): ?>
        <div class="folder-grid">
            <?php foreach ($dirs as $dir): ?>
                <a href="?dir=<?php echo urlencode(($current_rel_path ? $current_rel_path . DIRECTORY_SEPARATOR : '') . $dir['name']); ?>" class="folder">
                    <?php if ($dir['thumb']): ?>
                        <img src="<?php echo $dir['thumb']; ?>">
                    <?php else: ?>
                        <span class="folder-default">📁</span>
                    <?php endif; ?>
                    <div class="folder-name"><?php echo htmlspecialchars($dir['name']); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($grouped_files)): ?>
        <?php foreach ($grouped_files as $date => $day_files): ?>
            <div class="date-group">
                <div class="date-header"><?php echo $date; ?></div>
                <div class="media-row">
                    <?php foreach ($day_files as $file): ?>
                        <?php
                            // Calculate global index for this file across all groups
                            $global_index = 0;
                            foreach ($grouped_files as $date_key => $files_in_group) {
                                foreach ($files_in_group as $f) {
                                    if ($f['path'] === $file['path']) break 2;
                                    $global_index++;
                                }
                            }
                        ?>
                        <div class="media-item" onclick="openViewer(<?php echo $global_index; ?>)">
                            <img src="<?php echo $file['thumb_path']; ?>" loading="lazy">
                            <?php if ($file['type'] === 'video'): ?><div class="media-badge">🎥 Video</div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div id="viewer">
    <div class="viewer-toolbar">
        <button class="btn btn-sec" onclick="closeViewer()">✕ Chiudi</button>
        <button class="btn" id="btn-play-pause" onclick="toggleAuto()">⏸ Pausa</button>
    </div>

    <div id="viewer-main">
        <div class="viewer-nav nav-left" onclick="prev()">❮</div>
        <div id="display-area"></div>
        <div class="viewer-nav nav-right" onclick="next()">❯</div>
        <div id="date-overlay" class="date-overlay"></div>
    </div>

    <div class="filmstrip" id="filmstrip"></div>
</div>

<script>
    const media = <?php echo json_encode(array_merge(...array_values($grouped_files))); ?>;
    let currentIndex = 0;
    let autoTimer = null;

    function openViewer(index) {
        if (media.length === 0) return;
        currentIndex = index;
        document.getElementById('viewer').style.display = 'flex';
        updateDisplay();
        buildFilmstrip();
    }

    function closeViewer() {
        document.getElementById('viewer').style.display = 'none';
        stopAuto();
    }

    function updateDisplay() {
        const item = media[currentIndex];
        const area = document.getElementById('display-area');
        const dateOverlay = document.getElementById('date-overlay');

        if (item.type === 'image') {
            area.innerHTML = `<img src="${item.web_path}">`;
        } else {
            area.innerHTML = `<video controls autoplay src="${item.web_path}"></video>`;
        }

        // Display the date in the overlay
        dateOverlay.innerText = item.date;

        document.querySelectorAll('.filmstrip-item').forEach((el, idx) => {
            el.classList.toggle('active', idx === currentIndex);
        });

        const activeThumb = document.querySelector('.filmstrip-item.active');
        if (activeThumb) activeThumb.scrollIntoView({ behavior: 'smooth', inline: 'center' });
    }

    function buildFilmstrip() {
        const strip = document.getElementById('filmstrip');
        strip.innerHTML = '';
        media.forEach((item, idx) => {
            const img = document.createElement('img');
            img.className = 'filmstrip-item' + (idx === currentIndex ? ' active' : '');
            img.src = item.thumb_path;
            img.onclick = () => { currentIndex = idx; updateDisplay(); };
            strip.appendChild(img);
        });
    }

    function next() {
        currentIndex = (currentIndex + 1) % media.length;
        updateDisplay();
    }

    function prev() {
        currentIndex = (currentIndex - 1 + media.length) % media.length;
        updateDisplay();
    }

    function startSlideshow() {
        openViewer(0);
        toggleAuto();
    }

    function toggleAuto() {
        const btn = document.getElementById('btn-play-pause');
        if (autoTimer) {
            stopAuto();
        } else {
            btn.innerText = '⏸ Pausa';
            autoTimer = setInterval(next, 5000);
        }
    }

    function stopAuto() {
        clearInterval(autoTimer);
        autoTimer = null;
        document.getElementById('btn-play-pause').innerText = '▶ Riproduci';
    }
</script>
</body>
</html>
