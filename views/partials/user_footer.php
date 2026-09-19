    <!-- Footer -->
    <footer class="bg-surface-container border-top border-outline-variant py-5">
        <div class="container-xl d-flex flex-column flex-md-row justify-content-between align-items-center gap-4">
            <div class="d-flex flex-column align-items-center align-items-md-start gap-1">
                <span class="fs-5 fw-bold text-on-surface">TestShare</span>
                <p class="fs-8 text-on-surface-variant mb-0">© 2026 TestShare. All rights reserved.</p>
            </div>
            <div class="d-flex flex-wrap justify-content-center gap-4">
                <a class="fs-8 text-on-surface-variant text-decoration-none hover-primary" href="<?php echo url('user/help'); ?>">Support</a>
                <a class="fs-8 text-on-surface-variant text-decoration-none hover-primary" href="#">Privacy Policy</a>
                <a class="fs-8 text-on-surface-variant text-decoration-none hover-primary" href="#">Terms of Service</a>
                <a class="fs-8 text-on-surface-variant text-decoration-none hover-primary" href="<?php echo url('seller/register'); ?>">Become a Partner</a>
            </div>
            <div class="d-flex gap-3">
                <button class="btn btn-outline-secondary d-flex align-items-center justify-content-center p-2">
                    <span class="material-symbols-outlined fs-6">face_nod</span>
                </button>
                <button class="btn btn-outline-secondary d-flex align-items-center justify-content-center p-2">
                    <span class="material-symbols-outlined fs-6">share</span>
                </button>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="<?php echo asset('js/bootstrap.bundle.min.js'); ?>"></script>
    <!-- Custom Main JS -->
    <script src="<?php echo asset('js/main.js'); ?>"></script>
</body>
</html>
