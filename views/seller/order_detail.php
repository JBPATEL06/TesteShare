<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('getDB')) {
    require_once __DIR__ . '/../../db.php';
}
if (!function_exists('view')) {
    require_once __DIR__ . '/../../config.php';
}
$db = getDB();
$sellerId = $_SESSION['user_id'] ?? null;

// Resolve store associated with logged-in owner
$storeId = 0;
$myStore = null;
if ($sellerId) {
    $stmtStore = $db->prepare("SELECT * FROM stores WHERE owner_id = ?");
    $stmtStore->execute([$sellerId]);
    $myStore = $stmtStore->fetch();
    if ($myStore) {
        $storeId = $myStore['id'];
    }
}

// Handle delete dish
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $dishId = intval($_GET['id'] ?? 0);
    $del = $db->prepare("DELETE FROM menu_items WHERE id = ? AND store_id = ?");
    $del->execute([$dishId, $storeId]);
    header("Location: " . url('seller/order_detail'));
    exit;
}

// Handle toggle availability
if (isset($_GET['action']) && $_GET['action'] === 'toggle') {
    $dishId = intval($_GET['id'] ?? 0);
    $up = $db->prepare("UPDATE menu_items SET is_available = NOT is_available WHERE id = ? AND store_id = ?");
    $up->execute([$dishId, $storeId]);
    header("Location: " . url('seller/order_detail'));
    exit;
}

// Handle create dish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_dish') {
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? 'Appetizers');
    if ($category === 'CUSTOM') {
        $category = trim($_POST['custom_category'] ?? 'Appetizers');
    }
    $price = floatval($_POST['price'] ?? 0.0);
    $description = trim($_POST['description'] ?? '');
    $image_source_type = $_POST['image_source_type'] ?? 'upload';
    $imageUrl = '';
    if ($image_source_type === 'url') {
        $imageUrl = trim($_POST['image_url'] ?? '');
    } else {
        $imageUrl = trim($_POST['image_base64'] ?? '');
    }
    
    if (empty($imageUrl)) {
        $imageUrl = 'images/smoked_bone_marrow.png';
    }
    
    if ($name && $storeId) {
        $ins = $db->prepare("INSERT INTO menu_items (store_id, name, description, price, category, image_url, is_available) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $ins->execute([$storeId, $name, $description, $price, $category, $imageUrl]);
    }
    header("Location: " . url('seller/order_detail'));
    exit;
}

// Fetch all menu items for the store
$stmt = $db->prepare("SELECT * FROM menu_items WHERE store_id = ? ORDER BY category, name");
$stmt->execute([$storeId]);
$dishes = $stmt->fetchAll();

// Group dishes by category
$categories = [];
foreach ($dishes as $dish) {
    $categories[$dish['category']][] = $dish;
}

