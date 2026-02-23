    </div>
    
    <script>
        // Navigation Scrolling
        const navMenu = document.getElementById('admin-nav');
        if (navMenu) {
            const scrollLeftBtn = document.getElementById('nav-scroll-left');
            const scrollRightBtn = document.getElementById('nav-scroll-right');
            const scrollAmount = 200; // Pixels to scroll per click
            
            function updateScrollButtons() {
                if (!navMenu || !scrollLeftBtn || !scrollRightBtn) return;
                
                const isAtStart = navMenu.scrollLeft <= 0;
                const isAtEnd = navMenu.scrollLeft >= navMenu.scrollWidth - navMenu.clientWidth - 1;
                
                scrollLeftBtn.disabled = isAtStart;
                scrollRightBtn.disabled = isAtEnd;
            }
            
            function scrollNav(direction) {
                if (!navMenu) return;
                
                const currentScroll = navMenu.scrollLeft;
                const scrollTo = direction === 'left' 
                    ? currentScroll - scrollAmount 
                    : currentScroll + scrollAmount;
                
                navMenu.scrollTo({
                    left: scrollTo,
                    behavior: 'smooth'
                });
            }
            
            // Update button states on scroll
            navMenu.addEventListener('scroll', updateScrollButtons);
            
            // Update button states on window resize
            window.addEventListener('resize', updateScrollButtons);
            
            // Initial button state check
            updateScrollButtons();
            
            // Check if scrolling is needed after page load
            setTimeout(updateScrollButtons, 100);
            
            // Smooth scroll for navigation items
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    document.querySelectorAll('.nav-item').forEach(nav => {
                        nav.classList.remove('active', 'font-bold', 'text-netflix-red');
                    });
                    this.classList.add('active', 'font-bold', 'text-netflix-red');
                    
                    // Scroll active item into view
                    setTimeout(() => {
                        this.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    }, 100);
                });
            });
        }
        
        // Profile Dropdown Toggle
        function toggleProfileMenu() {
            const menu = document.getElementById('profile-menu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('profile-menu');
            const button = event.target.closest('[onclick="toggleProfileMenu()"]');
            
            if (menu && !button && !menu.classList.contains('hidden')) {
                menu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
