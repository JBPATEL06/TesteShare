import os
import re
import sys

if sys.stdout.encoding != 'utf-8':
    sys.stdout.reconfigure(encoding='utf-8')

BASE_DIR = r"c:\xampp\htdocs\TesteShare-main\TesteShare-main"

# Replacement rules for PHP views
view_replacements = [
    # Static UI text
    (r'Upgrade to Premium \(\$49/mo\)', 'Upgrade to Premium (₹49/mo)'),
    (r'Upgrade to Ultra Premium \(\$149/mo\)', 'Upgrade to Ultra Premium (₹149/mo)'),
    (r'Min\. order \$15\.00', 'Min. order ₹15.00'),
    (r'Free over \$20', 'Free over ₹20'),
    (r'\$10 Wallet', '₹10 Wallet'),
    (r'\$296\.50', '₹296.50'),
    (r'\$48\.00', '₹48.00'),
    
    # JS total string concatenation
    (r"totalEl\.innerText = '\$' \+", "totalEl.innerText = '₹' +"),
    
    # Currency prefixes before php echo
    (r'>\$<\?php\s*echo', '>₹<?php echo'),
    (r' \$(<\?php\s*echo)', ' ₹\\1'),
    (r'\(\$(<\?php\s*echo)', '(₹\\1'),
    (r'Price:\s*\$(<\?php\s*echo)', 'Price: ₹\\1'),
    (r'Total:\s*\$(<\?php\s*echo)', 'Total: ₹\\1'),
    (r'Min\.\s*Order:\s*\$(<\?php\s*echo)', 'Min. Order: ₹\\1'),
]

def update_views():
    views_dir = os.path.join(BASE_DIR, "views")
    modified_count = 0
    for root, _, files in os.walk(views_dir):
        for f in files:
            if not f.endswith(".php"):
                continue
            path = os.path.join(root, f)
            with open(path, "r", encoding="utf-8") as file:
                content = file.read()
            
            orig = content
            for pattern, repl in view_replacements:
                content = re.sub(pattern, repl, content)
            
            if content != orig:
                with open(path, "w", encoding="utf-8") as file:
                    file.write(content)
                print(f"[UPDATED VIEW] {os.path.relpath(path, BASE_DIR)}")
                modified_count += 1
    print(f"Total views updated: {modified_count}")

def update_pages():
    page_dir = os.path.join(BASE_DIR, "page")
    modified_count = 0
    for root, _, files in os.walk(page_dir):
        for f in files:
            if not f.endswith(".html"):
                continue
            path = os.path.join(root, f)
            with open(path, "r", encoding="utf-8") as file:
                content = file.read()
            
            orig = content
            # Replace $ followed by numbers in HTML
            content = re.sub(r'\$(\d+(?:\.\d+)?)', r'₹\1', content)
            # Replace '$' + in JS
            content = re.sub(r"'\$'\s*\+", "'₹' +", content)
            content = re.sub(r'"\$"\s*\+', '"₹" +', content)
            content = re.sub(r"'\$'\s*\+\s*next", "'₹' + next", content)
            
            if content != orig:
                with open(path, "w", encoding="utf-8") as file:
                    file.write(content)
                print(f"[UPDATED PAGE] {os.path.relpath(path, BASE_DIR)}")
                modified_count += 1
    print(f"Total pages updated: {modified_count}")

if __name__ == "__main__":
    print("Replacing dollar symbols with INR (₹)...")
    update_views()
    update_pages()
    print("Finished replacement!")