$pageTitle = 'Dish Studio';
$activeNav = 'order_detail';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>
    <!-- Main Content Canvas -->
    <div class="flex-grow p-8 overflow-y-auto">
        <div class="max-w-6xl mx-auto">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Dish Studio</h2>
                    <p class="text-on-surface-variant font-body-md text-body-md mb-0">Create and curate your digital culinary experience.</p>
                </div>
                <button class="bg-primary-container text-on-primary-container font-bold px-6 py-3 rounded-lg flex items-center gap-2 hover:opacity-90 active:scale-95 transition-all border-0 font-bold" onclick="toggleForm()">
                    <span class="material-symbols-outlined" data-icon="add">add</span>
                    <span>Add New Dish</span>
                </button>
            </div>
            
            <!-- Category Filter Tabs -->
            <div class="flex gap-4 mb-8 border-b border-outline-variant overflow-x-auto pb-2" id="dishCategoryTabs">
                <button onclick="filterDishCategory('all', this)" class="px-4 py-2 text-primary font-bold border-b-2 border-primary whitespace-nowrap bg-transparent cursor-pointer dish-cat-btn font-bold">All Items</button>
                <?php foreach (array_keys($categories) as $catKey): ?>
                    <button onclick="filterDishCategory('<?php echo htmlspecialchars(addslashes($catKey)); ?>', this)" class="px-4 py-2 text-on-surface-variant hover:text-on-surface transition-colors whitespace-nowrap bg-transparent border-0 font-bold cursor-pointer dish-cat-btn"><?php echo htmlspecialchars($catKey); ?></button>
                <?php endforeach; ?>
            </div>
            
            <!-- Menu Grid -->
            <div class="space-y-12 pb-12">
                <?php if (empty($categories)): ?>
                    <p class="text-on-surface-variant font-body-md">No dishes added to your storefront yet. Click "Add New Dish" to get started!</p>
                <?php else: ?>
                    <?php foreach ($categories as $catName => $catDishes): ?>
                        <section class="dish-category-section" data-category="<?php echo htmlspecialchars($catName); ?>">
                            <div class="flex items-center gap-3 mb-6">
                                <span class="w-1.5 h-6 bg-primary rounded-full"></span>
                                <h3 class="font-headline-md text-headline-md text-on-surface font-bold mb-0"><?php echo htmlspecialchars($catName); ?></h3>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <?php foreach ($catDishes as $dish): ?>
                                    <?php
                                    $imgUrl = $dish['image_url'] ? asset($dish['image_url']) : asset('images/smoked_bone_marrow.png');
                                    ?>
                                    <!-- Item Card -->
                                    <div class="bg-surface-container border border-outline-variant p-4 rounded-xl group hover:border-primary transition-colors flex flex-col justify-between">
                                        <div>
                                            <div class="relative w-full h-48 mb-4 rounded-lg overflow-hidden border border-outline-variant">
                                                <div class="w-full h-full bg-cover bg-center" style="background-image: url('<?php echo $imgUrl; ?>');"></div>
                                                <?php if (!$dish['is_available']): ?>
                                                    <div class="absolute inset-0 bg-black/60 flex items-center justify-center">
                                                        <span class="bg-error text-on-error px-3 py-1 rounded text-[12px] font-bold uppercase tracking-widest">Sold Out</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex justify-between items-start mb-4">
                                                <div>
                                                    <h4 class="font-bold text-on-surface text-lg mb-1"><?php echo htmlspecialchars($dish['name']); ?></h4>
                                                    <p class="text-primary font-bold mb-0">₹<?php echo number_format($dish['price'], 2); ?></p>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-[10px] text-on-surface-variant uppercase font-bold"><?php echo $dish['is_available'] ? 'Available' : 'Sold Out'; ?></span>
                                                    <label class="relative inline-flex items-center cursor-pointer mb-0">
                                                        <input onchange="window.location.href='<?php echo url('seller/order_detail&action=toggle&id=' . $dish['id']); ?>'" <?php echo $dish['is_available'] ? 'checked' : ''; ?> class="sr-only toggle-checkbox" type="checkbox">
                                                        <div class="w-10 h-5 bg-surface-variant rounded-full transition-colors toggle-label border border-outline-variant">
                                                            <div class="toggle-dot absolute left-1 top-1 w-3 h-3 bg-white rounded-full transition-transform <?php echo $dish['is_available'] ? 'translate-x-5 bg-primary' : ''; ?>"></div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                            <p class="text-on-surface-variant text-body-sm leading-relaxed mb-4"><?php echo htmlspecialchars($dish['description']); ?></p>
                                        </div>
                                        <div class="flex gap-2 mt-4">
                                            <button onclick="window.location.href='<?php echo url('seller/order_detail&action=delete&id=' . $dish['id']); ?>'" class="w-full border border-outline-variant text-on-surface-variant py-2 rounded-lg font-label-md text-label-md hover:text-error hover:border-error transition-colors bg-transparent flex items-center justify-center gap-2 font-bold">
                                                <span class="material-symbols-outlined text-sm">delete</span> Delete Dish
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="flex flex-col md:flex-row justify-between items-center px-8 py-6 border-t border-outline-variant bg-surface shrink-0">
        <p class="font-label-sm text-label-sm text-on-surface-variant mb-4 md:mb-0">© 2024 TestShare Merchant Portal. All rights reserved.</p>
        <div class="flex gap-8">
            <a class="font-label-sm text-label-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none font-bold" href="#">Privacy Policy</a>
            <a class="font-label-sm text-label-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none font-bold" href="#">Terms of Service</a>
            <a class="font-label-sm text-label-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none font-bold" href="#">Merchant Support</a>
        </div>
    </footer>

    <!-- Add New Dish Modal (Hidden by Default) -->
    <div class="fixed inset-0 z-[60] bg-black/80 flex items-center justify-center p-4 hidden" id="dishFormModal">
        <div class="bg-surface border border-outline-variant w-full max-w-2xl max-h-[90vh] overflow-y-auto custom-scrollbar flex flex-col rounded-2xl shadow-2xl">
            <div class="p-6 border-b border-outline-variant flex justify-between items-center sticky top-0 bg-surface z-10">
                <h3 class="font-headline-md text-headline-md text-on-surface font-bold mb-0">Add New Dish</h3>
                <button class="text-on-surface-variant hover:text-on-surface transition-colors bg-transparent border-0 p-1" onclick="toggleForm()">
                    <span class="material-symbols-outlined" data-icon="close">close</span>
                </button>
            </div>
            <form method="POST" action="" class="p-6 space-y-6">
                <input type="hidden" name="action" value="create_dish">
                <div class="space-y-2">
                    <label class="block font-label-md text-label-md text-on-surface-variant font-bold">Dish Name</label>
                    <input name="name" class="w-full bg-surface border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg outline-none transition-all placeholder:text-on-surface-variant/30" placeholder="e.g. Butter Chicken" type="text" required>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block font-label-md text-label-md text-on-surface-variant font-bold">Category</label>
                        <select name="category" id="categorySelect" onchange="checkCustomCategory(this.value)" class="w-full bg-surface border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg outline-none transition-all appearance-none font-bold">
                            <option value="North Indian">North Indian</option>
                            <option value="South Indian">South Indian</option>
                            <option value="Appetizers">Appetizers</option>
                            <option value="Drinks">Drinks</option>
                            <option value="Dessert">Dessert</option>
                            <option value="CUSTOM">-- Write Custom Category --</option>
                        </select>
                    </div>
                    <div class="space-y-2 hidden" id="customCategoryGroup">
                        <label class="block font-label-md text-label-md text-on-surface-variant font-bold">Custom Category Name</label>
                        <input type="text" id="customCategoryInput" name="custom_category" class="w-full bg-surface border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg outline-none transition-all" placeholder="e.g. Tandoori Snacks">
                    </div>
                    <div class="space-y-2">
                        <label class="block font-label-md text-label-md text-on-surface-variant font-bold">Price ($)</label>
                        <input name="price" class="w-full bg-surface border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg outline-none transition-all" placeholder="0.00" step="0.01" type="number" required>
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="block font-label-md text-label-md text-on-surface-variant font-bold">Description</label>
                    <textarea name="description" class="w-full bg-surface border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg outline-none text-on-surface p-3 resize-none placeholder:text-on-surface-variant/30" placeholder="Describe the flavors, ingredients..." rows="4" required></textarea>
                </div>
                <div class="space-y-2">
                    <label class="block font-label-md text-label-md text-on-surface-variant font-bold">Dish Image</label>
                    <div class="flex gap-2 mb-3 bg-surface-container-low p-1 rounded-lg border border-outline-variant">
                        <button type="button" id="toggle-dish-upload" onclick="switchDishImageSource('upload')" class="flex-1 py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-primary-container text-on-primary-container">
                            Upload File
                        </button>
                        <button type="button" id="toggle-dish-url" onclick="switchDishImageSource('url')" class="flex-1 py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-transparent text-on-surface-variant">
                            Enter URL
                        </button>
                    </div>
                    
                    <input type="hidden" name="image_source_type" id="image_source_type" value="upload">
                    <input type="hidden" name="image_base64" id="image_base64" value="">

                    <div id="dish-upload-group" class="transition-all">
                        <label for="dish_image_file" class="flex flex-col items-center justify-center border-2 border-dashed border-outline-variant hover:border-primary rounded-xl p-4 cursor-pointer hover:bg-surface-container-high transition-colors text-center">
                            <span class="material-symbols-outlined text-3xl text-primary mb-1">cloud_upload</span>
                            <span class="text-xs font-bold text-on-surface block truncate max-w-full" id="dish-file-label">Choose Image File</span>
                            <input type="file" id="dish_image_file" accept="image/*" class="hidden" onchange="handleDishImageUpload(this)">
                        </label>
                    </div>
                    <div id="dish-url-group" class="hidden transition-all">
                        <input name="image_url" id="dish_image_url" type="url" placeholder="https://images.unsplash.com/...jpg" class="w-full bg-surface border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg outline-none transition-all">
                    </div>
                </div>
                <div class="flex items-center gap-6 pt-6 border-t border-outline-variant">
                    <button class="flex-1 py-3 border border-outline-variant text-on-surface rounded-lg hover:bg-surface-container-high transition-colors font-bold bg-transparent" onclick="toggleForm()" type="button">Cancel</button>
                    <button class="flex-1 py-3 bg-primary-container text-on-primary-container rounded-lg font-bold hover:opacity-90 active:scale-95 transition-all border-0 font-bold" type="submit">Create Dish</button>
                </div>
            </form>
        </div>
    </div>

