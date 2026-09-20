#!/usr/bin/env python3
"""
replace_cdn.py
Automates downloading CDN assets (Bootstrap, jQuery, Leaflet, Fonts, etc.)
locally and replaces CDN links with local assets across the TestShare project.
Also fixes dynamic base URL handling in config.php so CSS loads correctly in XAMPP.
"""

import os
import re
import urllib.request
import urllib.error

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
CSS_DIR = os.path.join(BASE_DIR, "css")
JS_DIR = os.path.join(BASE_DIR, "js")
FONTS_DIR = os.path.join(BASE_DIR, "fonts")
VIEWS_DIR = os.path.join(BASE_DIR, "views")
PAGE_DIR = os.path.join(BASE_DIR, "page")
CONFIG_FILE = os.path.join(BASE_DIR, "config.php")

USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"

DOWNLOAD_LIST = [
    # jQuery (v3.7.1)
    {
        "url": "https://code.jquery.com/jquery-3.7.1.min.js",
        "path": os.path.join(JS_DIR, "jquery.min.js"),
        "name": "jQuery 3.7.1"
    },
    # Leaflet CSS & JS
    {
        "url": "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css",
        "path": os.path.join(CSS_DIR, "leaflet.css"),
        "name": "Leaflet CSS 1.9.4"
    },
    {
        "url": "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js",
        "path": os.path.join(JS_DIR, "leaflet.js"),
        "name": "Leaflet JS 1.9.4"
    },
    # Leaflet Images
    {
        "url": "https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png",
        "path": os.path.join(CSS_DIR, "images", "marker-icon.png"),
        "name": "Leaflet Marker Icon"
    },
    {
        "url": "https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png",
        "path": os.path.join(CSS_DIR, "images", "marker-icon-2x.png"),
        "name": "Leaflet Marker Icon 2x"
    },
    {
        "url": "https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png",
        "path": os.path.join(CSS_DIR, "images", "marker-shadow.png"),
        "name": "Leaflet Marker Shadow"
    },
    {
        "url": "https://unpkg.com/leaflet@1.9.4/dist/images/layers.png",
        "path": os.path.join(CSS_DIR, "images", "layers.png"),
        "name": "Leaflet Layers Icon"
    },
    {
        "url": "https://unpkg.com/leaflet@1.9.4/dist/images/layers-2x.png",
        "path": os.path.join(CSS_DIR, "images", "layers-2x.png"),
        "name": "Leaflet Layers Icon 2x"
    },
]

def download_file(url, target_path, name):
    os.makedirs(os.path.dirname(target_path), exist_ok=True)
    if os.path.exists(target_path) and os.path.getsize(target_path) > 0:
        print(f"[EXISTS] {name} -> {os.path.relpath(target_path, BASE_DIR)}")
        return True
    print(f"[DOWNLOADING] {name} from {url}...")
    try:
        req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
        with urllib.request.urlopen(req, timeout=30) as response, open(target_path, "wb") as out_file:
            out_file.write(response.read())
        print(f"[SUCCESS] Saved {name} -> {os.path.relpath(target_path, BASE_DIR)} ({os.path.getsize(target_path)} bytes)")
        return True
    except Exception as e:
        print(f"[ERROR] Failed to download {name}: {e}")
        return False

def download_all_cdns():
    print("\n--- 1. DOWNLOADING CDN ASSETS LOCALLY ---")
    for item in DOWNLOAD_LIST:
        download_file(item["url"], item["path"], item["name"])

def fix_config_php():
    print("\n--- 2. UPDATING config.php DYNAMIC BASE URL ---")
    if not os.path.exists(CONFIG_FILE):
        print(f"[WARN] config.php not found at {CONFIG_FILE}")
        return

    with open(CONFIG_FILE, "r", encoding="utf-8") as f:
        content = f.read()

    # Pattern for older static /web/ base detection
    old_block_pattern = r"if\s*\(!function_exists\('url'\)\)\s*\{.*?if\s*\(!function_exists\('asset'\)\)\s*\{.*?\n\}\n\}"
    
    new_block = """if (!function_exists('base_url')) {
    /**
     * Dynamically determine the base web URL path regardless of directory or server configuration.
     * Works seamlessly in XAMPP subdirectories, Apache VirtualHosts, or PHP built-in server.
     */
    function base_url() {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\\\', '/', dirname($script));
        if ($dir === '.' || $dir === '/' || $dir === '') {
            return '/';
        }
        return rtrim($dir, '/') . '/';
    }
}

if (!function_exists('url')) {
    /**
     * Generate an application route URL.
     * Example: url('user/home') -> /TesteShare-main/TesteShare-main/index.php?route=user/home
     */
    function url($path = 'user/home') {
        return base_url() . 'index.php?route=' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * Generate an asset URL.
     * Example: asset('css/bootstrap.min.css') -> /TesteShare-main/TesteShare-main/css/bootstrap.min.css
     */
    function asset($path) {
        return base_url() . ltrim($path, '/');
    }
}"""

    if "function base_url()" not in content:
        updated_content = re.sub(old_block_pattern, new_block, content, flags=re.DOTALL)
        if updated_content != content:
            with open(CONFIG_FILE, "w", encoding="utf-8") as f:
                f.write(updated_content)
            print("[SUCCESS] config.php updated with dynamic base_url(), url(), and asset() helpers!")
        else:
            print("[INFO] Direct regex match did not trigger, performing fallback replace for config.php...")
            start_idx = content.find("if (!function_exists('url'))")
            if start_idx != -1:
                asset_pos = content.find("if (!function_exists('asset'))", start_idx)
                # Find the closing brace of asset()
                closing_brace_pos = content.find("}", content.find("return", asset_pos))
                end_idx = content.find("}", closing_brace_pos + 1) + 1
                updated_content = content[:start_idx] + new_block + content[end_idx:]
                with open(CONFIG_FILE, "w", encoding="utf-8") as f:
                    f.write(updated_content)
                print("[SUCCESS] config.php updated via fallback replacement!")
    else:
        print("[EXISTS] config.php already has dynamic base_url().")

