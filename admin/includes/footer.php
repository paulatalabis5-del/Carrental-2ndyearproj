        </div> <!-- End of container-fluid -->
    </div> <!-- End of main-content -->
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const desktopSidebarToggle = document.getElementById('desktopSidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const overlay = document.querySelector('.sidebar-overlay');
            
            // Mobile sidebar toggle
            function toggleMobileSidebar() {
                sidebar.classList.toggle('active');
                mainContent.classList.toggle('sidebar-active');
                document.body.classList.toggle('sidebar-active');
                overlay.classList.toggle('active');
                
                // Toggle hamburger icon
                const icon = sidebarToggle.querySelector('i');
                if (sidebar.classList.contains('active')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
            
            // Desktop sidebar toggle
            function toggleDesktopSidebar() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                document.body.classList.toggle('sidebar-collapsed');
                
                // Rotate the toggle button icon
                const icon = desktopSidebarToggle.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
            }
            
            sidebarToggle.addEventListener('click', toggleMobileSidebar);
            desktopSidebarToggle.addEventListener('click', toggleDesktopSidebar);
            overlay.addEventListener('click', toggleMobileSidebar);
            
            // Close sidebar on window resize if screen becomes larger
            let resizeTimeout;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                        toggleMobileSidebar();
                    }
                }, 250);
            });
        });
    </script>
</body>
</html> 