<script>
    function toggleForm() {
        const modal = document.getElementById('dishFormModal');
        modal.classList.toggle('hidden');
    }

    function checkCustomCategory(val) {
        const group = document.getElementById('customCategoryGroup');
        const input = document.getElementById('customCategoryInput');
        if (val === 'CUSTOM') {
            group.classList.remove('hidden');
            input.required = true;
        } else {
            group.classList.add('hidden');
            input.required = false;
        }
    }

    function switchDishImageSource(type) {
        const btnUpload = document.getElementById('toggle-dish-upload');
        const btnUrl = document.getElementById('toggle-dish-url');
        const grpUpload = document.getElementById('dish-upload-group');
        const grpUrl = document.getElementById('dish-url-group');
        const inputType = document.getElementById('image_source_type');

        inputType.value = type;
        if (type === 'upload') {
            btnUpload.className = 'flex-1 py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-primary-container text-on-primary-container';
            btnUrl.className = 'flex-1 py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-transparent text-on-surface-variant';
            grpUpload.classList.remove('hidden');
            grpUrl.classList.add('hidden');
        } else {
            btnUpload.className = 'flex-1 py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-transparent text-on-surface-variant';
            btnUrl.className = 'flex-1 py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-primary-container text-on-primary-container';
            grpUpload.classList.add('hidden');
            grpUrl.classList.remove('hidden');
        }
    }

    function handleDishImageUpload(input) {
        const file = input.files[0];
        if (file) {
            const label = document.getElementById('dish-file-label');
            label.textContent = file.name;
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('image_base64').value = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }

    function filterDishCategory(cat, btn) {
        document.querySelectorAll('.dish-cat-btn').forEach(b => {
            b.classList.remove('text-primary', 'border-b-2', 'border-primary');
            b.classList.add('text-on-surface-variant', 'border-0');
        });
        if (btn) {
            btn.classList.remove('text-on-surface-variant', 'border-0');
            btn.classList.add('text-primary', 'border-b-2', 'border-primary');
        }
        document.querySelectorAll('.dish-category-section').forEach(sec => {
            if (cat === 'all' || sec.getAttribute('data-category') === cat) {
                sec.style.display = 'block';
            } else {
                sec.style.display = 'none';
            }
        });
    }
</script>
<?php view('partials/seller_footer'); ?>