def replace_cdns_in_views():
    print("\n--- 3. SCANNING AND REPLACING CDN LINKS IN VIEWS ---")
    replacements = [
        # Bootstrap CSS CDN -> local asset
        (
            r'https://cdn\.jsdelivr\.net/npm/bootstrap@[0-9\.]+/dist/css/bootstrap\.min\.css',
            "<?php echo asset('css/bootstrap.min.css'); ?>"
        ),
        # Bootstrap JS CDN -> local asset
        (
            r'https://cdn\.jsdelivr\.net/npm/bootstrap@[0-9\.]+/dist/js/bootstrap\.bundle\.min\.js',
            "<?php echo asset('js/bootstrap.bundle.min.js'); ?>"
        ),
        # Leaflet CSS CDN -> local asset
        (
            r'https://unpkg\.com/leaflet@[0-9\.]+/dist/leaflet\.css',
            "<?php echo asset('css/leaflet.css'); ?>"
        ),
        # Leaflet JS CDN -> local asset
        (
            r'https://unpkg\.com/leaflet@[0-9\.]+/dist/leaflet\.js',
            "<?php echo asset('js/leaflet.js'); ?>"
        ),
        # Plus Jakarta Sans Google Fonts CDN -> local google-fonts.css
        (
            r'https://fonts\.googleapis\.com/css2\?family=Plus\+Jakarta\+Sans[^\"]*',
            "<?php echo asset('css/google-fonts.css'); ?>"
        ),
        # Material Symbols Google Fonts CDN -> local google-fonts.css
        (
            r'https://fonts\.googleapis\.com/css2\?family=Material\+Symbols\+Outlined[^\"]*',
            "<?php echo asset('css/google-fonts.css'); ?>"
        ),
        # Fix relative links in views/seller/register.php
        (
            r'<link\s+href="css/custom\.css"\s+rel="stylesheet">',
            '<link href="<?php echo asset(\'css/custom.css\'); ?>" rel="stylesheet">'
        ),
        (
            r'<script\s+src="js/main\.js"></script>',
            '<script src="<?php echo asset(\'js/main.js\'); ?>"></script>'
        ),
    ]

    total_files_modified = 0

    for root, _, files in os.walk(VIEWS_DIR):
        for file in files:
            if not file.endswith(".php"):
                continue
            filepath = os.path.join(root, file)
            with open(filepath, "r", encoding="utf-8") as f:
                original = f.read()

            modified = original
            for pattern, replacement in replacements:
                modified = re.sub(pattern, replacement, modified)

            # Deduplicate multiple consecutive <link href="<?php echo asset('css/google-fonts.css'); ?>" rel="stylesheet">
            font_link_pattern = r'(<link\s+href="<\?php\s+echo\s+asset\(\'css/google-fonts\.css\'\);\s*\?>"\s+rel="stylesheet">\s*){2,}'
            modified = re.sub(font_link_pattern, '<link href="<?php echo asset(\'css/google-fonts.css\'); ?>" rel="stylesheet">\n', modified)

            if modified != original:
                with open(filepath, "w", encoding="utf-8") as f:
                    f.write(modified)
                rel_path = os.path.relpath(filepath, BASE_DIR)
                print(f"[REPLACED] CDN links updated in: {rel_path}")
                total_files_modified += 1

    print(f"[SUMMARY] Total PHP views modified: {total_files_modified}")

def main():
    print("=" * 60)
    print(" TestShare Local Asset & CDN Migration Tool ")
    print("=" * 60)
    download_all_cdns()
    fix_config_php()
    replace_cdns_in_views()
    print("\n[COMPLETE] All CDN assets localized and configuration updated successfully!")

if __name__ == "__main__":
    main()
